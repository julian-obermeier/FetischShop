<?php
namespace App\Http\Controllers;

use App\Core\MigrationRunner;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use PDO;

final class UpdaterController
{
    public function __construct(private string $root, private PDO $db) {}

    public function index(): void
    {
        $runner = new MigrationRunner($this->db, $this->root);
        View::render($this->root, 'admin/updater', [
            'pageTitle' => 'Systemupdates',
            'pending' => array_map('basename', $runner->pending()),
            'history' => $runner->history(),
            'version' => trim((string) @file_get_contents($this->root . '/VERSION')) ?: 'unbekannt',
        ]);
    }

    public function run(Request $r): void
    {
        try {
            $done = (new MigrationRunner($this->db, $this->root))->run();
            Session::flash('success', $done ? 'Migrationen ausgeführt: ' . implode(', ', $done) : 'Keine ausstehenden Migrationen.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Update fehlgeschlagen: ' . $e->getMessage());
        }
        Response::redirect('/admin/system/update');
    }
}
