<?php
namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\SellerScheduleService;
use PDO;

final class SellerController
{
    public function __construct(private string $root, private PDO $db, private Auth $auth) {}

    public function dashboard(): void
    {
        $s = $this->auth->seller();

        $ordersQ = $this->db->prepare("SELECT o.*,ov.title,
            (SELECT COUNT(*) FROM order_components oc WHERE oc.order_id=o.id) component_count,
            (SELECT COUNT(*) FROM order_offer_items ooi WHERE ooi.order_id=o.id) offer_count
            FROM orders o
            JOIN offer_versions ov ON ov.id=o.offer_version_id
            WHERE o.seller_id=? AND o.archived_at IS NULL
            ORDER BY o.updated_at DESC LIMIT 8");
        $ordersQ->execute([$s['id']]);
        $orders = $ordersQ->fetchAll();
        foreach($orders as &$dashboardOrder){if((int)($dashboardOrder['offer_count']??0)>1)$dashboardOrder['title']='Sammelauftrag · '.(int)$dashboardOrder['offer_count'].' Angebote';}
        unset($dashboardOrder);

        $n = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE seller_id=? AND read_at IS NULL");
        $n->execute([$s['id']]);
        $unread = (int) $n->fetchColumn();

        $private = $this->db->prepare("SELECT o.id,o.title,o.acceptance_deadline,ov.compensation,c.name category_name
            FROM offers o
            JOIN offer_versions ov ON ov.id=o.current_version_id
            JOIN categories c ON c.id=o.category_id
            WHERE o.seller_id=? AND o.is_private=1 AND o.status='active' AND o.private_offer_status='pending'
            AND (o.acceptance_deadline IS NULL OR o.acceptance_deadline>NOW())
            ORDER BY o.acceptance_deadline IS NULL,o.acceptance_deadline");
        $private->execute([$s['id']]);
        $privateOffers = $private->fetchAll();

        $walletQ = $this->db->prepare('SELECT * FROM wallets WHERE seller_id=?');
        $walletQ->execute([$s['id']]);
        $wallet = $walletQ->fetch() ?: ['balance_reserved'=>0,'balance_in_review'=>0,'balance_available'=>0];

        $activeQ = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE seller_id=? AND status NOT IN('rejected','cancelled','archived','paid')");
        $activeQ->execute([$s['id']]);
        $activeOrders = (int) $activeQ->fetchColumn();

        $actions = [];

        $precheckQ = $this->db->prepare("SELECT o.id,o.order_number,
            CASE WHEN (SELECT COUNT(*) FROM order_offer_items ooi WHERE ooi.order_id=o.id)>1
              THEN CONCAT('Sammelauftrag · ',(SELECT COUNT(*) FROM order_offer_items ooi2 WHERE ooi2.order_id=o.id),' Angebote')
              ELSE ov.title END title,COUNT(*) open_count
            FROM precheck_requirements pr
            JOIN orders o ON o.id=pr.order_id
            JOIN offer_versions ov ON ov.id=o.offer_version_id
            WHERE o.seller_id=? AND o.started_at IS NULL
            AND (SELECT COUNT(*) FROM evidences e WHERE e.precheck_requirement_id=pr.id AND e.review_status='accepted') < pr.required_count
            GROUP BY o.id,o.order_number,ov.title
            ORDER BY o.updated_at DESC LIMIT 5");
        $precheckQ->execute([$s['id']]);
        foreach ($precheckQ->fetchAll() as $row) {
            $actions[] = [
                'priority' => 1,
                'title' => 'Vorabkontrolle abschließen',
                'text' => '#' . $row['order_number'] . ' · ' . $row['title'] . ' · ' . $row['open_count'] . ' Perspektive(n) offen',
                'url' => '/konto/auftraege/' . $row['id'],
                'due' => null,
            ];
        }

        $windowQ = $this->db->prepare("SELECT o.id,o.order_number,ov.title,ew.name,ew.ends_at,ew.grace_ends_at,ew.required_count,
            (SELECT COUNT(*) FROM evidences e WHERE e.evidence_window_id=ew.id) uploaded_count
            FROM evidence_windows ew
            JOIN order_days od ON od.id=ew.order_day_id
            JOIN orders o ON o.id=od.order_id
            JOIN offer_versions ov ON ov.id=o.offer_version_id
            WHERE o.seller_id=? AND ew.starts_at<=NOW() AND ew.grace_ends_at>=NOW() AND ew.status='open'
            HAVING uploaded_count < ew.required_count
            ORDER BY ew.ends_at LIMIT 5");
        $windowQ->execute([$s['id']]);
        foreach ($windowQ->fetchAll() as $row) {
            $actions[] = [
                'priority' => 0,
                'title' => 'Nachweis jetzt fällig',
                'text' => '#' . $row['order_number'] . ' · ' . $row['name'] . ' · ' . $row['uploaded_count'] . '/' . $row['required_count'] . ' eingereicht',
                'url' => '/konto/auftraege/' . $row['id'],
                'due' => $row['ends_at'],
            ];
        }

        $taskQ = $this->db->prepare("SELECT o.id,o.order_number,t.title,te.due_at
            FROM task_executions te
            JOIN tasks t ON t.id=te.task_id
            JOIN orders o ON o.id=t.order_id
            WHERE o.seller_id=? AND te.submitted_at IS NULL AND te.grace_ends_at>=NOW()
            ORDER BY te.due_at LIMIT 5");
        $taskQ->execute([$s['id']]);
        foreach ($taskQ->fetchAll() as $row) {
            $actions[] = [
                'priority' => strtotime($row['due_at']) <= time()+3600 ? 0 : 2,
                'title' => 'Zusatzaufgabe offen',
                'text' => '#' . $row['order_number'] . ' · ' . $row['title'],
                'url' => '/konto/auftraege/' . $row['id'],
                'due' => $row['due_at'],
            ];
        }

        $spontQ = $this->db->prepare("SELECT o.id,o.order_number,sr.motif,sr.deadline,sr.requested_count,
            (SELECT COUNT(*) FROM evidences e WHERE e.spontaneous_request_id=sr.id) uploaded_count
            FROM spontaneous_requests sr
            JOIN orders o ON o.id=sr.order_id
            WHERE o.seller_id=? AND sr.grace_ends_at>=NOW() AND sr.status='requested'
            HAVING uploaded_count < sr.requested_count
            ORDER BY sr.deadline LIMIT 5");
        $spontQ->execute([$s['id']]);
        foreach ($spontQ->fetchAll() as $row) {
            $actions[] = [
                'priority' => strtotime($row['deadline']) <= time()+3600 ? 0 : 1,
                'title' => 'Zusätzliches Foto angefordert',
                'text' => '#' . $row['order_number'] . ' · ' . $row['motif'],
                'url' => '/konto/auftraege/' . $row['id'],
                'due' => $row['deadline'],
            ];
        }

        $scheduleItems=(new SellerScheduleService($this->db))->items(
            (int)$s['id'],
            date('Y-m-d H:i:s',strtotime('-1 day')),
            date('Y-m-d H:i:s',strtotime('+7 days'))
        );
        $scheduleTitles=[
            'retake'=>'Nachaufnahme offen',
            'digital_submission'=>'Digitale Abgabe offen',
            'revision'=>'Digitale Revision offen',
            'damage'=>'Beschädigungsnachweis offen',
            'shipping'=>'Versandschritt offen',
        ];
        foreach($scheduleItems as $item){
            if($item['is_done']||!isset($scheduleTitles[$item['event_type']]))continue;
            $actions[]=[
                'priority'=>in_array($item['urgency'],['overdue','now'],true)?0:($item['urgency']==='today'?1:2),
                'title'=>$scheduleTitles[$item['event_type']],
                'text'=>($item['order_number']?'#'.$item['order_number'].' · ':'').$item['title'],
                'url'=>$item['url'],
                'due'=>$item['due_at'],
            ];
        }

        usort($actions, static function(array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) return $a['priority'] <=> $b['priority'];
            if ($a['due'] && $b['due']) return strtotime($a['due']) <=> strtotime($b['due']);
            return $a['due'] ? -1 : 1;
        });
        $actions = array_slice($actions, 0, 8);

        View::render($this->root, 'seller/dashboard', [
            'pageTitle' => 'Übersicht',
            'seller' => $s,
            'orders' => $orders,
            'unread' => $unread,
            'privateOffers' => $privateOffers,
            'wallet' => $wallet,
            'activeOrders' => $activeOrders,
            'actions' => $actions,
        ]);
    }

    public function orders(Request $r): void
    {
        $s = $this->auth->seller();
        $term = trim((string)$r->input('q'));
        $filter = (string)$r->input('filter','all');
        if (!in_array($filter,['all','active','payout','archive'],true)) {
            $filter='all';
        }

        $where=['o.seller_id=?'];
        $params=[(int)$s['id']];

        if ($term !== '') {
            $where[]='(o.order_number LIKE ? OR ov.title LIKE ? OR EXISTS(SELECT 1 FROM order_offer_items ooi WHERE ooi.order_id=o.id AND ooi.title LIKE ?))';
            $like='%'.$term.'%';
            $params[]=$like;
            $params[]=$like;
            $params[]=$like;
        }

        if ($filter==='active') {
            $where[]="o.status NOT IN('completed','rejected','archived','cancelled','paid')";
        } elseif ($filter==='payout') {
            $where[]="o.phase='payout' OR o.status='completed'";
        } elseif ($filter==='archive') {
            $where[]="o.status IN('rejected','archived','cancelled','paid') OR o.phase='archive'";
        }

        $sql="SELECT o.*,ov.title,
            (SELECT COUNT(*) FROM order_components oc WHERE oc.order_id=o.id) component_count,
            (SELECT COUNT(*) FROM order_offer_items ooi WHERE ooi.order_id=o.id) offer_count
            FROM orders o
            JOIN offer_versions ov ON ov.id=o.offer_version_id
            WHERE ".implode(' AND ',$where)." ORDER BY o.created_at DESC";
        $q=$this->db->prepare($sql);
        $q->execute($params);

        $countsQ=$this->db->prepare("SELECT
            COUNT(*) total,
            SUM(status NOT IN('completed','rejected','archived','cancelled','paid')) active_count,
            SUM(phase='payout' OR status='completed') payout_count,
            SUM(status IN('rejected','archived','cancelled','paid') OR phase='archive') archive_count
            FROM orders WHERE seller_id=?");
        $countsQ->execute([$s['id']]);

        View::render($this->root,'seller/orders',[
            'pageTitle'=>'Meine Aufträge',
            'orders'=>array_map(static function(array $order):array{
                if((int)($order['offer_count']??0)>1)$order['title']='Sammelauftrag · '.(int)$order['offer_count'].' Angebote';
                return $order;
            },$q->fetchAll()),
            'filterTerm'=>$term,
            'filterMode'=>$filter,
            'counts'=>$countsQ->fetch() ?: [],
        ]);
    }

    public function schedule(Request $r): void
    {
        $s = $this->auth->seller();
        $view = (string) $r->input('view', 'upcoming');
        if (!in_array($view, ['upcoming','week','month'], true)) {
            $view = 'upcoming';
        }

        $anchorRaw = trim((string) $r->input('date'));
        $anchor = $anchorRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchorRaw)
            ? new \DateTimeImmutable($anchorRaw . ' 00:00:00')
            : new \DateTimeImmutable('today');

        if ($view === 'week') {
            $from = $anchor->modify('monday this week')->setTime(0,0,0);
            $to = $from->modify('+6 days')->setTime(23,59,59);
        } elseif ($view === 'month') {
            $from = $anchor->modify('first day of this month')->setTime(0,0,0);
            $to = $anchor->modify('last day of this month')->setTime(23,59,59);
        } else {
            $from = new \DateTimeImmutable('-1 day 00:00:00');
            $to = new \DateTimeImmutable('+30 days 23:59:59');
        }

        $items = (new SellerScheduleService($this->db))->items(
            (int) $s['id'],
            $from->format('Y-m-d H:i:s'),
            $to->format('Y-m-d H:i:s')
        );

        $groups = ['overdue'=>[],'now'=>[],'today'=>[],'tomorrow'=>[],'later'=>[]];
        foreach ($items as $item) {
            if ($item['is_done']) {
                continue;
            }
            $groups[$item['urgency']][] = $item;
        }

        View::render($this->root, 'seller/schedule', [
            'pageTitle' => 'Fristen & Kalender',
            'viewMode' => $view,
            'anchorDate' => $anchor->format('Y-m-d'),
            'from' => $from,
            'to' => $to,
            'groups' => $groups,
            'allItems' => $items,
        ]);
    }

    public function wallet(): void
    {
        $s=$this->auth->seller();
        $w=$this->db->prepare('SELECT * FROM wallets WHERE seller_id=?');$w->execute([$s['id']]);$wallet=$w->fetch();
        $e=$this->db->prepare('SELECT we.* FROM wallet_entries we JOIN wallets w ON w.id=we.wallet_id WHERE w.seller_id=? ORDER BY we.created_at DESC LIMIT 100');$e->execute([$s['id']]);
        View::render($this->root,'seller/wallet',['pageTitle'=>'Wallet','wallet'=>$wallet,'entries'=>$e->fetchAll()]);
    }

    public function notifications(Request $r): void
    {
        $s=$this->auth->seller();
        $filter=(string)$r->input('filter','all');
        if(!in_array($filter,['all','unread'],true))$filter='all';
        $sql='SELECT * FROM notifications WHERE seller_id=?'.($filter==='unread'?' AND read_at IS NULL':'').' ORDER BY created_at DESC LIMIT 150';
        $q=$this->db->prepare($sql);$q->execute([$s['id']]);
        $unreadQ=$this->db->prepare('SELECT COUNT(*) FROM notifications WHERE seller_id=? AND read_at IS NULL');$unreadQ->execute([$s['id']]);
        View::render($this->root,'seller/notifications',[
            'pageTitle'=>'Benachrichtigungen',
            'notifications'=>$q->fetchAll(),
            'filterMode'=>$filter,
            'unreadCount'=>(int)$unreadQ->fetchColumn(),
        ]);
    }

    public function markNotificationRead(Request $r,array $p): void
    {
        $s=$this->auth->seller();
        $id=(int)$p['id'];
        $q=$this->db->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE id=? AND seller_id=?');
        $q->execute([$id,$s['id']]);
        $redirect=trim((string)$r->input('redirect'));
        if($redirect!=='' && str_starts_with($redirect,'/') && !str_starts_with($redirect,'//') && !str_contains($redirect,"\n") && !str_contains($redirect,"\r"))Response::redirect($redirect);
        Response::redirect('/konto/benachrichtigungen');
    }

    public function markAllNotificationsRead(): void
    {
        $s=$this->auth->seller();
        $this->db->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE seller_id=? AND read_at IS NULL')->execute([$s['id']]);
        Session::flash('success','Alle Benachrichtigungen wurden als gelesen markiert.');
        Response::redirect('/konto/benachrichtigungen');
    }
}
