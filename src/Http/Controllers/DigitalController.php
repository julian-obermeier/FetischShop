<?php
namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PrivateStorage;
use App\Services\OrderLifecycleService;
use PDO;
use RuntimeException;
use DateTimeImmutable;

final class DigitalController
{
    public function __construct(private string $root, private PDO $db, private Auth $auth) {}

    public function upload(Request $r, array $p): void
    {
        $seller = $this->auth->seller();
        $orderId = (int) $p['id'];
        $componentId = (int) $p['componentId'];

        try {
            $q = $this->db->prepare("SELECT dc.*,oc.order_id,o.seller_id,o.status order_status FROM digital_components dc JOIN order_components oc ON oc.id=dc.order_component_id JOIN orders o ON o.id=oc.order_id WHERE dc.id=? AND o.id=? AND o.seller_id=?");
            $q->execute([$componentId, $orderId, $seller['id']]);
            $component = $q->fetch();
            if (!$component) {
                throw new RuntimeException('Digitaler Bestandteil nicht gefunden.');
            }
            if (in_array($component['status'], ['accepted', 'partially_accepted', 'rejected'], true)) {
                throw new RuntimeException('Dieser digitale Bestandteil ist bereits abschließend geprüft.');
            }

            $submission = $this->db->prepare('SELECT * FROM digital_submissions WHERE digital_component_id=? ORDER BY submission_no DESC LIMIT 1');
            $submission->execute([$componentId]);
            $submission = $submission->fetch();
            if (!$submission) {
                $this->db->prepare("INSERT INTO digital_submissions(digital_component_id,submission_no,status,created_at) VALUES(?,1,'draft',NOW())")->execute([$componentId]);
                $submissionId = (int) $this->db->lastInsertId();
            } else {
                $submissionId = (int) $submission['id'];
            }

            $vn = $this->db->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM digital_versions WHERE digital_submission_id=?');
            $vn->execute([$submissionId]);
            $versionNo = (int) $vn->fetchColumn();

            $type = (string) $r->input('content_type', 'file');
            if (!in_array($type, ['text', 'audio', 'video'], true)) {
                throw new RuntimeException('Ungültiger Inhaltstyp.');
            }

            $requirements = json_decode($component['requirements_json'] ?: '{}', true) ?: [];
            $text = null;
            $path = null;
            $original = null;
            $mime = null;
            $size = null;
            $sha = null;
            $meta = [];

            if ($type === 'text') {
                $text = trim((string) $r->input('text_content'));
                if ($text === '') {
                    throw new RuntimeException('Der Text darf nicht leer sein.');
                }
                $len = mb_strlen($text);
                if (!empty($requirements['min_length']) && $len < (int) $requirements['min_length']) {
                    throw new RuntimeException('Die geforderte Mindestlänge wurde noch nicht erreicht.');
                }
                if (!empty($requirements['max_length']) && $len > (int) $requirements['max_length']) {
                    throw new RuntimeException('Die maximale Textlänge wurde überschritten.');
                }
                $meta = ['characters' => $len];
            } else {
                $allowed = $type === 'audio'
                    ? ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/ogg']
                    : ['video/mp4', 'video/webm', 'video/quicktime'];

                $maxMb = max(1, min(500, (int) ($requirements['max_file_mb'] ?? 150)));
                $app = require $this->root . '/config/app.php';
                $file = (new PrivateStorage($app['private_storage']))->storeUploaded(
                    $r->files['digital_file'] ?? [],
                    'digital',
                    $allowed,
                    $maxMb * 1024 * 1024
                );
                $path = $file['path'];
                $original = $file['original_name'];
                $mime = $file['mime'];
                $size = $file['size'];
                $sha = $file['sha256'];
                $meta = ['technical_check' => 'mime_and_size', 'max_file_mb' => $maxMb];
            }

            $this->db->prepare('INSERT INTO digital_versions(digital_submission_id,version_no,content_type,file_path,original_name,mime_type,file_size,sha256,text_content,technical_metadata_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())')
                ->execute([
                    $submissionId,
                    $versionNo,
                    $type,
                    $path,
                    $original,
                    $mime,
                    $size,
                    $sha,
                    $text,
                    json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ]);

            $this->db->prepare("UPDATE digital_submissions SET status='draft' WHERE id=?")->execute([$submissionId]);
            $this->db->prepare("UPDATE digital_components SET status='draft',updated_at=NOW() WHERE id=?")->execute([$componentId]);

            Session::flash('success', 'Version V' . $versionNo . ' wurde gespeichert.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/konto/auftraege/' . $orderId);
    }

    public function submit(Request $r, array $p): void
    {
        $seller = $this->auth->seller();
        $orderId = (int) $p['id'];
        $componentId = (int) $p['componentId'];

        try {
            $this->db->beginTransaction();

            $q = $this->db->prepare("SELECT dc.*,oc.order_id,o.seller_id FROM digital_components dc JOIN order_components oc ON oc.id=dc.order_component_id JOIN orders o ON o.id=oc.order_id WHERE dc.id=? AND o.id=? AND o.seller_id=? FOR UPDATE");
            $q->execute([$componentId, $orderId, $seller['id']]);
            $component = $q->fetch();
            if (!$component) {
                throw new RuntimeException('Digitaler Bestandteil nicht gefunden.');
            }

            $sub = $this->db->prepare('SELECT * FROM digital_submissions WHERE digital_component_id=? ORDER BY submission_no DESC LIMIT 1 FOR UPDATE');
            $sub->execute([$componentId]);
            $submission = $sub->fetch();
            if (!$submission) {
                throw new RuntimeException('Es wurde noch keine Version hochgeladen.');
            }

            $count = $this->db->prepare('SELECT COUNT(*) FROM digital_versions WHERE digital_submission_id=?');
            $count->execute([$submission['id']]);
            if ((int) $count->fetchColumn() < 1) {
                throw new RuntimeException('Es wurde noch keine Version hochgeladen.');
            }

            $this->db->prepare("UPDATE digital_submissions SET status='submitted',finalized_at=NOW() WHERE id=?")->execute([$submission['id']]);
            $this->db->prepare("UPDATE digital_components SET status='submitted',updated_at=NOW() WHERE id=?")->execute([$componentId]);

            $revision = $this->db->prepare("SELECT id FROM revision_rounds WHERE digital_component_id=? AND status='open' ORDER BY round_no DESC LIMIT 1");
            $revision->execute([$componentId]);
            if ($revisionId = $revision->fetchColumn()) {
                $this->db->prepare("UPDATE revision_rounds SET status='submitted' WHERE id=?")->execute([$revisionId]);
            }

            $remaining = $this->db->prepare("SELECT COUNT(*) FROM digital_components dc JOIN order_components oc ON oc.id=dc.order_component_id WHERE oc.order_id=? AND dc.status NOT IN('submitted','accepted','partially_accepted','rejected')");
            $remaining->execute([$orderId]);
            if ((int) $remaining->fetchColumn() === 0) {
                $physical = $this->db->prepare("SELECT COUNT(*) FROM order_components WHERE order_id=? AND component_type='physical'");
                $physical->execute([$orderId]);
                if ((int) $physical->fetchColumn() === 0) {
                    $this->db->prepare("UPDATE orders SET status='digital_review',phase='review',updated_at=NOW() WHERE id=?")->execute([$orderId]);
                }
            }

            $this->systemChat($orderId, 'Eine digitale Abgabe wurde final eingereicht und wartet auf Prüfung.');
            $this->db->commit();
            Session::flash('success', 'Digitale Abgabe wurde final eingereicht.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/konto/auftraege/' . $orderId);
    }

    public function review(Request $r, array $p): void
    {
        $orderId = (int) $p['id'];
        $componentId = (int) $p['componentId'];
        $decision = (string) $r->input('decision');

        if (!in_array($decision, ['accepted', 'revision', 'partially_accepted', 'rejected'], true)) {
            Response::abort(422, 'Ungültige Prüfentscheidung.');
        }

        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare("SELECT dc.*,oc.order_id,oc.id order_component_id FROM digital_components dc JOIN order_components oc ON oc.id=dc.order_component_id WHERE dc.id=? AND oc.order_id=? FOR UPDATE");
            $q->execute([$componentId, $orderId]);
            $component = $q->fetch();
            if (!$component) {
                throw new RuntimeException('Digitaler Bestandteil nicht gefunden.');
            }

            if ($decision === 'revision') {
                $open = $this->db->prepare("SELECT COUNT(*) FROM revision_rounds WHERE digital_component_id=? AND status IN('open','submitted')");
                $open->execute([$componentId]);
                if ((int) $open->fetchColumn() > 0) {
                    throw new RuntimeException('Es besteht bereits eine aktive Revisionsrunde.');
                }

                $deadlineRaw = trim((string) $r->input('deadline'));
                if ($deadlineRaw === '') {
                    throw new RuntimeException('Für die Revision ist eine Deadline erforderlich.');
                }
                $deadline = new DateTimeImmutable($deadlineRaw);

                $next = $this->db->prepare('SELECT COALESCE(MAX(round_no),0)+1 FROM revision_rounds WHERE digital_component_id=?');
                $next->execute([$componentId]);
                $roundNo = (int) $next->fetchColumn();

                $this->db->prepare("INSERT INTO revision_rounds(digital_component_id,round_no,deadline,grace_ends_at,status,created_at) VALUES(?,?,?,?, 'open',NOW())")
                    ->execute([$componentId, $roundNo, $deadline->format('Y-m-d H:i:s'), $deadline->modify('+1 hour')->format('Y-m-d H:i:s')]);
                $roundId = (int) $this->db->lastInsertId();

                $lines = preg_split('/\R+/', trim((string) $r->input('revision_items'))) ?: [];
                $created = 0;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $this->db->prepare("INSERT INTO revision_items(revision_round_id,description,priority,status,created_at,updated_at) VALUES(?,?,'normal','open',NOW(),NOW())")
                        ->execute([$roundId, $line]);
                    $created++;
                }
                if ($created === 0) {
                    throw new RuntimeException('Bitte mindestens einen konkreten Änderungspunkt angeben.');
                }

                $this->db->prepare("UPDATE digital_components SET status='revision_required',review_note=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?")
                    ->execute([trim((string) $r->input('note')) ?: null, $componentId]);
                $this->db->prepare("UPDATE orders SET status='running',phase='execution',updated_at=NOW() WHERE id=?")->execute([$orderId]);
                $this->systemChat($orderId, 'Revision für einen digitalen Bestandteil angefordert. Runde ' . $roundNo . '.');
            } else {
                $approved = null;
                if ($decision === 'accepted') {
                    $approved = (float) $component['compensation'];
                } elseif ($decision === 'partially_accepted') {
                    $approved = round((float) str_replace(',', '.', (string) $r->input('approved_amount')), 2);
                    if ($approved < 0 || $approved > (float) $component['compensation']) {
                        throw new RuntimeException('Der Teilfreigabebetrag ist ungültig.');
                    }
                } elseif ($decision === 'rejected') {
                    $approved = 0.0;
                }

                $status = $decision;
                $this->db->prepare('UPDATE digital_components SET status=?,approved_amount=?,review_note=?,reviewed_at=NOW(),updated_at=NOW() WHERE id=?')
                    ->execute([$status, $approved, trim((string) $r->input('note')) ?: null, $componentId]);

                $activeRound = $this->db->prepare("SELECT id FROM revision_rounds WHERE digital_component_id=? AND status IN('open','submitted') ORDER BY round_no DESC LIMIT 1");
                $activeRound->execute([$componentId]);
                if ($roundId = $activeRound->fetchColumn()) {
                    $this->db->prepare("UPDATE revision_rounds SET status='completed',completed_at=NOW() WHERE id=?")->execute([$roundId]);
                }

                $this->refreshDigitalOrderComponent((int) $component['order_component_id']);
                $this->advanceOrderReviewIfReady($orderId);

                $this->systemChat($orderId, 'Digitale Endprüfung: ' . $decision . '.');
            }

            $this->db->commit();
            Session::flash('success', 'Digitale Prüfentscheidung gespeichert.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Session::flash('error', $e->getMessage());
        }

        Response::redirect('/admin/auftraege/' . $orderId);
    }

    public function revisionItem(Request $r, array $p): void
    {
        $orderId = (int) $p['id'];
        $itemId = (int) $p['itemId'];
        $status = (string) $r->input('status');
        if (!in_array($status, ['open', 'done', 'insufficient', 'change_again'], true)) {
            Response::abort(422, 'Ungültiger Revisionsstatus.');
        }

        $q = $this->db->prepare("UPDATE revision_items ri JOIN revision_rounds rr ON rr.id=ri.revision_round_id JOIN digital_components dc ON dc.id=rr.digital_component_id JOIN order_components oc ON oc.id=dc.order_component_id SET ri.status=?,ri.updated_at=NOW() WHERE ri.id=? AND oc.order_id=?");
        $q->execute([$status, $itemId, $orderId]);
        Session::flash('success', 'Revisionspunkt aktualisiert.');
        Response::redirect('/admin/auftraege/' . $orderId);
    }

    private function refreshDigitalOrderComponent(int $orderComponentId): void
    {
        $q = $this->db->prepare("SELECT COUNT(*) total,SUM(status='rejected') rejected,SUM(status='partially_accepted') partial,SUM(status NOT IN('accepted','partially_accepted','rejected')) unresolved FROM digital_components WHERE order_component_id=?");
        $q->execute([$orderComponentId]);
        $state = $q->fetch();
        if (!$state || (int) $state['total'] === 0 || (int) $state['unresolved'] > 0) {
            return;
        }

        $status = (int) $state['rejected'] > 0
            ? 'rejected'
            : ((int) $state['partial'] > 0 ? 'partially_accepted' : 'accepted');

        $this->db->prepare('UPDATE order_components SET status=?,updated_at=NOW() WHERE id=?')
            ->execute([$status, $orderComponentId]);
    }

    private function advanceOrderReviewIfReady(int $orderId): void
    {
        $digitalOpen = $this->db->prepare("SELECT COUNT(*) FROM digital_components dc JOIN order_components oc ON oc.id=dc.order_component_id WHERE oc.order_id=? AND dc.status NOT IN('accepted','partially_accepted','rejected')");
        $digitalOpen->execute([$orderId]);
        if ((int) $digitalOpen->fetchColumn() > 0) {
            return;
        }

        $physicalOpen = $this->db->prepare("SELECT COUNT(*) FROM order_components WHERE order_id=? AND component_type='physical' AND status<>'received'");
        $physicalOpen->execute([$orderId]);
        if ((int) $physicalOpen->fetchColumn() > 0) {
            return;
        }

        (new OrderLifecycleService($this->db))->enterReview($orderId);
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
