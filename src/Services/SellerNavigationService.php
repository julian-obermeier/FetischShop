<?php
namespace App\Services;

use PDO;

final class SellerNavigationService
{
    public function __construct(private PDO $db) {}

    public function counts(int $sellerId): array
    {
        $unreadQ=$this->db->prepare('SELECT COUNT(*) FROM notifications WHERE seller_id=? AND read_at IS NULL');
        $unreadQ->execute([$sellerId]);
        $unread=(int)$unreadQ->fetchColumn();

        $urgentSql="SELECT SUM(cnt) FROM (
            SELECT COUNT(*) cnt FROM evidence_windows ew
            JOIN order_days od ON od.id=ew.order_day_id
            JOIN orders o ON o.id=od.order_id
            WHERE o.seller_id=? AND ew.status='open'
              AND ew.grace_ends_at>=NOW()
              AND ew.ends_at<=DATE_ADD(NOW(),INTERVAL 24 HOUR)

            UNION ALL

            SELECT COUNT(*) FROM evidences e
            JOIN orders o ON o.id=e.order_id
            WHERE o.seller_id=? AND e.review_status='rejected'
              AND e.resolved_by_evidence_id IS NULL
              AND e.retake_deadline IS NOT NULL
              AND e.retake_grace_ends_at>=NOW()
              AND e.retake_deadline<=DATE_ADD(NOW(),INTERVAL 24 HOUR)

            UNION ALL

            SELECT COUNT(*) FROM digital_components dc
            JOIN order_components oc ON oc.id=dc.order_component_id
            JOIN orders o ON o.id=oc.order_id
            WHERE o.seller_id=? AND dc.status IN('open','draft','running')
              AND dc.deadline IS NOT NULL
              AND DATE_ADD(dc.deadline,INTERVAL 1 HOUR)>=NOW()
              AND dc.deadline<=DATE_ADD(NOW(),INTERVAL 24 HOUR)

            UNION ALL

            SELECT COUNT(*) FROM task_executions te
            JOIN tasks t ON t.id=te.task_id
            JOIN orders o ON o.id=t.order_id
            WHERE o.seller_id=? AND te.submitted_at IS NULL
              AND te.grace_ends_at>=NOW()
              AND te.due_at<=DATE_ADD(NOW(),INTERVAL 24 HOUR)

            UNION ALL

            SELECT COUNT(*) FROM spontaneous_requests sr
            JOIN orders o ON o.id=sr.order_id
            WHERE o.seller_id=?
              AND sr.status NOT IN('uploaded','completed','cancelled')
              AND sr.grace_ends_at>=NOW()
              AND sr.deadline<=DATE_ADD(NOW(),INTERVAL 24 HOUR)

            UNION ALL

            SELECT COUNT(*) FROM revision_rounds rr
            JOIN digital_components dc ON dc.id=rr.digital_component_id
            JOIN order_components oc ON oc.id=dc.order_component_id
            JOIN orders o ON o.id=oc.order_id
            WHERE o.seller_id=? AND rr.status='open'
              AND rr.grace_ends_at>=NOW()
              AND rr.deadline<=DATE_ADD(NOW(),INTERVAL 24 HOUR)

            UNION ALL

            SELECT COUNT(*) FROM damage_evidence_requests dr
            JOIN damage_cases d ON d.id=dr.damage_case_id
            JOIN orders o ON o.id=d.order_id
            WHERE o.seller_id=? AND dr.status='requested'
              AND dr.grace_ends_at>=NOW()
              AND dr.deadline<=DATE_ADD(NOW(),INTERVAL 24 HOUR)

            UNION ALL

            SELECT COUNT(*) FROM shipping_steps ss
            JOIN shipping_workflows sw ON sw.id=ss.shipping_workflow_id
            JOIN orders o ON o.id=sw.order_id
            WHERE o.seller_id=? AND sw.status='active'
              AND ss.status='pending'
              AND ss.deadline IS NOT NULL
              AND DATE_ADD(ss.deadline,INTERVAL 1 HOUR)>=NOW()
              AND ss.deadline<=DATE_ADD(NOW(),INTERVAL 24 HOUR)
        ) urgent";

        $urgentQ=$this->db->prepare($urgentSql);
        $urgentQ->execute(array_fill(0,8,$sellerId));

        return [
            'unread'=>$unread,
            'urgent'=>(int)($urgentQ->fetchColumn()?:0),
        ];
    }
}
