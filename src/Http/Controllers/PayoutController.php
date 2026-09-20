<?php
namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use PDO;
use RuntimeException;

final class PayoutController
{
    public function __construct(private string $root, private PDO $db, private Auth $auth) {}

    public function sellerIndex(): void
    {
        $seller = $this->auth->seller();
        $walletQ = $this->db->prepare('SELECT * FROM wallets WHERE seller_id=?');
        $walletQ->execute([$seller['id']]);
        $wallet = $walletQ->fetch();

        $methods = $this->db->prepare('SELECT * FROM payout_methods WHERE seller_id=? ORDER BY is_active DESC, id DESC');
        $methods->execute([$seller['id']]);

        $requests = $this->db->prepare('SELECT * FROM payout_requests WHERE seller_id=? ORDER BY requested_at DESC');
        $requests->execute([$seller['id']]);

        $settings = $this->settings([
            'payout_minimum' => '10.00',
            'payout_bank_enabled' => '1',
            'payout_paypal_enabled' => '1',
            'payout_bank_fee_type' => 'none',
            'payout_bank_fee_value' => '0',
            'payout_paypal_fee_type' => 'none',
            'payout_paypal_fee_value' => '0',
            'payout_days' => 'Montag,Donnerstag',
        ]);

        $entries = $this->db->prepare('SELECT we.* FROM wallet_entries we JOIN wallets w ON w.id=we.wallet_id WHERE w.seller_id=? ORDER BY we.created_at DESC LIMIT 100');
        $entries->execute([$seller['id']]);

        View::render($this->root, 'seller/wallet', [
            'pageTitle' => 'Wallet & Auszahlungen',
            'wallet' => $wallet,
            'entries' => $entries->fetchAll(),
            'methods' => $methods->fetchAll(),
            'requests' => $requests->fetchAll(),
            'payoutSettings' => $settings,
        ]);
    }

    public function saveMethod(Request $r): void
    {
        $seller = $this->auth->seller();
        $type = (string) $r->input('method_type');

        if (!in_array($type, ['bank', 'paypal'], true)) {
            Session::flash('error', 'Ungültige Auszahlungsmethode.');
            Response::redirect('/konto/wallet');
        }

        if ($type === 'bank') {
            $holder = trim((string) $r->input('account_holder'));
            $iban = strtoupper(preg_replace('/\s+/', '', (string) $r->input('iban')));
            $bic = strtoupper(trim((string) $r->input('bic')));
            if ($holder === '' || !preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
                Session::flash('error', 'Bitte gültigen Kontoinhaber und IBAN angeben.');
                Response::redirect('/konto/wallet');
            }
            $this->db->prepare('UPDATE payout_methods SET is_active=0,updated_at=NOW() WHERE seller_id=? AND method_type=\'bank\'')->execute([$seller['id']]);
            $q = $this->db->prepare("INSERT INTO payout_methods(seller_id,method_type,account_holder,iban,bic,is_active,created_at,updated_at) VALUES(?,'bank',?,?,?,1,NOW(),NOW())");
            $q->execute([$seller['id'], $holder, $iban, $bic ?: null]);
        } else {
            $identifier = trim((string) $r->input('paypal_identifier'));
            if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                Session::flash('error', 'Bitte eine gültige PayPal-E-Mail-Adresse angeben.');
                Response::redirect('/konto/wallet');
            }
            $this->db->prepare('UPDATE payout_methods SET is_active=0,updated_at=NOW() WHERE seller_id=? AND method_type=\'paypal\'')->execute([$seller['id']]);
            $q = $this->db->prepare("INSERT INTO payout_methods(seller_id,method_type,paypal_identifier,is_active,created_at,updated_at) VALUES(?,'paypal',?,1,NOW(),NOW())");
            $q->execute([$seller['id'], $identifier]);
        }

        Session::flash('success', 'Auszahlungsdaten wurden gespeichert.');
        Response::redirect('/konto/wallet');
    }

    public function request(Request $r): void
    {
        $seller = $this->auth->seller();
        $amount = round((float) str_replace(',', '.', (string) $r->input('amount')), 2);
        $methodId = (int) $r->input('payout_method_id');

        $this->db->beginTransaction();
        try {
            $open = $this->db->prepare("SELECT COUNT(*) FROM payout_requests WHERE seller_id=? AND status IN('requested','in_review','approved')");
            $open->execute([$seller['id']]);
            if ((int) $open->fetchColumn() > 0) {
                throw new RuntimeException('Es kann nur ein offener Auszahlungsantrag gleichzeitig bestehen.');
            }

            $walletQ = $this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');
            $walletQ->execute([$seller['id']]);
            $wallet = $walletQ->fetch();
            if (!$wallet) {
                throw new RuntimeException('Wallet nicht gefunden.');
            }

            $methodQ = $this->db->prepare('SELECT * FROM payout_methods WHERE id=? AND seller_id=? AND is_active=1');
            $methodQ->execute([$methodId, $seller['id']]);
            $method = $methodQ->fetch();
            if (!$method) {
                throw new RuntimeException('Auszahlungsmethode nicht gefunden.');
            }

            $settings = $this->settings([
                'payout_minimum' => '10.00',
                'payout_bank_enabled' => '1',
                'payout_paypal_enabled' => '1',
                'payout_bank_fee_type' => 'none',
                'payout_bank_fee_value' => '0',
                'payout_paypal_fee_type' => 'none',
                'payout_paypal_fee_value' => '0',
            ]);

            if ($settings['payout_' . $method['method_type'] . '_enabled'] !== '1') {
                throw new RuntimeException('Diese Auszahlungsmethode ist derzeit deaktiviert.');
            }
            if ($amount < (float) $settings['payout_minimum']) {
                throw new RuntimeException('Der Mindestbetrag beträgt ' . number_format((float) $settings['payout_minimum'], 2, ',', '.') . ' €.');
            }
            if ($amount > (float) $wallet['balance_available']) {
                throw new RuntimeException('Der beantragte Betrag übersteigt das verfügbare Guthaben.');
            }

            $feeType = $settings['payout_' . $method['method_type'] . '_fee_type'];
            $feeValue = (float) $settings['payout_' . $method['method_type'] . '_fee_value'];
            $fee = $feeType === 'fixed' ? $feeValue : ($feeType === 'percent' ? round($amount * $feeValue / 100, 2) : 0.0);
            $fee = min($amount, max(0, $fee));
            $net = $amount - $fee;

            $snapshot = json_encode([
                'method_type' => $method['method_type'],
                'account_holder' => $method['account_holder'],
                'iban' => $method['iban'],
                'bic' => $method['bic'],
                'paypal_identifier' => $method['paypal_identifier'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $q = $this->db->prepare("INSERT INTO payout_requests(seller_id,payout_method_id,requested_amount,fee_amount,net_amount,method_snapshot,status,requested_at) VALUES(?,?,?,?,?,?,'requested',NOW())");
            $q->execute([$seller['id'], $methodId, $amount, $fee, $net, $snapshot]);

            $this->db->commit();
            Session::flash('success', 'Auszahlungsantrag wurde eingereicht.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/konto/wallet');
    }

    public function withdraw(Request $r, array $p): void
    {
        $seller = $this->auth->seller();
        $q = $this->db->prepare("UPDATE payout_requests SET status='withdrawn',processed_at=NOW() WHERE id=? AND seller_id=? AND status='requested'");
        $q->execute([(int) $p['id'], $seller['id']]);
        Session::flash($q->rowCount() ? 'success' : 'error', $q->rowCount() ? 'Auszahlungsantrag wurde zurückgezogen.' : 'Der Antrag kann nicht mehr zurückgezogen werden.');
        Response::redirect('/konto/wallet');
    }

    public function adminIndex(): void
    {
        $rows = $this->db->query("SELECT pr.*,s.first_name,s.last_name,s.email FROM payout_requests pr JOIN sellers s ON s.id=pr.seller_id ORDER BY FIELD(pr.status,'requested','in_review','approved','paid','rejected','withdrawn'),pr.requested_at")->fetchAll();
        View::render($this->root, 'admin/payouts', ['pageTitle' => 'Auszahlungen', 'payouts' => $rows]);
    }

    public function adminUpdate(Request $r, array $p): void
    {
        $status = (string) $r->input('status');
        if (!in_array($status, ['in_review', 'approved', 'paid', 'rejected'], true)) {
            Response::abort(422, 'Ungültiger Status.');
        }

        $id = (int) $p['id'];
        $this->db->beginTransaction();
        try {
            $q = $this->db->prepare('SELECT * FROM payout_requests WHERE id=? FOR UPDATE');
            $q->execute([$id]);
            $payout = $q->fetch();
            if (!$payout) {
                Response::abort(404);
            }
            if (in_array($payout['status'], ['paid', 'withdrawn', 'rejected'], true)) {
                throw new RuntimeException('Abgeschlossene Auszahlungen können nicht erneut bearbeitet werden.');
            }

            if ($status === 'paid') {
                $walletQ = $this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');
                $walletQ->execute([$payout['seller_id']]);
                $wallet = $walletQ->fetch();
                if ((float) $wallet['balance_available'] < (float) $payout['requested_amount']) {
                    throw new RuntimeException('Das verfügbare Walletguthaben reicht nicht mehr aus.');
                }
                $newBalance = (float) $wallet['balance_available'] - (float) $payout['requested_amount'];
                $this->db->prepare('UPDATE wallets SET balance_available=?,updated_at=NOW() WHERE id=?')->execute([$newBalance, $wallet['id']]);
                $this->db->prepare("INSERT INTO wallet_entries(wallet_id,payout_request_id,entry_type,status,amount,balance_after,created_at) VALUES(?,?,'payout','paid',?,?,NOW())")->execute([$wallet['id'], $id, -1 * (float) $payout['requested_amount'], $newBalance]);
            }

            $this->db->prepare('UPDATE payout_requests SET status=?,processed_at=CASE WHEN ? IN (\'paid\',\'rejected\') THEN NOW() ELSE processed_at END WHERE id=?')->execute([$status, $status, $id]);
            $this->db->commit();
            Session::flash('success', 'Auszahlungsstatus wurde aktualisiert.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/admin/auszahlungen');
    }

    private function settings(array $defaults): array
    {
        $result = $defaults;
        if (!$defaults) {
            return $result;
        }
        $marks = implode(',', array_fill(0, count($defaults), '?'));
        $q = $this->db->prepare("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ($marks)");
        $q->execute(array_keys($defaults));
        foreach ($q->fetchAll() as $row) {
            $result[$row['setting_key']] = (string) $row['setting_value'];
        }
        return $result;
    }
}
