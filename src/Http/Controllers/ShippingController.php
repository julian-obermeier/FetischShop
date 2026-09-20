<?php
namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\PrivateStorage;
use PDO;
use RuntimeException;

final class ShippingController
{
    public function __construct(private string $root, private PDO $db, private Auth $auth) {}

    public function adminAddresses(): void
    {
        $rows = $this->db->query('SELECT * FROM recipient_addresses ORDER BY is_active DESC,label')->fetchAll();
        View::render($this->root, 'admin/addresses', ['pageTitle' => 'Empfängeradressen', 'addresses' => $rows]);
    }

    public function saveAddress(Request $r): void
    {
        $id = (int) $r->input('id', 0);
        $data = [
            trim((string) $r->input('label')),
            trim((string) $r->input('recipient_name')),
            trim((string) $r->input('street')),
            trim((string) $r->input('postal_code')),
            trim((string) $r->input('city')),
            strtoupper(trim((string) $r->input('country_code', 'DE'))) ?: 'DE',
            (int) !!$r->input('is_active'),
        ];
        if (in_array('', array_slice($data, 0, 5), true)) {
            Session::flash('error', 'Bitte alle Pflichtfelder der Empfängeradresse ausfüllen.');
            Response::redirect('/admin/empfaengeradressen');
        }
        if ($id > 0) {
            $q = $this->db->prepare('UPDATE recipient_addresses SET label=?,recipient_name=?,street=?,postal_code=?,city=?,country_code=?,is_active=?,updated_at=NOW() WHERE id=?');
            $q->execute([...$data, $id]);
        } else {
            $q = $this->db->prepare('INSERT INTO recipient_addresses(label,recipient_name,street,postal_code,city,country_code,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,NOW(),NOW())');
            $q->execute($data);
        }
        Session::flash('success', 'Empfängeradresse gespeichert.');
        Response::redirect('/admin/empfaengeradressen');
    }

    public function start(Request $r, array $p): void
    {
        $orderId = (int) $p['id'];
        $addressId = (int) $r->input('recipient_address_id');
        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare("SELECT o.*,ov.end_workflow_json,c.is_digital FROM orders o JOIN offer_versions ov ON ov.id=o.offer_version_id JOIN offers off ON off.id=o.offer_id JOIN categories c ON c.id=off.category_id WHERE o.id=? FOR UPDATE");
            $q->execute([$orderId]);
            $order = $q->fetch();
            if (!$order) {
                throw new RuntimeException('Auftrag nicht gefunden.');
            }
            if ((int) $order['is_digital'] === 1) {
                throw new RuntimeException('Digitale Aufträge besitzen keinen Versandworkflow.');
            }
            if (!in_array($order['phase'], ['execution', 'shipping'], true)) {
                throw new RuntimeException('Der Auftrag befindet sich nicht in der Durchführung.');
            }

            $openWindows = $this->db->prepare("SELECT COUNT(*) FROM evidence_windows ew JOIN order_days od ON od.id=ew.order_day_id WHERE od.order_id=? AND (ew.status='open' OR ew.grace_ends_at>NOW())");
            $openWindows->execute([$orderId]);
            if ((int) $openWindows->fetchColumn() > 0) {
                throw new RuntimeException('Es bestehen noch laufende oder offene Nachweisfenster.');
            }

            $openDecisions = $this->db->prepare("SELECT COUNT(*) FROM violations WHERE order_id=? AND status IN('open','reviewed')");
            $openDecisions->execute([$orderId]);
            if ((int) $openDecisions->fetchColumn() > 0) {
                throw new RuntimeException('Offene Verstöße müssen vor Versandbeginn entschieden werden.');
            }

            $addr = $this->db->prepare('SELECT id FROM recipient_addresses WHERE id=? AND is_active=1');
            $addr->execute([$addressId]);
            if (!$addr->fetchColumn()) {
                throw new RuntimeException('Bitte eine aktive Empfängeradresse wählen.');
            }

            $existing = $this->db->prepare('SELECT id FROM shipping_workflows WHERE order_id=?');
            $existing->execute([$orderId]);
            if ($existing->fetchColumn()) {
                throw new RuntimeException('Der Versandworkflow wurde bereits angelegt.');
            }

            $this->db->prepare("INSERT INTO shipping_workflows(order_id,status,recipient_address_id,started_at,created_at) VALUES(?,'active',?,NOW(),NOW())")->execute([$orderId, $addressId]);
            $workflowId = (int) $this->db->lastInsertId();

            $cfg = json_decode($order['end_workflow_json'] ?: '[]', true) ?: [];
            $steps = $cfg['steps'] ?? $cfg;
            if (!is_array($steps) || !$steps) {
                $steps = [
                    ['title' => 'Nutzung beenden / ausziehen', 'instructions' => 'Bestätige, dass die Nutzung beendet wurde.', 'type' => 'checkbox', 'required' => true],
                    ['title' => 'Artikel dokumentieren', 'instructions' => 'Nimm ein aktuelles Foto des Artikels auf.', 'type' => 'photo', 'required' => true],
                    ['title' => 'Verpackung dokumentieren', 'instructions' => 'Verpacke den Artikel und fotografiere die verschlossene Verpackung.', 'type' => 'photo', 'required' => true],
                    ['title' => 'Versand abschließen', 'instructions' => 'Hinterlege die Trackingnummer oder lade den Einlieferungsbeleg hoch.', 'type' => 'tracking_or_receipt', 'required' => true],
                ];
            }

            $n = 1;
            foreach ($steps as $step) {
                $title = trim((string) ($step['title'] ?? ('Schritt ' . $n)));
                $instructions = trim((string) ($step['instructions'] ?? ''));
                $required = array_key_exists('required', $step) ? (int) !!$step['required'] : 1;
                $config = [
                    'type' => (string) ($step['type'] ?? 'checkbox'),
                    'photos' => (int) ($step['photos'] ?? 0),
                    'text' => !empty($step['text']),
                    'tracking' => !empty($step['tracking']),
                ];
                $this->db->prepare("INSERT INTO shipping_steps(shipping_workflow_id,step_no,title,instructions,is_required,config_json,status,created_at) VALUES(?,?,?,?,?,?,'pending',NOW())")
                    ->execute([$workflowId, $n, $title, $instructions, $required, json_encode($config, JSON_UNESCAPED_UNICODE)]);
                $n++;
            }

            $this->db->prepare("INSERT INTO shipments(order_id,shipping_workflow_id,status,created_at) VALUES(?,?,'preparing',NOW())")->execute([$orderId, $workflowId]);
            $this->db->prepare("UPDATE orders SET status='shipping',phase='shipping',updated_at=NOW() WHERE id=?")->execute([$orderId]);
            $this->systemChat($orderId, 'Die Durchführung ist abgeschlossen. Der Versandworkflow wurde freigeschaltet.');
            $this->db->commit();
            Session::flash('success', 'Versandworkflow wurde gestartet.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Session::flash('error', $e->getMessage());
        }
        Response::redirect('/admin/auftraege/' . $orderId);
    }

    public function sellerStep(Request $r, array $p): void
    {
        $seller = $this->auth->seller();
        $orderId = (int) $p['id'];
        $stepId = (int) $p['stepId'];

        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare("SELECT ss.*,sw.id workflow_id,sw.status workflow_status,o.seller_id FROM shipping_steps ss JOIN shipping_workflows sw ON sw.id=ss.shipping_workflow_id JOIN orders o ON o.id=sw.order_id WHERE ss.id=? AND o.id=? AND o.seller_id=? FOR UPDATE");
            $q->execute([$stepId, $orderId, $seller['id']]);
            $step = $q->fetch();
            if (!$step) {
                throw new RuntimeException('Versandschritt nicht gefunden.');
            }
            if ($step['workflow_status'] !== 'active') {
                throw new RuntimeException('Der Versandworkflow ist nicht aktiv.');
            }

            $prev = $this->db->prepare("SELECT COUNT(*) FROM shipping_steps WHERE shipping_workflow_id=? AND step_no<? AND is_required=1 AND status<>'completed'");
            $prev->execute([$step['workflow_id'], $step['step_no']]);
            if ((int) $prev->fetchColumn() > 0) {
                throw new RuntimeException('Bitte zuerst den vorherigen Pflichtschritt abschließen.');
            }
            if ($step['status'] === 'completed') {
                throw new RuntimeException('Dieser Schritt ist bereits abgeschlossen.');
            }

            $cfg = json_decode($step['config_json'] ?: '{}', true) ?: [];
            $type = (string) ($cfg['type'] ?? 'checkbox');
            $response = [];

            if (in_array($type, ['photo', 'tracking_or_receipt'], true) && isset($r->files['evidence']) && (($r->files['evidence']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK)) {
                $app = require $this->root . '/config/app.php';
                $file = (new PrivateStorage($app['private_storage']))->storeUploaded(
                    $r->files['evidence'],
                    'evidence',
                    ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
                    15 * 1024 * 1024
                );
                $run = $this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');
                $run->execute([$orderId]);
                $this->db->prepare("INSERT INTO evidences(order_id,order_run_id,shipping_step_id,evidence_type,file_path,original_name,mime_type,file_size,sha256,captured_at,metadata_json,review_status,created_at) VALUES(?,?,?,'shipping',?,?,?,?,?,NOW(),'{}','pending',NOW())")
                    ->execute([$orderId, $run->fetchColumn(), $stepId, $file['path'], $file['original_name'], $file['mime'], $file['size'], $file['sha256']]);
                $response['evidence_id'] = (int) $this->db->lastInsertId();
            }

            $tracking = trim((string) $r->input('tracking_number'));
            if ($tracking !== '') {
                $response['tracking_number'] = $tracking;
                $this->db->prepare('UPDATE shipments SET tracking_number=? WHERE order_id=?')->execute([$tracking, $orderId]);
            }

            $text = trim((string) $r->input('text'));
            if ($text !== '') {
                $response['text'] = $text;
            }
            if ($r->input('confirmed')) {
                $response['confirmed'] = true;
            }

            if ($type === 'photo' && empty($response['evidence_id'])) {
                throw new RuntimeException('Für diesen Schritt ist ein Foto erforderlich.');
            }
            if ($type === 'tracking_or_receipt' && empty($response['tracking_number']) && empty($response['evidence_id'])) {
                throw new RuntimeException('Bitte Trackingnummer oder Einlieferungsbeleg angeben.');
            }
            if ($type === 'checkbox' && empty($response['confirmed'])) {
                throw new RuntimeException('Bitte den Schritt bestätigen.');
            }

            $this->db->prepare("UPDATE shipping_steps SET response_json=?,completed_at=NOW(),status='completed' WHERE id=?")
                ->execute([json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $stepId]);

            $open = $this->db->prepare("SELECT COUNT(*) FROM shipping_steps WHERE shipping_workflow_id=? AND is_required=1 AND status<>'completed'");
            $open->execute([$step['workflow_id']]);
            if ((int) $open->fetchColumn() === 0) {
                $shipmentQ = $this->db->prepare('SELECT tracking_number FROM shipments WHERE order_id=?');
                $shipmentQ->execute([$orderId]);
                $trackingCurrent = $shipmentQ->fetchColumn();
                $receipt = $this->db->prepare("SELECT id FROM evidences WHERE order_id=? AND evidence_type='shipping' ORDER BY id DESC LIMIT 1");
                $receipt->execute([$orderId]);
                $receiptId = $receipt->fetchColumn();
                if (!$trackingCurrent && !$receiptId) {
                    throw new RuntimeException('Versand kann erst mit Trackingnummer oder Versandbeleg abgeschlossen werden.');
                }
                $this->db->prepare("UPDATE shipping_workflows SET status='completed',completed_at=NOW() WHERE id=?")->execute([$step['workflow_id']]);
                $this->db->prepare("UPDATE shipments SET receipt_evidence_id=COALESCE(receipt_evidence_id,?),status='shipped',shipped_at=NOW() WHERE order_id=?")->execute([$receiptId ?: null, $orderId]);
                $this->db->prepare("UPDATE orders SET status='shipped',updated_at=NOW() WHERE id=?")->execute([$orderId]);
                $this->systemChat($orderId, 'Versand abgeschlossen – wartet auf Wareneingang.');
            }

            $this->db->commit();
            Session::flash('success', 'Versandschritt gespeichert.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/konto/auftraege/' . $orderId);
    }

    public function received(Request $r, array $p): void
    {
        $orderId = (int) $p['id'];
        $q = $this->db->prepare("UPDATE shipments s JOIN orders o ON o.id=s.order_id SET s.status='received',s.received_at=NOW(),o.status='reviewing',o.phase='review',o.updated_at=NOW() WHERE s.order_id=? AND s.status='shipped'");
        $q->execute([$orderId]);
        if (!$q->rowCount()) {
            Session::flash('error', 'Wareneingang kann für diesen Auftrag derzeit nicht gesetzt werden.');
        } else {
            $this->systemChat($orderId, 'Wareneingang wurde bestätigt. Der Auftrag befindet sich in der Abschlussprüfung.');
            Session::flash('success', 'Wareneingang bestätigt.');
        }
        Response::redirect('/admin/auftraege/' . $orderId);
    }

    public function finalReview(Request $r, array $p): void
    {
        $orderId = (int) $p['id'];
        $decision = (string) $r->input('decision');
        if (!in_array($decision, ['accepted', 'partially_accepted', 'rejected'], true)) {
            Response::abort(422, 'Ungültige Abschlussentscheidung.');
        }

        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
            $q->execute([$orderId]);
            $order = $q->fetch();
            if (!$order) {
                throw new RuntimeException('Auftrag nicht gefunden.');
            }
            if (in_array($order['status'], ['rejected', 'completed', 'archived'], true)) {
                throw new RuntimeException('Der Auftrag wurde bereits abschließend entschieden.');
            }

            $approved = $decision === 'accepted'
                ? (float) $order['current_total']
                : ($decision === 'partially_accepted' ? round((float) str_replace(',', '.', (string) $r->input('approved_amount')), 2) : 0.0);
            if ($approved < 0 || $approved > (float) $order['current_total']) {
                throw new RuntimeException('Der freigegebene Betrag ist ungültig.');
            }

            $this->db->prepare("INSERT INTO final_reviews(order_id,decision,approved_amount,internal_note,seller_message,decided_at) VALUES(?,?,?,?,?,NOW())")
                ->execute([$orderId, $decision, $approved, trim((string) $r->input('internal_note')) ?: null, trim((string) $r->input('seller_message')) ?: null]);

            $walletQ = $this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');
            $walletQ->execute([$order['seller_id']]);
            $wallet = $walletQ->fetch();
            $reservedRelease = min((float) $wallet['balance_reserved'], (float) $order['current_total']);
            $newReserved = (float) $wallet['balance_reserved'] - $reservedRelease;
            $newAvailable = (float) $wallet['balance_available'] + $approved;
            $this->db->prepare('UPDATE wallets SET balance_reserved=?,balance_available=?,updated_at=NOW() WHERE id=?')->execute([$newReserved, $newAvailable, $wallet['id']]);

            if ($approved > 0) {
                $this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,balance_after,metadata_json,created_at) VALUES(?,?,'order_release','available',?,?,?,NOW())")
                    ->execute([$wallet['id'], $orderId, $approved, $newAvailable, json_encode(['decision' => $decision], JSON_UNESCAPED_UNICODE)]);
            }
            $rejectedAmount = max(0, (float) $order['current_total'] - $approved);
            if ($rejectedAmount > 0) {
                $this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,metadata_json,created_at) VALUES(?,?,'order_reduction','rejected',?,?,NOW())")
                    ->execute([$wallet['id'], $orderId, $rejectedAmount, json_encode(['decision' => $decision], JSON_UNESCAPED_UNICODE)]);
            }

            if ($decision === 'rejected') {
                $this->db->prepare("UPDATE orders SET status='rejected',phase='archive',finished_at=NOW(),archived_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$orderId]);
                $this->db->prepare('UPDATE chats SET is_readonly=1 WHERE order_id=?')->execute([$orderId]);
            } else {
                $this->db->prepare("UPDATE orders SET status='completed',phase='payout',finished_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$orderId]);
            }

            $message = trim((string) $r->input('seller_message'));
            $this->systemChat($orderId, 'Abschlussprüfung: ' . $decision . ($message !== '' ? ' – ' . $message : ''));
            $this->db->commit();
            Session::flash('success', 'Abschlussprüfung gespeichert und Wallet aktualisiert.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/admin/auftraege/' . $orderId);
    }

    private function systemChat(int $orderId, string $message): void
    {
        $q = $this->db->prepare('SELECT id FROM chats WHERE order_id=?');
        $q->execute([$orderId]);
        if ($id = $q->fetchColumn()) {
            $this->db->prepare("INSERT INTO chat_messages(chat_id,sender_type,message,created_at) VALUES(?,'system',?,NOW())")->execute([$id, $message]);
        }
    }
}
