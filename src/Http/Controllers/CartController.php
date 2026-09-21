<?php
namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\CartService;
use App\Services\OrderService;
use PDO;
use RuntimeException;

final class CartController
{
    public function __construct(private string $root,private PDO $db,private Auth $auth) {}

    public function index(): void
    {
        $seller=$this->auth->seller();
        $items=(new CartService($this->db))->items((int)$seller['id']);
        $total=0.0;
        $hasDigital=false;
        $hasInvalid=false;
        foreach($items as $item){
            $total+=(float)$item['total'];
            $hasDigital=$hasDigital||!empty($item['has_digital']);
            $hasInvalid=$hasInvalid||!empty($item['invalid_reason']);
        }

        View::render($this->root,'seller/cart',[
            'pageTitle'=>'Warenkorb',
            'items'=>$items,
            'cartTotal'=>$total,
            'hasDigital'=>$hasDigital,
            'hasInvalid'=>$hasInvalid,
        ]);
    }

    public function add(Request $r,array $p): void
    {
        $seller=$this->auth->seller();
        $options=$r->input('options',[]);
        if(!is_array($options))$options=[];

        try{
            (new CartService($this->db))->add((int)$seller['id'],(int)$p['id'],$options);
            Session::flash('success','Angebot wurde in den Warenkorb gelegt.');
            Response::redirect('/konto/warenkorb');
        }catch(\Throwable $e){
            Session::flash('error',$e->getMessage());
            Response::redirect('/angebote/'.(int)$p['id']);
        }
    }

    public function remove(Request $r,array $p): void
    {
        $seller=$this->auth->seller();
        (new CartService($this->db))->remove((int)$seller['id'],(int)$p['id']);
        Session::flash('success','Angebot wurde aus dem Warenkorb entfernt.');
        Response::redirect('/konto/warenkorb');
    }

    public function checkout(Request $r): void
    {
        $seller=$this->auth->seller();

        try{
            foreach([
                'adult_confirmation'=>'Bitte bestätige deine Volljährigkeit.',
                'own_goods_confirmation'=>'Bitte bestätige, dass Artikel und Inhalte von dir selbst stammen.',
                'no_third_parties_confirmation'=>'Bitte bestätige, dass keine unzulässigen Dritten beteiligt sind.',
                'summary_confirmation'=>'Bitte bestätige die Zusammenfassung des Warenkorbs.',
            ] as $field=>$message){
                if(!$r->input($field))throw new RuntimeException($message);
            }

            $items=(new CartService($this->db))->items((int)$seller['id']);
            if(!$items)throw new RuntimeException('Dein Warenkorb ist leer.');
            foreach($items as $item){
                if(!empty($item['invalid_reason']))throw new RuntimeException('„'.$item['title'].'“: '.$item['invalid_reason']);
            }

            $cartItems=array_map(static fn(array $item)=>[
                'offer_id'=>(int)$item['offer_id'],
                'offer_version_id'=>(int)$item['offer_version_id'],
                'option_ids'=>$item['option_ids'],
            ],$items);

            $consents=[
                'terms_version'=>'2026-09-20',
                'adult_confirmed'=>true,
                'own_goods_confirmed'=>true,
                'no_third_parties_confirmed'=>true,
                'summary_confirmed'=>true,
                'ip_address'=>$r->server['REMOTE_ADDR']??null,
                'user_agent'=>$r->server['HTTP_USER_AGENT']??null,
            ];

            $orderId=(new OrderService($this->db,$this->root))->acceptCart(
                (int)$seller['id'],
                $cartItems,
                (bool)$r->input('rights_acceptance'),
                $consents
            );

            Session::flash('success',count($items)>1
                ? 'Deine Angebote wurden zu einem gemeinsamen Auftrag zusammengeführt.'
                : 'Der Auftrag wurde erfolgreich angelegt.');
            Response::redirect('/konto/auftraege/'.$orderId);
        }catch(\Throwable $e){
            Session::flash('error',$e->getMessage());
            Response::redirect('/konto/warenkorb');
        }
    }
}
