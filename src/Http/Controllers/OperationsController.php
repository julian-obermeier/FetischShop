<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Core\MigrationRunner;
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
        $this->append($items, $this->db->query("SELECT NULL order_id,o.id source_id,'Privatangebot' type,o.title,o.acceptance_deadline due_at,o.acceptance_deadline grace_at,o.private_offer_status status,CONCAT('/admin/angebote/',o.id,'/bearbeiten') url FROM offers o WHERE o.is_private=1 AND o.private_offer_status='pending' AND o.acceptance_deadline IS NOT NULL")->fetchAll());

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

        $q = $this->db->prepare("SELECT o.id order_id,'Auftragsstart' type,CONCAT('#',o.order_number,' Start') title,o.started_at starts_at,NULL ends_at,CONCAT('/admin/auftraege/',o.id) url FROM orders o WHERE o.started_at>=? AND o.started_at<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT od.order_id,'Nachweisfenster' type,CONCAT('#',o.order_number,' · ',ew.name) title,ew.starts_at,ew.ends_at,CONCAT('/admin/auftraege/',od.order_id) url FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id JOIN orders o ON o.id=od.order_id WHERE ew.starts_at>=? AND ew.starts_at<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT t.order_id,'Aufgabe' type,CONCAT('#',o.order_number,' · ',t.title) title,te.due_at starts_at,NULL ends_at,CONCAT('/admin/auftraege/',t.order_id) url FROM task_executions te JOIN tasks t ON t.id=te.task_id JOIN orders o ON o.id=t.order_id WHERE te.due_at>=? AND te.due_at<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT oc.order_id,'Revision' type,CONCAT('#',o.order_number,' · Revision ',rr.round_no) title,rr.deadline starts_at,NULL ends_at,CONCAT('/admin/auftraege/',oc.order_id) url FROM revision_rounds rr JOIN digital_components dc ON dc.id=rr.digital_component_id JOIN order_components oc ON oc.id=dc.order_component_id JOIN orders o ON o.id=oc.order_id WHERE rr.deadline>=? AND rr.deadline<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT sw.order_id,'Versandschritt' type,CONCAT('#',o.order_number,' · ',ss.title) title,ss.deadline starts_at,DATE_ADD(ss.deadline,INTERVAL 1 HOUR) ends_at,CONCAT('/admin/auftraege/',sw.order_id) url FROM shipping_steps ss JOIN shipping_workflows sw ON sw.id=ss.shipping_workflow_id JOIN orders o ON o.id=sw.order_id WHERE ss.deadline IS NOT NULL AND ss.deadline>=? AND ss.deadline<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT NULL order_id,'Privatangebot' type,CONCAT('Privatangebot · ',o.title) title,o.acceptance_deadline starts_at,NULL ends_at,CONCAT('/admin/angebote/',o.id,'/bearbeiten') url FROM offers o WHERE o.is_private=1 AND o.acceptance_deadline IS NOT NULL AND o.acceptance_deadline>=? AND o.acceptance_deadline<?");
        $q->execute($params);
        $this->append($events, $q->fetchAll());

        $q = $this->db->prepare("SELECT s.order_id,'Versand' type,CONCAT('#',o.order_number,' · Versand') title,s.shipped_at starts_at,NULL ends_at,CONCAT('/admin/auftraege/',s.order_id) url FROM shipments s JOIN orders o ON o.id=s.order_id WHERE s.shipped_at>=? AND s.shipped_at<?");
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

    public function audit(Request $r): void
    {
        $term=trim((string)$r->input('q'));
        $actor=trim((string)$r->input('actor'));
        $eventType=trim((string)$r->input('event'));
        $from=trim((string)$r->input('from'));
        $to=trim((string)$r->input('to'));

        $where=['1=1'];$params=[];
        if($term!==''){
            $like='%'.$term.'%';
            $where[]="(o.order_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR se.event_type LIKE ?)";
            array_push($params,$like,$like,$like,$like,$like);
        }
        if(in_array($actor,['seller','admin','system'],true)){$where[]='se.actor_type=?';$params[]=$actor;}
        if($eventType!==''){$where[]='se.event_type=?';$params[]=$eventType;}
        if($from!==''&&preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$from)){$where[]='se.created_at>=?';$params[]=$from.' 00:00:00';}
        if($to!==''&&preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$to)){$where[]='se.created_at<=?';$params[]=$to.' 23:59:59';}

        $sql="SELECT se.*,o.order_number,s.first_name,s.last_name,s.email,'system_event' source_type
            FROM system_events se
            LEFT JOIN orders o ON o.id=se.order_id
            LEFT JOIN sellers s ON s.id=se.seller_id
            WHERE ".implode(' AND ',$where)."
            ORDER BY se.created_at DESC,se.id DESC LIMIT 500";
        $q=$this->db->prepare($sql);$q->execute($params);$events=$q->fetchAll();

        if($eventType==='' || str_starts_with($eventType,'rights_') || in_array($eventType,['seller_consented','revision_requested','admin_download'],true)){
            $rightsWhere=['1=1'];$rightsParams=[];
            if($term!==''){
                $like='%'.$term.'%';
                $rightsWhere[]="(o.order_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR dre.event_type LIKE ?)";
                array_push($rightsParams,$like,$like,$like,$like,$like);
            }
            if(in_array($actor,['seller','admin','system'],true)){$rightsWhere[]='dre.actor_type=?';$rightsParams[]=$actor;}
            if($eventType!==''){$rightsWhere[]='dre.event_type=?';$rightsParams[]=$eventType;}
            if($from!==''&&preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$from)){$rightsWhere[]='dre.created_at>=?';$rightsParams[]=$from.' 00:00:00';}
            if($to!==''&&preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$to)){$rightsWhere[]='dre.created_at<=?';$rightsParams[]=$to.' 23:59:59';}
            $rightsSql="SELECT dre.id,dre.order_id,o.seller_id,dre.event_type,dre.actor_type,dre.actor_id,
                JSON_OBJECT('digital_component_id',dre.digital_component_id,'clause_version',dre.clause_version,'note',dre.note) payload_json,
                dre.created_at,o.order_number,s.first_name,s.last_name,s.email,'digital_rights' source_type
                FROM digital_rights_events dre
                JOIN orders o ON o.id=dre.order_id
                LEFT JOIN sellers s ON s.id=o.seller_id
                WHERE ".implode(' AND ',$rightsWhere)."
                ORDER BY dre.created_at DESC,dre.id DESC LIMIT 300";
            try{$rq=$this->db->prepare($rightsSql);$rq->execute($rightsParams);$events=array_merge($events,$rq->fetchAll());}catch(\Throwable){}
        }

        usort($events,static fn(array $a,array $b):int=>strcmp((string)$b['created_at'],(string)$a['created_at']));
        $events=array_slice($events,0,500);
        foreach($events as &$event){
            $payload=json_decode((string)($event['payload_json']??'{}'),true);
            $event['payload']=is_array($payload)?$payload:[];
        }
        unset($event);

        $types=[];
        try{$types=$this->db->query("SELECT event_type FROM system_events UNION SELECT event_type FROM digital_rights_events ORDER BY event_type")->fetchAll(PDO::FETCH_COLUMN);}catch(\Throwable){$types=$this->db->query("SELECT DISTINCT event_type FROM system_events ORDER BY event_type")->fetchAll(PDO::FETCH_COLUMN);}

        View::render($this->root,'admin/audit',[
            'pageTitle'=>'Protokoll',
            'events'=>$events,
            'eventTypes'=>$types,
            'filterTerm'=>$term,
            'filterActor'=>$actor,
            'filterEvent'=>$eventType,
            'filterFrom'=>$from,
            'filterTo'=>$to,
        ]);
    }

    public function archive(Request $r): void
    {
        $term=trim((string)$r->input('q'));
        $decision=trim((string)$r->input('decision'));
        $year=trim((string)$r->input('year'));
        $where=["(o.archived_at IS NOT NULL OR o.status='rejected')"];
        $params=[];

        if($term!==''){
            $like='%'.$term.'%';
            $where[]="(o.order_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR ov.title LIKE ?)";
            array_push($params,$like,$like,$like,$like,$like);
        }
        if(in_array($decision,['accepted','partially_accepted','rejected'],true)){
            $where[]='fr.decision=?';$params[]=$decision;
        }
        if($year!==''&&preg_match('/^\\d{4}$/',$year)){
            $where[]='YEAR(COALESCE(o.archived_at,o.finished_at,o.updated_at))=?';$params[]=(int)$year;
        }

        $sql="SELECT o.*,s.first_name,s.last_name,s.email,ov.title,fr.decision,fr.approved_amount
            FROM orders o
            JOIN sellers s ON s.id=o.seller_id
            JOIN offer_versions ov ON ov.id=o.offer_version_id
            LEFT JOIN final_reviews fr ON fr.order_id=o.id
            WHERE ".implode(' AND ',$where)."
            ORDER BY COALESCE(o.archived_at,o.finished_at,o.updated_at) DESC";
        $q=$this->db->prepare($sql);$q->execute($params);
        $years=$this->db->query("SELECT DISTINCT YEAR(COALESCE(archived_at,finished_at,updated_at)) y FROM orders WHERE archived_at IS NOT NULL OR status='rejected' ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);

        View::render($this->root,'admin/archive',[
            'pageTitle'=>'Archiv',
            'orders'=>$q->fetchAll(),
            'filterTerm'=>$term,
            'filterDecision'=>$decision,
            'filterYear'=>$year,
            'years'=>$years,
        ]);
    }

    public function systemStatus(): void
    {
        $migrationRunner = new MigrationRunner($this->db, $this->root);
        $pending = $migrationRunner->pending();

        $settingsQ = $this->db->prepare("SELECT setting_key,setting_value,updated_at FROM settings WHERE setting_key IN ('scheduler_last_run','scheduler_last_result')");
        $settingsQ->execute();
        $runtime = [];
        foreach ($settingsQ->fetchAll() as $row) {
            $runtime[$row['setting_key']] = $row;
        }

        $cronLastRun = $runtime['scheduler_last_run']['setting_value'] ?? null;
        $cronAge = $cronLastRun ? max(0, time() - strtotime($cronLastRun)) : null;
        $dbVersion = (string) $this->db->query('SELECT VERSION()')->fetchColumn();
        $privateStorage = $this->root . '/storage/private';
        $logDir = $this->root . '/storage/logs';
        $appConfig = require $this->root . '/config/app.php';
        $baseUrl = rtrim((string)($appConfig['base_url'] ?? ''), '/');
        $publicRoot = realpath($this->root . '/public') ?: ($this->root . '/public');
        $privateReal = realpath($privateStorage) ?: $privateStorage;
        $privateOutsidePublic = !str_starts_with(str_replace('\\','/',$privateReal), rtrim(str_replace('\\','/',$publicRoot),'/') . '/');
        $cronTokenQ = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key='cron_token'");
        $cronTokenQ->execute();
        $cronToken = (string)($cronTokenQ->fetchColumn() ?: '');

        $checks = [
            ['label' => 'PHP-Version', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>='), 'value' => PHP_VERSION],
            ['label' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'value' => extension_loaded('pdo_mysql') ? 'aktiv' : 'fehlt'],
            ['label' => 'Fileinfo', 'ok' => extension_loaded('fileinfo'), 'value' => extension_loaded('fileinfo') ? 'aktiv' : 'fehlt'],
            ['label' => 'mbstring', 'ok' => extension_loaded('mbstring'), 'value' => extension_loaded('mbstring') ? 'aktiv' : 'fehlt'],
            ['label' => 'Private Ablage', 'ok' => is_dir($privateStorage) && is_writable($privateStorage), 'value' => is_dir($privateStorage) && is_writable($privateStorage) ? 'beschreibbar' : 'nicht beschreibbar'],
            ['label' => 'Log-Verzeichnis', 'ok' => is_dir($logDir) && is_writable($logDir), 'value' => is_dir($logDir) && is_writable($logDir) ? 'beschreibbar' : 'nicht beschreibbar'],
            ['label' => 'Mail-Funktion', 'ok' => function_exists('mail'), 'value' => function_exists('mail') ? 'verfügbar' : 'nicht verfügbar'],
            ['label' => 'Cron-Heartbeat', 'ok' => $cronAge !== null && $cronAge <= 900, 'value' => $cronLastRun ? date('d.m.Y H:i:s', strtotime($cronLastRun)) . ' · vor ' . (int) floor($cronAge / 60) . ' Min.' : 'noch kein Lauf'],
            ['label' => 'Datenbank', 'ok' => true, 'value' => $dbVersion],
            ['label' => 'Migrationen', 'ok' => count($pending) === 0, 'value' => count($pending) === 0 ? 'aktuell' : count($pending) . ' offen'],
            ['label' => 'Öffentliche URL', 'ok' => str_starts_with($baseUrl, 'https://'), 'value' => $baseUrl !== '' ? $baseUrl : 'nicht konfiguriert'],
            ['label' => 'Cron-Schlüssel', 'ok' => strlen($cronToken) >= 32, 'value' => strlen($cronToken) >= 32 ? 'gesetzt' : 'fehlt / zu kurz'],
            ['label' => 'Installationssperre', 'ok' => is_file($this->root . '/storage/installed.lock'), 'value' => is_file($this->root . '/storage/installed.lock') ? 'aktiv' : 'fehlt'],
            ['label' => 'Private Ablage außerhalb Webroot', 'ok' => $privateOutsidePublic, 'value' => $privateOutsidePublic ? 'sicher getrennt' : 'prüfen'],
        ];

        View::render($this->root, 'admin/system-status', [
            'pageTitle' => 'Systemstatus',
            'checks' => $checks,
            'pendingMigrations' => $pending,
            'schedulerResult' => json_decode((string)($runtime['scheduler_last_result']['setting_value'] ?? '{}'), true) ?: [],
        ]);
    }

    public function settings(): void
    {
        $rows = $this->db->query('SELECT setting_key,setting_value FROM settings ORDER BY setting_key')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $outages = $this->db->query('SELECT * FROM platform_outages ORDER BY starts_at DESC LIMIT 50')->fetchAll();

        $app = require $this->root . '/config/app.php';
        $baseUrl = rtrim((string)($app['base_url'] ?? ''), '/');
        $cronToken = (string)($settings['cron_token'] ?? '');
        $cronUrl = ($baseUrl !== '' && $cronToken !== '') ? $baseUrl . '/cron/' . $cronToken : '';

        View::render($this->root, 'admin/settings', [
            'pageTitle' => 'Einstellungen',
            'settings' => $settings,
            'outages' => $outages,
            'cronUrl' => $cronUrl,
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

    public function regenerateCronToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $q = $this->db->prepare("INSERT INTO settings(setting_key,setting_value,updated_at) VALUES('cron_token',?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()");
        $q->execute([$token]);
        Session::flash('success', 'Neue Cron-URL wurde erzeugt. Die bisherige URL ist ab sofort ungültig.');
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
