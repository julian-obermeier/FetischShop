<?php
namespace App\Services;

use PDO;

final class SellerScheduleService
{
    public function __construct(private PDO $db) {}

    public function items(int $sellerId, string $from, string $to): array
    {
        $items = [];

        $q = $this->db->prepare("SELECT o.id order_id,o.order_number,ew.id source_id,'evidence' event_type,
            CONCAT('Nachweis: ',ew.name) title,ew.starts_at starts_at,ew.ends_at due_at,ew.grace_ends_at grace_at,
            ew.status source_status
            FROM evidence_windows ew
            JOIN order_days od ON od.id=ew.order_day_id
            JOIN orders o ON o.id=od.order_id
            WHERE o.seller_id=? AND ew.grace_ends_at>=? AND ew.starts_at<=?");
        $q->execute([$sellerId,$from,$to]);
        $items = array_merge($items,$q->fetchAll());

        $q = $this->db->prepare("SELECT o.id order_id,o.order_number,e.id source_id,'retake' event_type,
            'Angeforderte Nachaufnahme' title,e.created_at starts_at,e.retake_deadline due_at,e.retake_grace_ends_at grace_at,
            e.review_status source_status
            FROM evidences e
            JOIN orders o ON o.id=e.order_id
            WHERE o.seller_id=? AND e.review_status='rejected' AND e.resolved_by_evidence_id IS NULL
            AND e.retake_deadline IS NOT NULL AND e.retake_grace_ends_at>=? AND e.retake_deadline<=?");
        $q->execute([$sellerId,$from,$to]);
        $items = array_merge($items,$q->fetchAll());

        $q = $this->db->prepare("SELECT o.id order_id,o.order_number,dc.id source_id,'digital_submission' event_type,
            CONCAT('Digitale Abgabe: ',oc.title) title,dc.created_at starts_at,dc.deadline due_at,DATE_ADD(dc.deadline,INTERVAL 1 HOUR) grace_at,
            dc.status source_status
            FROM digital_components dc
            JOIN order_components oc ON oc.id=dc.order_component_id
            JOIN orders o ON o.id=oc.order_id
            WHERE o.seller_id=? AND dc.deadline IS NOT NULL AND dc.status IN('open','draft','running')
            AND DATE_ADD(dc.deadline,INTERVAL 1 HOUR)>=? AND dc.deadline<=?");
        $q->execute([$sellerId,$from,$to]);
        $items = array_merge($items,$q->fetchAll());

        $q = $this->db->prepare("SELECT o.id order_id,o.order_number,te.id source_id,'task' event_type,
            CONCAT('Zusatzaufgabe: ',t.title) title,te.created_at starts_at,te.due_at due_at,te.grace_ends_at grace_at,
            te.review_status source_status
            FROM task_executions te
            JOIN tasks t ON t.id=te.task_id
            JOIN orders o ON o.id=t.order_id
            WHERE o.seller_id=? AND te.grace_ends_at>=? AND te.due_at<=?");
        $q->execute([$sellerId,$from,$to]);
        $items = array_merge($items,$q->fetchAll());

        $q = $this->db->prepare("SELECT o.id order_id,o.order_number,sr.id source_id,'spontaneous' event_type,
            CONCAT('Zusätzliches Foto: ',sr.motif) title,sr.created_at starts_at,sr.deadline due_at,sr.grace_ends_at grace_at,
            sr.status source_status
            FROM spontaneous_requests sr
            JOIN orders o ON o.id=sr.order_id
            WHERE o.seller_id=? AND sr.grace_ends_at>=? AND sr.deadline<=?");
        $q->execute([$sellerId,$from,$to]);
        $items = array_merge($items,$q->fetchAll());

        $q = $this->db->prepare("SELECT o.id order_id,o.order_number,rr.id source_id,'revision' event_type,
            CONCAT('Digitale Revision – Runde ',rr.round_no) title,rr.created_at starts_at,rr.deadline due_at,rr.grace_ends_at grace_at,
            rr.status source_status
            FROM revision_rounds rr
            JOIN digital_components dc ON dc.id=rr.digital_component_id
            JOIN order_components oc ON oc.id=dc.order_component_id
            JOIN orders o ON o.id=oc.order_id
            WHERE o.seller_id=? AND rr.grace_ends_at>=? AND rr.deadline<=?");
        $q->execute([$sellerId,$from,$to]);
        $items = array_merge($items,$q->fetchAll());

        $q = $this->db->prepare("SELECT o.id order_id,o.order_number,dr.id source_id,'damage' event_type,
            'Nachweis zur Beschädigung' title,dr.created_at starts_at,dr.deadline due_at,dr.grace_ends_at grace_at,
            dr.status source_status
            FROM damage_evidence_requests dr
            JOIN damage_cases dc ON dc.id=dr.damage_case_id
            JOIN orders o ON o.id=dc.order_id
            WHERE o.seller_id=? AND dr.grace_ends_at>=? AND dr.deadline<=?");
        $q->execute([$sellerId,$from,$to]);
        $items = array_merge($items,$q->fetchAll());

        $q = $this->db->prepare("SELECT o.id order_id,o.order_number,ss.id source_id,'shipping' event_type,
            CONCAT('Versand: ',ss.title) title,COALESCE(sw.started_at,ss.deadline) starts_at,ss.deadline due_at,
            CASE WHEN ss.deadline IS NULL THEN NULL ELSE DATE_ADD(ss.deadline,INTERVAL 1 HOUR) END grace_at,
            ss.status source_status
            FROM shipping_steps ss
            JOIN shipping_workflows sw ON sw.id=ss.shipping_workflow_id
            JOIN orders o ON o.id=sw.order_id
            WHERE o.seller_id=? AND ss.deadline IS NOT NULL AND DATE_ADD(ss.deadline,INTERVAL 1 HOUR)>=? AND ss.deadline<=?");
        $q->execute([$sellerId,$from,$to]);
        $items = array_merge($items,$q->fetchAll());

        $q = $this->db->prepare("SELECT NULL order_id,NULL order_number,o.id source_id,'private_offer' event_type,
            CONCAT('Privatangebot: ',o.title) title,o.created_at starts_at,o.acceptance_deadline due_at,o.acceptance_deadline grace_at,
            o.private_offer_status source_status
            FROM offers o
            WHERE o.seller_id=? AND o.is_private=1 AND o.status='active' AND o.acceptance_deadline IS NOT NULL
            AND o.acceptance_deadline>=? AND o.acceptance_deadline<=?");
        $q->execute([$sellerId,$from,$to]);
        $items = array_merge($items,$q->fetchAll());

        $now = time();
        foreach ($items as &$item) {
            $dueTs = !empty($item['due_at']) ? strtotime((string)$item['due_at']) : null;
            $graceTs = !empty($item['grace_at']) ? strtotime((string)$item['grace_at']) : null;
            $item['url'] = $item['event_type']==='private_offer'
                ? '/angebote/'.(int)$item['source_id']
                : '/konto/auftraege/'.(int)$item['order_id'];
            $item['urgency'] = 'later';
            if ($graceTs !== null && $graceTs < $now) {
                $item['urgency'] = 'overdue';
            } elseif ($dueTs !== null && $dueTs <= $now) {
                $item['urgency'] = 'now';
            } elseif ($dueTs !== null && date('Y-m-d',$dueTs) === date('Y-m-d')) {
                $item['urgency'] = 'today';
            } elseif ($dueTs !== null && date('Y-m-d',$dueTs) === date('Y-m-d',strtotime('+1 day'))) {
                $item['urgency'] = 'tomorrow';
            }

            $item['is_done'] = in_array((string)$item['source_status'],[
                'completed','accepted','submitted','done','uploaded','received','shipped','declined','expired'
            ],true);
        }
        unset($item);

        usort($items, static function(array $a,array $b): int {
            $aTime = strtotime((string)($a['due_at'] ?: $a['starts_at']));
            $bTime = strtotime((string)($b['due_at'] ?: $b['starts_at']));
            return $aTime <=> $bTime;
        });

        return $items;
    }
}
