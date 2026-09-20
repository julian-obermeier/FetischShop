<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\SchedulerService;
use PDO;

final class CronController
{
    public function __construct(private string $root, private PDO $db) {}

    public function run(Request $request, array $params): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');

        $configured = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key='cron_token' LIMIT 1");
        $configured->execute();
        $token = (string) ($configured->fetchColumn() ?: '');
        $provided = (string) ($params['token'] ?? '');

        if ($token === '' || $provided === '' || !hash_equals($token, $provided)) {
            Response::abort(404, 'Nicht gefunden.');
        }

        $result = (new SchedulerService($this->db, $this->root))->run();
        if (!empty($result['locked'])) {
            echo "FetischShop Cron: Ein Lauf ist bereits aktiv.\n";
            exit;
        }

        $labels = [
            'evidence_violations' => 'Fehlende Nachweise',
            'evidence_retake_violations' => 'Versäumte Nachaufnahmen',
            'task_violations' => 'Versäumte Zusatzaufgaben',
            'spontaneous_violations' => 'Versäumte Spontanfotos',
            'damage_violations' => 'Versäumte Schadensnachforderungen',
            'revision_violations' => 'Versäumte Revisionen',
            'shipping_violations' => 'Versäumte Versandschritte',
            'expired_private_offers' => 'Abgelaufene Privatangebote',
            'reminders' => 'Erzeugte Erinnerungen',
            'cleanup' => 'Bereinigte Datensätze',
        ];

        echo "FetischShop Cron erfolgreich\n";
        echo 'Zeit: ' . date('d.m.Y H:i:s') . " Europe/Berlin\n";
        foreach ($labels as $key => $label) {
            echo $label . ': ' . (int) ($result[$key] ?? 0) . "\n";
        }
    }
}
