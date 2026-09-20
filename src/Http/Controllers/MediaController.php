<?php
namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Response;
use PDO;

final class MediaController
{
    public function __construct(private string $root, private PDO $db, private Auth $auth) {}

    public function evidence($r, array $p): void
    {
        $id = (int) $p['id'];
        $q = $this->db->prepare('SELECT e.*,o.seller_id,o.status order_status FROM evidences e JOIN orders o ON o.id=e.order_id WHERE e.id=?');
        $q->execute([$id]);
        $e = $q->fetch();
        if (!$e) {
            Response::abort(404);
        }

        $seller = $this->auth->seller();
        $admin = $this->auth->admin();
        if (!$admin) {
            if (!$seller || (int) $seller['id'] !== (int) $e['seller_id']) {
                Response::abort(403);
            }
            if ($e['order_status'] === 'rejected') {
                Response::abort(403, 'Nachweise abgelehnter Aufträge sind nur noch für den Betreiber zugänglich.');
            }
        }

        $this->stream((string) $e['file_path'], (string) $e['mime_type'], 'nachweis-' . $id, false);
    }

    public function digital($r, array $p): void
    {
        $id = (int) $p['id'];
        $q = $this->db->prepare("SELECT dv.*,o.seller_id,o.status order_status FROM digital_versions dv JOIN digital_submissions ds ON ds.id=dv.digital_submission_id JOIN digital_components dc ON dc.id=ds.digital_component_id JOIN order_components oc ON oc.id=dc.order_component_id JOIN orders o ON o.id=oc.order_id WHERE dv.id=?");
        $q->execute([$id]);
        $v = $q->fetch();
        if (!$v) {
            Response::abort(404);
        }

        $seller = $this->auth->seller();
        $admin = $this->auth->admin();
        if (!$admin && (!$seller || (int) $seller['id'] !== (int) $v['seller_id'])) {
            Response::abort(403);
        }
        if (!$admin && $v['order_status'] === 'rejected') {
            Response::abort(403);
        }

        $download = $admin && (($r->query['download'] ?? '') === '1');

        if ($v['content_type'] === 'text') {
            header('Content-Type: text/plain; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
            header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="digital-v' . (int) $v['version_no'] . '.txt"');
            echo (string) $v['text_content'];
            exit;
        }

        $name = $v['original_name'] ?: ('digital-v' . (int) $v['version_no']);
        $this->stream((string) $v['file_path'], (string) $v['mime_type'], $name, $download);
    }

    private function stream(string $path, string $mime, string $name, bool $download): void
    {
        if (!is_file($path)) {
            Response::abort(404);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name) ?: 'datei';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $safeName . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header("Content-Security-Policy: default-src 'none'; media-src 'self'");
        readfile($path);
        exit;
    }
}
