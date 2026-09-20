<?php
namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Mailer;
use App\Services\NotificationService;
use PDO;

final class SupportController
{
    public function __construct(private string $root, private PDO $db, private Auth $auth) {}

    public function publicForm(): void
    {
        $seller=$this->auth->seller();
        View::render($this->root,'public/contact',[
            'pageTitle'=>'Kontakt',
            'seller'=>$seller,
        ]);
    }

    public function publicSubmit(Request $r): void
    {
        $seller=$this->auth->seller();
        $key='support:'.$r->ip();
        $limiter=new RateLimiter($this->db);
        if($limiter->tooMany($key,5,3600)){
            Session::flash('error','Zu viele Kontaktanfragen. Bitte versuche es später erneut.');
            Response::redirect('/kontakt');
        }

        $name=trim((string)$r->input('name'));
        $email=mb_strtolower(trim((string)$r->input('email')));
        $subject=trim((string)$r->input('subject'));
        $message=trim((string)$r->input('message'));

        if($seller){
            $name=trim($seller['first_name'].' '.$seller['last_name']);
            $email=$seller['email'];
        }

        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$subject===''||mb_strlen($message)<10){
            Session::flash('error','Bitte Name, gültige E-Mail-Adresse, Betreff und eine ausführliche Nachricht angeben.');
            Response::redirect('/kontakt');
        }

        $limiter->hit($key);
        $this->db->beginTransaction();
        try{
            $q=$this->db->prepare("INSERT INTO support_tickets(seller_id,name,email,subject,status,priority,created_at,updated_at) VALUES(?,?,?,?, 'open','normal',NOW(),NOW())");
            $q->execute([$seller['id']??null,$name,$email,$subject]);
            $id=(int)$this->db->lastInsertId();
            $ticket='SUP-'.date('Y').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
            $this->db->prepare('UPDATE support_tickets SET ticket_number=? WHERE id=?')->execute([$ticket,$id]);
            $this->db->prepare("INSERT INTO support_messages(ticket_id,sender_type,sender_id,message,created_at) VALUES(?,?,?,?,NOW())")
                ->execute([$id,$seller?'seller':'guest',$seller['id']??null,$message]);
            $this->db->commit();

            $cfg=require $this->root.'/config/app.php';
            $mailer=new Mailer($cfg);
            $mailer->send($email,'Supportanfrage '.$ticket,'<p>Deine Anfrage wurde als <strong>'.htmlspecialchars($ticket,ENT_QUOTES,'UTF-8').'</strong> gespeichert.</p><p>Betreff: '.htmlspecialchars($subject,ENT_QUOTES,'UTF-8').'</p><p>Du erhältst eine weitere Nachricht, sobald eine Antwort vorliegt.</p>');

            Session::flash('success','Deine Anfrage wurde gespeichert. Ticketnummer: '.$ticket);
            Response::redirect($seller?'/konto/support/'.$id:'/kontakt');
        }catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
    }

    public function sellerIndex(): void
    {
        $seller=$this->auth->seller();
        $q=$this->db->prepare("SELECT * FROM support_tickets WHERE seller_id=? ORDER BY updated_at DESC,id DESC");
        $q->execute([$seller['id']]);
        View::render($this->root,'seller/support',[
            'pageTitle'=>'Support',
            'tickets'=>$q->fetchAll(),
        ]);
    }

    public function sellerShow(Request $r,array $p): void
    {
        $seller=$this->auth->seller();
        $q=$this->db->prepare('SELECT * FROM support_tickets WHERE id=? AND seller_id=?');
        $q->execute([(int)$p['id'],$seller['id']]);
        $ticket=$q->fetch();
        if(!$ticket)Response::abort(404,'Supportanfrage nicht gefunden.');

        $m=$this->db->prepare('SELECT * FROM support_messages WHERE ticket_id=? ORDER BY created_at,id');
        $m->execute([$ticket['id']]);
        View::render($this->root,'seller/support-ticket',[
            'pageTitle'=>$ticket['ticket_number'],
            'ticket'=>$ticket,
            'messages'=>$m->fetchAll(),
        ]);
    }

    public function sellerReply(Request $r,array $p): void
    {
        $seller=$this->auth->seller();
        $message=trim((string)$r->input('message'));
        if($message===''){
            Session::flash('error','Bitte eine Nachricht eingeben.');
            Response::redirect('/konto/support/'.(int)$p['id']);
        }

        $q=$this->db->prepare('SELECT * FROM support_tickets WHERE id=? AND seller_id=?');
        $q->execute([(int)$p['id'],$seller['id']]);
        $ticket=$q->fetch();
        if(!$ticket)Response::abort(404);
        if($ticket['status']==='closed'){
            Session::flash('error','Dieses Ticket ist geschlossen.');
            Response::redirect('/konto/support/'.$ticket['id']);
        }

        $this->db->beginTransaction();
        try{
            $this->db->prepare("INSERT INTO support_messages(ticket_id,sender_type,sender_id,message,created_at) VALUES(?,'seller',?,?,NOW())")
                ->execute([$ticket['id'],$seller['id'],$message]);
            $this->db->prepare("UPDATE support_tickets SET status='waiting_admin',updated_at=NOW() WHERE id=?")->execute([$ticket['id']]);
            $this->db->commit();
            Session::flash('success','Antwort wurde gesendet.');
        }catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
        Response::redirect('/konto/support/'.$ticket['id']);
    }

    public function adminIndex(Request $r): void
    {
        $status=trim((string)$r->input('status'));
        $term=trim((string)$r->input('q'));
        $where=['1=1'];$params=[];
        if(in_array($status,['open','waiting_admin','waiting_seller','closed'],true)){
            $where[]='t.status=?';$params[]=$status;
        }
        if($term!==''){
            $like='%'.$term.'%';
            $where[]='(t.ticket_number LIKE ? OR t.name LIKE ? OR t.email LIKE ? OR t.subject LIKE ?)';
            array_push($params,$like,$like,$like,$like);
        }
        $sql="SELECT t.*,s.first_name,s.last_name FROM support_tickets t LEFT JOIN sellers s ON s.id=t.seller_id WHERE ".implode(' AND ',$where)." ORDER BY FIELD(t.status,'open','waiting_admin','waiting_seller','closed'),t.updated_at DESC";
        $q=$this->db->prepare($sql);$q->execute($params);
        View::render($this->root,'admin/support',[
            'pageTitle'=>'Support',
            'tickets'=>$q->fetchAll(),
            'filterStatus'=>$status,
            'filterTerm'=>$term,
        ]);
    }

    public function adminShow(Request $r,array $p): void
    {
        $q=$this->db->prepare('SELECT t.*,s.first_name,s.last_name,s.email seller_email FROM support_tickets t LEFT JOIN sellers s ON s.id=t.seller_id WHERE t.id=?');
        $q->execute([(int)$p['id']]);
        $ticket=$q->fetch();
        if(!$ticket)Response::abort(404);

        $m=$this->db->prepare('SELECT * FROM support_messages WHERE ticket_id=? ORDER BY created_at,id');
        $m->execute([$ticket['id']]);
        View::render($this->root,'admin/support-ticket',[
            'pageTitle'=>$ticket['ticket_number'],
            'ticket'=>$ticket,
            'messages'=>$m->fetchAll(),
        ]);
    }

    public function adminReply(Request $r,array $p): void
    {
        $admin=$this->auth->admin();
        $id=(int)$p['id'];
        $message=trim((string)$r->input('message'));
        if($message===''){
            Session::flash('error','Bitte eine Antwort eingeben.');
            Response::redirect('/admin/support/'.$id);
        }

        $q=$this->db->prepare('SELECT * FROM support_tickets WHERE id=?');
        $q->execute([$id]);
        $ticket=$q->fetch();
        if(!$ticket)Response::abort(404);

        $this->db->beginTransaction();
        try{
            $this->db->prepare("INSERT INTO support_messages(ticket_id,sender_type,sender_id,message,created_at) VALUES(?,'admin',?,?,NOW())")
                ->execute([$id,$admin['id'],$message]);
            $this->db->prepare("UPDATE support_tickets SET status='waiting_seller',updated_at=NOW() WHERE id=?")->execute([$id]);
            $this->db->commit();

            $cfg=require $this->root.'/config/app.php';
            $notifyUrl=$ticket['seller_id']?'/konto/support/'.$id:'/kontakt';
            if($ticket['seller_id']){
                (new NotificationService($this->db,new Mailer($cfg)))->seller((int)$ticket['seller_id'],'support','Neue Support-Antwort','Zu '.$ticket['ticket_number'].' liegt eine neue Antwort vor.',$notifyUrl,true);
            }else{
                (new Mailer($cfg))->send($ticket['email'],'Antwort zu '.$ticket['ticket_number'],'<p>Zu deiner Supportanfrage liegt eine neue Antwort vor:</p><p>'.nl2br(htmlspecialchars($message,ENT_QUOTES,'UTF-8')).'</p>');
            }
            Session::flash('success','Supportantwort wurde gespeichert.');
        }catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }

        Response::redirect('/admin/support/'.$id);
    }

    public function adminStatus(Request $r,array $p): void
    {
        $status=(string)$r->input('status');
        if(!in_array($status,['open','waiting_admin','waiting_seller','closed'],true))Response::abort(422);
        $id=(int)$p['id'];
        $this->db->prepare("UPDATE support_tickets SET status=?,updated_at=NOW(),closed_at=CASE WHEN ?='closed' THEN NOW() ELSE NULL END WHERE id=?")
            ->execute([$status,$status,$id]);
        Session::flash('success','Ticketstatus wurde aktualisiert.');
        Response::redirect('/admin/support/'.$id);
    }
}
