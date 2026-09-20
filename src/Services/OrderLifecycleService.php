<?php
namespace App\Services;

use PDO;
use RuntimeException;

final class OrderLifecycleService
{
    public function __construct(private PDO $db) {}

    public function enterReview(int $orderId): void
    {
        $orderQ = $this->db->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
        $orderQ->execute([$orderId]);
        $order = $orderQ->fetch();
        if (!$order) {
            throw new RuntimeException('Auftrag nicht gefunden.');
        }

        $existing = $this->db->prepare("SELECT id FROM wallet_entries WHERE order_id=? AND entry_type='order_review_hold' LIMIT 1");
        $existing->execute([$orderId]);

        if (!$existing->fetchColumn()) {
            $walletQ = $this->db->prepare('SELECT * FROM wallets WHERE seller_id=? FOR UPDATE');
            $walletQ->execute([$order['seller_id']]);
            $wallet = $walletQ->fetch();
            if (!$wallet) {
                throw new RuntimeException('Wallet nicht gefunden.');
            }

            $amount = round((float) $order['current_total'], 2);
            if ((float) $wallet['balance_reserved'] + 0.004 < $amount) {
                throw new RuntimeException('Reservierter Walletbetrag ist für die Abschlussprüfung inkonsistent.');
            }

            $reserved = round((float) $wallet['balance_reserved'] - $amount, 2);
            $inReview = round((float) $wallet['balance_in_review'] + $amount, 2);
            $this->db->prepare('UPDATE wallets SET balance_reserved=?,balance_in_review=?,updated_at=NOW() WHERE id=?')
                ->execute([$reserved, $inReview, $wallet['id']]);

            $this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,balance_after,metadata_json,created_at) VALUES(?,?,'order_review_hold','in_review',?,?,?,NOW())")
                ->execute([
                    $wallet['id'],
                    $orderId,
                    $amount,
                    $inReview,
                    json_encode(['reserved_after' => $reserved], JSON_UNESCAPED_UNICODE),
                ]);
        }

        $this->db->prepare("UPDATE orders SET status='reviewing',phase='review',updated_at=NOW() WHERE id=?")
            ->execute([$orderId]);
    }
}
