<?php
namespace App\Services;

use PDO;

final class SchedulerService
{
    public function __construct(private PDO $db, private ?string $root = null) {}

    public function run(): array
    {
        if (!$this->acquire('global-cron', 300)) {
            return ['locked' => true];
        }

        $result = [
            'evidence_violations' => 0,
            'task_violations' => 0,
            'spontaneous_violations' => 0,
            'damage_violations' => 0,
            'revision_violations' => 0,
            'shipping_violations' => 0,
            'expired_private_offers' => 0,
            'reminders' => 0,
            'cleanup' => 0,
        ];

        try {
            $result['evidence_violations'] = $this->evidenceWindows();
            $result['task_violations'] = $this->tasks();
            $result['spontaneous_violations'] = $this->spontaneous();
            $result['damage_violations'] = $this->damageRequests();
            $result['revision_violations'] = $this->revisions();
            $result['shipping_violations'] = $this->shipping();
            $result['expired_private_offers'] = $this->privateOffers();
            $result['reminders'] = $this->reminders();
            $result['cleanup'] = $this->cleanup();
            return $result;
        } finally {
            $this->release('global-cron');
        }
    }

    private function acquire(string $key, int $seconds): bool
    {
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT locked_until FROM scheduler_locks WHERE lock_key=? FOR UPDATE');
            $q->execute([$key]);
            $until = $q->fetchColumn();

            if ($until && strtotime((string) $until) > time()) {
                $this->db->rollBack();
                return false;
            }

            $this->db->prepare('INSERT INTO scheduler_locks(lock_key,locked_until,updated_at) VALUES(?,DATE_ADD(NOW(),INTERVAL ? SECOND),NOW()) ON DUPLICATE KEY UPDATE locked_until=VALUES(locked_until),updated_at=NOW()')
                ->execute([$key, $seconds]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function release(string $key): void
    {
        $this->db->prepare('UPDATE scheduler_locks SET locked_until=NOW(),updated_at=NOW() WHERE lock_key=?')->execute([$key]);
    }

    private function evidenceWindows(): int
    {
        $q = $this->db->query("SELECT ew.id,ew.required_count,od.order_id,o.seller_id FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id JOIN orders o ON o.id=od.order_id WHERE ew.grace_ends_at<NOW() AND ew.status='open' AND NOT EXISTS(SELECT 1 FROM platform_outages po WHERE ew.grace_ends_at BETWEEN po.starts_at AND po.ends_at)");
        $made = 0;

        foreach ($q->fetchAll() as $w) {
            $c = $this->db->prepare('SELECT COUNT(*) FROM evidences WHERE evidence_window_id=?');
            $c->execute([$w['id']]);
            $missing = max(0, (int) $w['required_count'] - (int) $c->fetchColumn());

            for ($slot = 1; $slot <= $missing; $slot++) {
                $sourceId = (int) $w['id'] * 1000 + $slot;
                if ($this->ensureViolation((int) $w['order_id'], 'missing_evidence', 'evidence_window', $sourceId, 'Pflichtnachweis im vorgesehenen Zeitfenster einschließlich Nachfrist fehlt.', true)) {
                    $made++;
                }
            }

            $this->db->prepare("UPDATE evidence_windows SET status='closed' WHERE id=?")->execute([$w['id']]);
        }

        return $made;
    }

    private function tasks(): int
    {
        $q = $this->db->query("SELECT te.id,t.order_id FROM task_executions te JOIN tasks t ON t.id=te.task_id WHERE te.grace_ends_at<NOW() AND te.submitted_at IS NULL AND te.review_status='open' AND NOT EXISTS(SELECT 1 FROM platform_outages po WHERE te.grace_ends_at BETWEEN po.starts_at AND po.ends_at)");
        $made = 0;

        foreach ($q->fetchAll() as $x) {
            if ($this->ensureViolation((int) $x['order_id'], 'task_not_completed', 'task_execution', (int) $x['id'], 'Zusatzaufgabe wurde innerhalb der Frist und Nachfrist nicht vollständig eingereicht.', true)) {
                $made++;
            }
            $this->db->prepare("UPDATE task_executions SET review_status='overdue' WHERE id=?")->execute([$x['id']]);
        }

        return $made;
    }

    private function spontaneous(): int
    {
        $q = $this->db->query("SELECT sr.id,sr.order_id,sr.requested_count FROM spontaneous_requests sr WHERE sr.grace_ends_at<NOW() AND sr.status NOT IN('completed','expired') AND NOT EXISTS(SELECT 1 FROM platform_outages po WHERE sr.grace_ends_at BETWEEN po.starts_at AND po.ends_at)");
        $made = 0;

        foreach ($q->fetchAll() as $row) {
            $count = $this->db->prepare('SELECT COUNT(*) FROM evidences WHERE spontaneous_request_id=?');
            $count->execute([$row['id']]);
            $missing = max(0, (int) $row['requested_count'] - (int) $count->fetchColumn());

            for ($slot = 1; $slot <= $missing; $slot++) {
                $sourceId = (int) $row['id'] * 1000 + $slot;
                if ($this->ensureViolation((int) $row['order_id'], 'spontaneous_photo_missing', 'spontaneous_request', $sourceId, 'Gefordertes spontanes Foto wurde nicht innerhalb der Frist einschließlich Nachfrist eingereicht.', true)) {
                    $made++;
                }
            }

            $this->db->prepare("UPDATE spontaneous_requests SET status=? WHERE id=?")->execute([$missing > 0 ? 'expired' : 'completed', $row['id']]);
        }

        return $made;
    }

    private function damageRequests(): int
    {
        $q = $this->db->query("SELECT dr.id,dr.damage_case_id,dc.order_id FROM damage_evidence_requests dr JOIN damage_cases dc ON dc.id=dr.damage_case_id WHERE dr.grace_ends_at<NOW() AND dr.status='requested' AND NOT EXISTS(SELECT 1 FROM platform_outages po WHERE dr.grace_ends_at BETWEEN po.starts_at AND po.ends_at)");
        $made = 0;

        foreach ($q->fetchAll() as $row) {
            if ($this->ensureViolation((int) $row['order_id'], 'damage_followup_missing', 'damage_case', (int) $row['damage_case_id'], 'Nachforderung im Beschädigungsvorgang wurde nicht rechtzeitig erfüllt.', true)) {
                $made++;
            }
            $this->db->prepare("UPDATE damage_evidence_requests SET status='overdue' WHERE id=?")->execute([$row['id']]);
        }

        return $made;
    }

    private function revisions(): int
    {
        $q = $this->db->query("SELECT rr.id,oc.order_id FROM revision_rounds rr JOIN digital_components dc ON dc.id=rr.digital_component_id JOIN order_components oc ON oc.id=dc.order_component_id WHERE rr.grace_ends_at<NOW() AND rr.status='open' AND NOT EXISTS(SELECT 1 FROM platform_outages po WHERE rr.grace_ends_at BETWEEN po.starts_at AND po.ends_at)");
        $made = 0;

        foreach ($q->fetchAll() as $row) {
            $extension = $this->digitalViolationExtends((int) $row['order_id']);
            if ($this->ensureViolation((int) $row['order_id'], 'digital_revision_missed', 'revision_round', (int) $row['id'], 'Digitale Revision wurde nicht innerhalb der Frist einschließlich Nachfrist eingereicht.', $extension)) {
                $made++;
            }
            $this->db->prepare("UPDATE revision_rounds SET status='overdue' WHERE id=?")->execute([$row['id']]);
        }

        return $made;
    }

    private function shipping(): int
    {
        $q = $this->db->query("SELECT ss.id,sw.order_id FROM shipping_steps ss JOIN shipping_workflows sw ON sw.id=ss.shipping_workflow_id WHERE ss.is_required=1 AND ss.status='pending' AND ss.deadline IS NOT NULL AND DATE_ADD(ss.deadline,INTERVAL 1 HOUR)<NOW() AND sw.status='active'");
        $made = 0;

        foreach ($q->fetchAll() as $row) {
            if ($this->ensureViolation((int) $row['order_id'], 'shipping_requirement_missed', 'shipping_step', (int) $row['id'], 'Pflichtschritt im Versandworkflow wurde nicht innerhalb der Frist einschließlich Nachfrist erfüllt.', true)) {
                $made++;
            }
            $this->db->prepare("UPDATE shipping_steps SET status='overdue' WHERE id=?")->execute([$row['id']]);
        }

        return $made;
    }

    private function privateOffers(): int
    {
        $q = $this->db->query("SELECT id FROM offers WHERE is_private=1 AND status='active' AND private_offer_status='pending' AND acceptance_deadline<NOW()");
        $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $this->db->prepare("UPDATE offers SET private_offer_status='expired',updated_at=NOW() WHERE id=?")->execute([$id]);
        }
        return count($ids);
    }

    private function reminders(): int
    {
        $count = 0;

        $windows = $this->db->query("SELECT ew.id,ew.name,ew.starts_at,od.order_id,o.seller_id FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id JOIN orders o ON o.id=od.order_id WHERE ew.status='open' AND ew.starts_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 61 MINUTE)")->fetchAll();
        foreach ($windows as $w) {
            $mins = (int) floor((strtotime($w['starts_at']) - time()) / 60);
            if ($mins >= 55 && $this->settingEnabled('evidence_reminder_60', true)) {
                if ($this->notify((int) $w['seller_id'], 'evidence:' . $w['id'] . ':60m', 'Nachweisfenster ' . $w['name'], 'Das Nachweisfenster beginnt in ungefähr 60 Minuten.', '/konto/auftraege/' . $w['order_id'])) {
                    $count++;
                }
            }
            if ($mins >= 10 && $mins <= 16 && $this->settingEnabled('evidence_reminder_15', true)) {
                if ($this->notify((int) $w['seller_id'], 'evidence:' . $w['id'] . ':15m', 'Nachweisfenster ' . $w['name'], 'Das Nachweisfenster beginnt in ungefähr 15 Minuten.', '/konto/auftraege/' . $w['order_id'])) {
                    $count++;
                }
            }
            if ($mins <= 1) {
                if ($this->notify((int) $w['seller_id'], 'evidence:' . $w['id'] . ':start', 'Nachweisfenster ' . $w['name'], 'Das Nachweisfenster ist jetzt fällig.', '/konto/auftraege/' . $w['order_id'])) {
                    $count++;
                }
            }
        }

        $sp = $this->db->query("SELECT sr.id,sr.order_id,sr.deadline,sr.created_at,o.seller_id FROM spontaneous_requests sr JOIN orders o ON o.id=sr.order_id WHERE sr.status IN('requested','seen','confirmed','uploaded') AND sr.deadline>NOW()")->fetchAll();
        foreach ($sp as $row) {
            $now = time();
            $start = strtotime($row['created_at']);
            $end = strtotime($row['deadline']);
            $left = $end - $now;
            $total = max(1, $end - $start);
            if ($left <= max(60, (int) round($total / 2)) && $left > 900) {
                if ($this->notify((int) $row['seller_id'], 'spontaneous:' . $row['id'] . ':half', 'Spontane Fotoanforderung', 'Die Frist für die zusätzliche Fotoanforderung ist ungefähr zur Hälfte abgelaufen.', '/konto/auftraege/' . $row['order_id'])) {
                    $count++;
                }
            }
            if ($left <= 900) {
                if ($this->notify((int) $row['seller_id'], 'spontaneous:' . $row['id'] . ':near', 'Spontane Fotoanforderung', 'Die Frist für die zusätzliche Fotoanforderung läuft bald ab.', '/konto/auftraege/' . $row['order_id'])) {
                    $count++;
                }
            }
        }

        $tasks = $this->db->query("SELECT te.id,te.due_at,t.order_id,o.seller_id,t.title FROM task_executions te JOIN tasks t ON t.id=te.task_id JOIN orders o ON o.id=t.order_id WHERE te.review_status='open' AND te.due_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 1 HOUR)")->fetchAll();
        foreach ($tasks as $row) {
            if ($this->notify((int) $row['seller_id'], 'task:' . $row['id'] . ':1h', 'Zusatzaufgabe bald fällig', 'Die Zusatzaufgabe „' . $row['title'] . '“ ist innerhalb der nächsten Stunde fällig.', '/konto/auftraege/' . $row['order_id'])) {
                $count++;
            }
        }

        $revisions = $this->db->query("SELECT rr.id,rr.deadline,oc.order_id,o.seller_id FROM revision_rounds rr JOIN digital_components dc ON dc.id=rr.digital_component_id JOIN order_components oc ON oc.id=dc.order_component_id JOIN orders o ON o.id=oc.order_id WHERE rr.status='open' AND rr.deadline BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 1 HOUR)")->fetchAll();
        foreach ($revisions as $row) {
            if ($this->notify((int) $row['seller_id'], 'revision:' . $row['id'] . ':1h', 'Revision bald fällig', 'Die Revisionsfrist läuft innerhalb der nächsten Stunde ab.', '/konto/auftraege/' . $row['order_id'])) {
                $count++;
            }
        }

        $priv = $this->db->query("SELECT id,seller_id,title,acceptance_deadline FROM offers WHERE is_private=1 AND status='active' AND private_offer_status='pending' AND acceptance_deadline>NOW() AND acceptance_deadline<=DATE_ADD(NOW(),INTERVAL 25 HOUR)")->fetchAll();
        foreach ($priv as $o) {
            $left = strtotime($o['acceptance_deadline']) - time();
            $bucket = $left <= 3600 ? '1h' : '24h';
            if ($this->notify((int) $o['seller_id'], 'private-offer:' . $o['id'] . ':' . $bucket, 'Privatangebot läuft bald ab', 'Für „' . $o['title'] . '“ endet die Annahmefrist ' . ($bucket === '1h' ? 'in ungefähr einer Stunde.' : 'innerhalb der nächsten 24 Stunden.'), '/angebote/' . $o['id'])) {
                $count++;
            }
        }

        return $count;
    }

    private function ensureViolation(int $orderId, string $type, string $sourceType, int $sourceId, string $description, bool $provisionalExtension): bool
    {
        $q = $this->db->prepare('SELECT id FROM violations WHERE order_id=? AND violation_type=? AND source_type=? AND source_id=?');
        $q->execute([$orderId, $type, $sourceType, $sourceId]);
        if ($q->fetchColumn()) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $i = $this->db->prepare("INSERT INTO violations(order_id,violation_type,source_type,source_id,description,status,provisional_extension,created_at) VALUES(?,?,?,?,?,'open',?,NOW())");
            $i->execute([$orderId, $type, $sourceType, $sourceId, $description, (int) $provisionalExtension]);
            $violationId = (int) $this->db->lastInsertId();

            if ($provisionalExtension) {
                $this->db->prepare("INSERT INTO extension_days(order_id,violation_id,source_type,source_id,reason,is_provisional,is_paid,created_at) VALUES(?,?,'violation',?,?,1,0,NOW())")
                    ->execute([$orderId, $violationId, $sourceId, $description]);
            }

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function digitalViolationExtends(int $orderId): bool
    {
        $q = $this->db->prepare('SELECT ov.violation_json FROM orders o JOIN offer_versions ov ON ov.id=o.offer_version_id WHERE o.id=?');
        $q->execute([$orderId]);
        $cfg = json_decode((string) ($q->fetchColumn() ?: '[]'), true) ?: [];
        $mode = (string) ($cfg['digital_violation_mode'] ?? 'extension');
        return $mode !== 'log_only';
    }

    private function notify(int $sellerId, string $key, string $title, string $message, string $url): bool
    {
        try {
            $q = $this->db->prepare("INSERT INTO notifications(seller_id,dedupe_key,type,title,message,url,created_at) VALUES(?,?,'reminder',?,?,?,NOW())");
            $q->execute([$sellerId, $key, $title, $message, $url]);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }
            throw $e;
        }

        if ($this->root !== null) {
            $seller = $this->db->prepare('SELECT email FROM sellers WHERE id=?');
            $seller->execute([$sellerId]);
            $address = $seller->fetchColumn();
            if ($address) {
                $config = require $this->root . '/config/app.php';
                (new Mailer($config))->send((string) $address, $title, '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>');
            }
        }

        return true;
    }

    private function settingEnabled(string $key, bool $default): bool
    {
        $q = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
        $q->execute([$key]);
        $value = $q->fetchColumn();
        if ($value === false) {
            return $default;
        }
        return in_array(strtolower((string) $value), ['1','true','yes','on'], true);
    }

    private function cleanup(): int
    {
        $a = $this->db->exec("DELETE FROM rate_limits WHERE created_at<DATE_SUB(NOW(),INTERVAL 2 DAY)");
        $b = $this->db->exec("DELETE FROM password_resets WHERE expires_at<DATE_SUB(NOW(),INTERVAL 1 DAY)");
        $c = $this->db->exec("DELETE FROM email_verifications WHERE expires_at<DATE_SUB(NOW(),INTERVAL 7 DAY)");
        return (int) $a + (int) $b + (int) $c;
    }
}
