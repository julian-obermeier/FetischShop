<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use PDO;
use DateTimeImmutable;
use DateInterval;
use RuntimeException;

final class OperationsController
{
    public function __construct(private string $root, private PDO $db) {}

    public function decisions(Request $r): void
    {
        $items = [];

        $this->append($items, $this->db->query("SELECT e.id source_id,e.order_id,'Vorabkontrolle' type,CONCAT('Nachweis #',e.id) title,e.created_at due_at,'pending' status FROM evidences e WHERE e.evidence_type='precheck' AND e.review_status='pending'")->fetchAll());
        $this->append($items, $this->db->query("SELECT e.id source_id,e.order_id,'Nachweis' type,CONCAT('Nachweis #',e.id) title,e.created_at due_at,'pending' status FROM evidences e WHERE e.evidence_type<>'precheck' AND e.review_status='pending'")->fetchAll());
        $this->append($items, $this->db->query("SELECT v.id source_id,v.order_id,'Verstoß' type,v.description title,v.created_at due_at,v.status FROM violations v WHERE v.status IN('open','reviewed')")->fetchAll());
        $this->append($items, $this->db->query("SELECT d.id source_id,d.order_id,'Beschädigung' type,d.reason title,d.created_at due_at,d.status FROM damage_cases d WHERE d.status IN('reported','evidence_requested','under_review')")->fetchAll());
        $this->append($items, $this->db->query("SELECT rr.id source_id,oc.order_id,'Revision' type,CONCAT('Revisionsrunde ',rr.round_no) title,rr.deadline due_at,rr.status FROM revision_rounds rr JOIN digital_components dc ON dc.id=rr.digital_component_id JOIN order_components oc ON oc.id=dc.order_component_id WHERE rr.status IN('open','submitted')")->fetchAll());
        $this->append($items, $this->db->query("SELECT s.id source_id,s.order_id,'Wareneingang' type,CONCAT('Sendung ',COALESCE(s.tracking_number,'ohne Tracking')) title,s.shipped_at due_at,s.status FROM shipments s WHERE s.status='shipped'")->fetchAll());
        $this->append($items, $this->db->query("SELECT o.id source_id,o.id order_id,'Abschlussprüfung' type,CONCAT('Auftrag #',o.order_number) title,o.updated_at due_at,o.status FROM orders o LEFT JOIN final_reviews fr ON fr.order_id=o.id WHERE o.phase='review' AND fr.id IS NULL")->fetchAll());

        usort($items, static fn(array $a, array $b): int => strcmp((string)($a['due_at'] ?? ''), (string)($b['due_at'] ?? '')));

        $type = trim((string) $r->input('type'));
        if ($type !== '') {
            $items = array_values(array_filter($items, static fn(array $x): bool => $x['type'] === $type));
        }

        View::render($this->root, 'admin/decisions', [
            'pageTitle' => 'Entscheidungsübersicht',
            'items' => $items,
            'filterType' => $type,
        ]);
    }

    public function deadlines(Request $r): void
    {
        $items = [];
        $this->append($items, $this->db->query("SELECT od.order_id,ew.id source_id,'Nachweisfenster' type,CONCAT(ew.name,' – Nachweis') title,ew.ends_at due_at,ew.grace_ends_at grace_at,ew.status FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id WHERE ew.status='open'")->fetchAll());
        $this->append($items, $this->db->query("SELECT t.order_id,te.id source_id,'Zusatzaufgabe' type,t.title,te.due_at,te.grace_ends_at grace_at,te.review_status status FROM task_executions te JOIN tasks t ON t.id=te.task_id WHERE te.review_status IN('open','pending','overdue')")->fetchAll());
        $this->append($items, $this->db->query("SELECT sr.order_id,sr.id source_id,'Spontanfoto' type,sr.motif title,sr.deadline due_at,sr.grace_ends_at grace_at,sr.status FROM spontaneous_requests sr WHERE sr.status NOT IN('uploaded','completed','cancelled')")->fetchAll());
        $this->append($items, $this->db->query("SELECT dc.order_id,dr.id source_id,'Beschädigungsnachforderung' type,dr.instructions title,dr.deadline due_at,dr.grace_ends_at grace_at,dr.status FROM damage_evidence_requests dr JOIN damage_cases dc ON dc.id=dr.damage_case_id WHERE dr.status='requested'")->fetchAll());
        $this->append($items, $this->db->query("SELECT oc.order_id,rr.id source_id,'Revision' type,CONCAT('Revision ',rr.round_no) title,rr.deadline due_at,rr.grace_ends_at grace_at,rr.status FROM revision_rounds rr JOIN digital_components dc ON dc.id=rr.digital_component_id JOIN order_components oc ON oc.id=dc.order_component_id WHERE rr.status IN('open','submitted')")->fetchAll());
        $this->append($items, $this->db->query("SELECT o.id order_id,o.id source_id,'Privatangebot' type,o.title,o.acceptance_deadline due_at,o.acceptance_deadline grace_at,o.private_offer_status status FROM offers o WHERE o.is_private=1 AND o.private_offer_status='pending' AND o.acceptance_deadline IS NOT NULL")->fetchAll());

        $now = time();
        foreach ($items as &$item) {
            $due = strtotime((string) $item['due_at']);
            $item['bucket'] = $due < $now ? 'overdue' : ($due <= $now + 86400 ? 'today' : ($due <= $now + 172800 ? 'tomorrow' : 'later'));
            $left = $due - $now;
            $item['escalation'] = $left < 0 ? 'überfällig' : ($left <= 900 ? 'kritisch' : ($left <= 3600 ? 'bald fällig' : 'normal'));
        }
        unset($item);

        $bucket = trim((string) $r->input('bucket'));
        $type = trim((string) $r->input('type'));
        if ($bucket !== '') {
            $items = array_values(array_filter($items, static fn(array $x): bool => $x['bucket'] === $bucket));
        }
        if ($type !== '') {
            $items = array_values(array_filter($items, static fn(array $x): bool => $x['type'] === $type));
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string) $a['due_at'], (string) $b['due_at']));

        View::render($this->root, 'admin/deadlines', [
            'pageTitle' => 'Fristenübersicht',
            'items' => $items,
            'filterBucket' => $bucket,
            'filterType' => $type,
        ]);
    }

    public function calendar(Request $r): void
    {
        $mode = (string) $r->input('mode', 'month');
        if (!in_array($mode, ['day', 'week', 'month'], true)) {
            $mode = 'month';
        }

        try {
            $anchor = new DateTimeImmutable((string) $r->input('date', 'today'));
        } catch (\Throwable) {
            $anchor = new DateTimeImmutable('today');
        }

        if ($mode === 'day') {
            $from = $anchor->setTime(0, 0);
            $to = $from->modify('+1 day');
        } elseif ($mode === 'week') {
            $from = $anchor->modify('monday this week')->setTime(0, 0);
            $to = $from->modify('+7 days');
        } else {
            $from = $anchor->modify('first day of this month')->setTime(0, 0);
            $to = $from->modify('first day of next month')->setTime(0, 0);
        }

        $events = [];
        $params = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

        $q = $this->db->prepare("SELECT o.id order_id,'Auftragsstart' type,CONCAT('#',o.order_number,' Start') title,o.started_at starts_at,NULL ends_at FROM orders o WHERE o.started_at>=? AND o.started_at<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT od.order_id,'Nachweisfenster' type,CONCAT('#',o.order_number,' · ',ew.name) title,ew.starts_at,ew.ends_at FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id JOIN orders o ON o.id=od.order_id WHERE ew.starts_at>=? AND ew.starts_at<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT t.order_id,'Aufgabe' type,CONCAT('#',o.order_number,' · ',t.title) title,te.due_at starts_at,NULL ends_at FROM task_executions te JOIN tasks t ON t.id=te.task_id JOIN orders o ON o.id=t.order_id WHERE te.due_at>=? AND te.due_at<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT oc.order_id,'Revision' type,CONCAT('#',o.order_number,' · Revision ',rr.round_no) title,rr.deadline starts_at,NULL ends_at FROM revision_rounds rr JOIN digital_components dc ON dc.id=rr.digital_component_id JOIN order_components oc ON oc.id=dc.order_component_id JOIN orders o ON o.id=oc.order_id WHERE rr.deadline>=? AND rr.deadline<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT s.order_id,'Versand' type,CONCAT('#',o.order_number,' · Versand') title,s.shipped_at starts_at,NULL ends_at FROM shipments s JOIN orders o ON o.id=s.order_id WHERE s.shipped_at>=? AND s.shipped_at<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        usort($events, static fn(array $a, array $b): int => strcmp((string) $a['starts_at'], (string) $b['starts_at']));

        View::render($this->root, 'admin/calendar', [
            'pageTitle' => 'Kalender',
            'events' => $events,
            'mode' => $mode,
            'anchor' => $anchor,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function search(Request $r): void
    {
        $term = trim((string) $r->input('q'));
        $results = [];

        if (mb_strlen($term) >= 2) {
            $like = '%' . $term . '%';

            $q = $this->db->prepare("SELECT id,'seller' result_type,CONCAT(first_name,' ',last_name) title,CONCAT(email,' · ',phone) subtitle,CONCAT('/admin/verkaeuferinnen/',id) url FROM sellers WHERE deleted_at IS NULL AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?) LIMIT 20");
            $q->execute([$like, $like, $like, $like]);
            $this->append($results, $q->fetchAll());

            $q = $this->db->prepare("SELECT id,'order' result_type,CONCAT('Auftrag #',order_number) title,CONCAT(status,' · ',phase) subtitle,CONCAT('/admin/auftraege/',id) url FROM orders WHERE order_number LIKE ? LIMIT 20");
            $q->execute([$like]);
            $this->append($results, $q->fetchAll());

            $q = $this->db->prepare("SELECT id,'offer' result_type,title,CONCAT(status,' · Angebot') subtitle,CONCAT('/admin/angebote/',id,'/bearbeiten') url FROM offers WHERE title LIKE ? LIMIT 20");
            $q->execute([$like]);
            $this->append($results, $q->fetchAll());

            $q = $this->db->prepare("SELECT s.order_id id,'shipment' result_type,CONCAT('Tracking ',s.tracking_number) title,CONCAT('Auftrag #',o.order_number) subtitle,CONCAT('/admin/auftraege/',s.order_id) url FROM shipments s JOIN orders o ON o.id=s.order_id WHERE s.tracking_number LIKE ? LIMIT 20");
            $q->execute([$like]);
            $this->append($results, $q->fetchAll());

            $q = $this->db->prepare("SELECT pr.id,'payout' result_type,CONCAT('Auszahlung ',FORMAT(pr.net_amount,2),' EUR') title,CONCAT(se.email,' · ',pr.status) subtitle,'/admin/auszahlungen' url FROM payout_requests pr JOIN sellers se ON se.id=pr.seller_id WHERE se.email LIKE ? OR CAST(pr.id AS CHAR) LIKE ? LIMIT 20");
            $q->execute([$like, $like]);
            $this->append($results, $q->fetchAll());
        }

        View::render($this->root, 'admin/search', [
            'pageTitle' => 'Globale Suche',
            'term' => $term,
            'results' => $results,
        ]);
    }

    public function archive(): void
    {
        $orders = $this->db->query("SELECT o.*,s.first_name,s.last_name,ov.title,fr.decision,fr.approved_amount FROM orders o JOIN sellers s ON s.id=o.seller_id JOIN offer_versions ov ON ov.id=o.offer_version_id LEFT JOIN final_reviews fr ON fr.order_id=o.id WHERE o.archived_at IS NOT NULL OR o.status='rejected' ORDER BY COALESCE(o.archived_at,o.finished_at,o.updated_at) DESC")->fetchAll();
        View::render($this->root, 'admin/archive', ['pageTitle' => 'Archiv', 'orders' => $orders]);
    }

    public function settings(): void
    {
        $rows = $this->db->query('SELECT setting_key,setting_value FROM settings ORDER BY setting_key')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $outages = $this->db->query('SELECT * FROM platform_outages ORDER BY starts_at DESC LIMIT 50')->fetchAll();

        View::render($this->root, 'admin/settings', [
            'pageTitle' => 'Einstellungen',
            'settings' => $settings,
            'outages' => $outages,
        ]);
    }

    public function saveSettings(Request $r): void
    {
        $allowed = [
            'platform_name','contact_email','payout_minimum','payout_bank_enabled','payout_paypal_enabled',
            'payout_bank_fee_type','payout_bank_fee_value','payout_paypal_fee_type','payout_paypal_fee_value',
            'payout_days','upload_image_mb','upload_digital_mb','evidence_reminder_60','evidence_reminder_15',
            'escalation_soon_minutes','escalation_critical_minutes'
        ];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $r->body)) {
                continue;
            }
            $value = is_array($r->body[$key]) ? json_encode($r->body[$key], JSON_UNESCAPED_UNICODE) : trim((string) $r->body[$key]);
            $q = $this->db->prepare('INSERT INTO settings(setting_key,setting_value,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');
            $q->execute([$key, $value]);
        }

        Session::flash('success', 'Globale Einstellungen gespeichert.');
        Response::redirect('/admin/einstellungen');
    }

    public function createOutage(Request $r): void
    {
        try {
            $start = new DateTimeImmutable((string) $r->input('starts_at'));
            $end = new DateTimeImmutable((string) $r->input('ends_at'));
            if ($end <= $start) {
                throw new RuntimeException('Das Ende des Ausfalls muss nach dem Beginn liegen.');
            }
            $reason = trim((string) $r->input('reason'));
            $seconds = $end->getTimestamp() - $start->getTimestamp();

            $this->db->beginTransaction();
            $this->db->prepare('INSERT INTO platform_outages(starts_at,ends_at,reason,created_at) VALUES(?,?,?,NOW())')->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $reason ?: null]);

            $minutes = (int) ceil($seconds / 60);
            $startSql = $start->format('Y-m-d H:i:s');

            $this->db->prepare("UPDATE evidence_windows SET starts_at=DATE_ADD(starts_at,INTERVAL ? MINUTE),ends_at=DATE_ADD(ends_at,INTERVAL ? MINUTE),grace_ends_at=DATE_ADD(grace_ends_at,INTERVAL ? MINUTE) WHERE grace_ends_at>=? AND status='open'")->execute([$minutes,$minutes,$minutes,$startSql]);
            $this->db->prepare("UPDATE task_executions SET due_at=DATE_ADD(due_at,INTERVAL ? MINUTE),grace_ends_at=DATE_ADD(grace_ends_at,INTERVAL ? MINUTE) WHERE grace_ends_at>=? AND review_status IN('open','pending')")->execute([$minutes,$minutes,$startSql]);
            $this->db->prepare("UPDATE spontaneous_requests SET deadline=DATE_ADD(deadline,INTERVAL ? MINUTE),grace_ends_at=DATE_ADD(grace_ends_at,INTERVAL ? MINUTE) WHERE grace_ends_at>=? AND status IN('requested','seen','confirmed')")->execute([$minutes,$minutes,$startSql]);
            $this->db->prepare("UPDATE damage_evidence_requests SET deadline=DATE_ADD(deadline,INTERVAL ? MINUTE),grace_ends_at=DATE_ADD(grace_ends_at,INTERVAL ? MINUTE) WHERE grace_ends_at>=? AND status='requested'")->execute([$minutes,$minutes,$startSql]);
            $this->db->prepare("UPDATE revision_rounds SET deadline=DATE_ADD(deadline,INTERVAL ? MINUTE),grace_ends_at=DATE_ADD(grace_ends_at,INTERVAL ? MINUTE) WHERE grace_ends_at>=? AND status IN('open','submitted')")->execute([$minutes,$minutes,$startSql]);
            $this->db->prepare("UPDATE offers SET acceptance_deadline=DATE_ADD(acceptance_deadline,INTERVAL ? MINUTE) WHERE is_private=1 AND private_offer_status='pending' AND acceptance_deadline>=?")->execute([$minutes,$startSql]);

            $this->db->prepare("INSERT INTO system_events(event_type,actor_type,payload_json,created_at) VALUES('platform_outage','admin',?,NOW())")->execute([json_encode(['starts_at'=>$startSql,'ends_at'=>$end->format('Y-m-d H:i:s'),'reason'=>$reason,'shift_minutes'=>$minutes],JSON_UNESCAPED_UNICODE)]);

            $this->db->commit();
            Session::flash('success', 'Systemausfall dokumentiert; aktive Fristen wurden um ' . $minutes . ' Minuten verschoben.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/admin/einstellungen');
    }

    private function append(array &$target, array $rows): void
    {
        foreach ($rows as $row) {
            $target[] = $row;
        }
    }
}
