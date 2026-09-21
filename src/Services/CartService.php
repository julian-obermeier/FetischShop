<?php
namespace App\Services;

use PDO;
use RuntimeException;

final class CartService
{
    public function __construct(private PDO $db) {}

    public function count(int $sellerId): int
    {
        $q=$this->db->prepare('SELECT COUNT(*) FROM cart_items WHERE seller_id=?');
        $q->execute([$sellerId]);
        return (int)$q->fetchColumn();
    }

    public function items(int $sellerId): array
    {
        $q=$this->db->prepare("SELECT ci.*,o.title offer_public_title,o.status offer_status,o.is_private,o.seller_id private_seller_id,
            o.private_offer_status,o.acceptance_deadline,o.current_version_id,ov.title,ov.description,ov.compensation,
            ov.fulfillment_model,ov.duration_value,ov.duration_unit,c.name category_name,c.is_digital
            FROM cart_items ci
            JOIN offers o ON o.id=ci.offer_id
            JOIN offer_versions ov ON ov.id=ci.offer_version_id
            JOIN categories c ON c.id=o.category_id
            WHERE ci.seller_id=?
            ORDER BY ci.created_at,ci.id");
        $q->execute([$sellerId]);
        $items=$q->fetchAll();

        foreach($items as &$item){
            $optionIds=array_values(array_unique(array_filter(array_map('intval',json_decode($item['option_ids_json']?:'[]',true)?:[]))));
            $item['option_ids']=$optionIds;
            $item['options']=[];
            $item['options_total']=0.0;

            if($optionIds){
                $marks=implode(',',array_fill(0,count($optionIds),'?'));
                $op=$this->db->prepare("SELECT id,name,description,price FROM offer_options WHERE offer_version_id=? AND is_active=1 AND id IN($marks) ORDER BY sort_order,id");
                $op->execute(array_merge([(int)$item['offer_version_id']],$optionIds));
                $item['options']=$op->fetchAll();
                foreach($item['options'] as $option)$item['options_total']+=(float)$option['price'];
            }

            $item['total']=(float)$item['compensation']+(float)$item['options_total'];
            $item['has_digital']=$this->versionHasDigital((int)$item['offer_version_id'],(int)$item['is_digital']);
            $item['invalid_reason']=$this->invalidReason($sellerId,$item);
        }
        unset($item);

        return $items;
    }

    public function add(int $sellerId,int $offerId,array $optionIds=[]): void
    {
        $this->db->beginTransaction();
        try{
            $sellerQ=$this->db->prepare("SELECT id,email_verified_at,deleted_at FROM sellers WHERE id=? FOR UPDATE");
            $sellerQ->execute([$sellerId]);
            $seller=$sellerQ->fetch();
            if(!$seller||$seller['deleted_at'])throw new RuntimeException('Verkäuferinnenkonto ist nicht verfügbar.');
            if(empty($seller['email_verified_at']))throw new RuntimeException('Bitte bestätige zuerst deine E-Mail-Adresse.');

            $q=$this->db->prepare("SELECT o.*,ov.id version_id,ov.title,ov.compensation,c.is_digital
                FROM offers o
                JOIN offer_versions ov ON ov.id=o.current_version_id
                JOIN categories c ON c.id=o.category_id
                WHERE o.id=? AND o.status='active' FOR UPDATE");
            $q->execute([$offerId]);
            $offer=$q->fetch();
            if(!$offer)throw new RuntimeException('Dieses Angebot ist nicht mehr verfügbar.');
            if((int)$offer['is_private']===1 && (int)$offer['seller_id']!==$sellerId)throw new RuntimeException('Dieses Privatangebot ist nicht für dein Konto bestimmt.');
            if((int)$offer['is_private']===1 && (string)($offer['private_offer_status']??'pending')!=='pending')throw new RuntimeException('Dieses Privatangebot ist nicht mehr verfügbar.');
            if($offer['acceptance_deadline']&&strtotime((string)$offer['acceptance_deadline'])<time())throw new RuntimeException('Die Annahmefrist dieses Angebots ist abgelaufen.');

            $optionIds=array_values(array_unique(array_filter(array_map('intval',$optionIds))));
            if($optionIds){
                $marks=implode(',',array_fill(0,count($optionIds),'?'));
                $op=$this->db->prepare("SELECT COUNT(*) FROM offer_options WHERE offer_version_id=? AND is_active=1 AND id IN($marks)");
                $op->execute(array_merge([(int)$offer['version_id']],$optionIds));
                if((int)$op->fetchColumn()!==count($optionIds))throw new RuntimeException('Mindestens eine ausgewählte Option ist nicht mehr verfügbar.');
            }

            $newCategories=$this->categoryIdsForVersion((int)$offer['version_id'],(int)$offer['category_id']);
            foreach($newCategories as $categoryId){
                $active=$this->db->prepare("SELECT ord.order_number FROM orders ord JOIN order_components oc ON oc.order_id=ord.id
                    WHERE ord.seller_id=? AND oc.category_id=? AND ord.status NOT IN('rejected','cancelled','paid','archived') LIMIT 1");
                $active->execute([$sellerId,$categoryId]);
                if($number=$active->fetchColumn())throw new RuntimeException('Diese Kategorie ist bereits durch deinen aktiven Auftrag #'.$number.' belegt.');
            }

            $existing=$this->db->prepare('SELECT id,offer_id,offer_version_id FROM cart_items WHERE seller_id=? FOR UPDATE');
            $existing->execute([$sellerId]);
            foreach($existing->fetchAll() as $row){
                if((int)$row['offer_id']===$offerId)continue;
                $categories=$this->categoryIdsForVersion((int)$row['offer_version_id'],null);
                if(array_intersect($newCategories,$categories)){
                    throw new RuntimeException('Im Warenkorb befindet sich bereits ein Angebot derselben Kategorie.');
                }
            }

            $this->db->prepare("INSERT INTO cart_items(seller_id,offer_id,offer_version_id,option_ids_json,created_at,updated_at)
                VALUES(?,?,?,?,NOW(),NOW())
                ON DUPLICATE KEY UPDATE offer_version_id=VALUES(offer_version_id),option_ids_json=VALUES(option_ids_json),updated_at=NOW()")
                ->execute([$sellerId,$offerId,$offer['version_id'],json_encode($optionIds,JSON_UNESCAPED_UNICODE)]);

            $this->db->commit();
        }catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
    }

    public function remove(int $sellerId,int $cartItemId): void
    {
        $q=$this->db->prepare('DELETE FROM cart_items WHERE id=? AND seller_id=?');
        $q->execute([$cartItemId,$sellerId]);
    }

    private function categoryIdsForVersion(int $versionId,?int $fallbackCategoryId): array
    {
        $q=$this->db->prepare('SELECT DISTINCT category_id FROM offer_components WHERE offer_version_id=?');
        $q->execute([$versionId]);
        $ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if(!$ids && $fallbackCategoryId!==null)$ids[]=$fallbackCategoryId;
        return array_values(array_unique($ids));
    }

    private function versionHasDigital(int $versionId,int $fallbackDigital): bool
    {
        $q=$this->db->prepare("SELECT COUNT(*) FROM offer_components WHERE offer_version_id=? AND component_type='digital'");
        $q->execute([$versionId]);
        return (int)$q->fetchColumn()>0 || $fallbackDigital===1;
    }

    private function invalidReason(int $sellerId,array $item): ?string
    {
        if((string)$item['offer_status']!=='active')return 'Angebot ist nicht mehr aktiv.';
        if((int)$item['current_version_id']!==(int)$item['offer_version_id'])return 'Angebot wurde geändert. Bitte neu hinzufügen.';
        if((int)$item['is_private']===1 && (int)$item['private_seller_id']!==$sellerId)return 'Privatangebot gehört nicht zu diesem Konto.';
        if((int)$item['is_private']===1 && (string)($item['private_offer_status']??'pending')!=='pending')return 'Privatangebot ist nicht mehr verfügbar.';
        if($item['acceptance_deadline']&&strtotime((string)$item['acceptance_deadline'])<time())return 'Annahmefrist ist abgelaufen.';
        if(count($item['options'])!==count($item['option_ids']))return 'Eine ausgewählte Option ist nicht mehr verfügbar.';
        return null;
    }
}
