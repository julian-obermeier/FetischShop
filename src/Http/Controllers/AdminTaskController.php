<?php
namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use PDO;

final class AdminTaskController
{
    public function __construct(private string $root, private PDO $db) {}

    public function index(): void
    {
        $rows = $this->db->query('SELECT * FROM task_templates ORDER BY updated_at DESC,title')->fetchAll();
        View::render($this->root, 'admin/tasks', ['pageTitle' => 'Aufgabenbibliothek', 'templates' => $rows]);
    }

    public function save(Request $r): void
    {
        $id = (int) $r->input('id');
        $title = trim((string) $r->input('title'));
        if ($title === '') {
            Session::flash('error', 'Bitte einen Titel für die Aufgabenvorlage angeben.');
            Response::redirect('/admin/aufgaben');
        }

        $keys = $this->arr($r->input('field_key', []));
        $labels = $this->arr($r->input('field_label', []));
        $types = $this->arr($r->input('field_type', []));
        $required = $this->arr($r->input('field_required', []));
        $mins = $this->arr($r->input('field_min', []));
        $maxs = $this->arr($r->input('field_max', []));
        $options = $this->arr($r->input('field_options', []));
        $fields = [];

        foreach ($labels as $i => $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $type = (string) ($types[$i] ?? 'text');
            if (!in_array($type, ['text','number','scale','select','boolean','textarea'], true)) {
                $type = 'text';
            }
            $key = trim((string) ($keys[$i] ?? ''));
            if ($key === '') {
                $key = 'feld_' . ($i + 1);
            }
            $key = preg_replace('/[^a-z0-9_]/', '_', mb_strtolower($key));
            $field = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => ((string) ($required[$i] ?? '0')) === '1',
            ];
            if (in_array($type, ['number','scale'], true)) {
                if (($mins[$i] ?? '') !== '') $field['min'] = (int) $mins[$i];
                if (($maxs[$i] ?? '') !== '') $field['max'] = (int) $maxs[$i];
            }
            if ($type === 'select') {
                $field['options'] = array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) ($options[$i] ?? '')) ?: [])));
            }
            $fields[] = $field;
        }

        $photoLabels = $this->arr($r->input('photo_label', []));
        $photoCounts = $this->arr($r->input('photo_count', []));
        $photos = [];
        foreach ($photoLabels as $i => $label) {
            $label = trim((string) $label);
            if ($label === '') continue;
            $photos[] = ['label' => $label, 'required_count' => max(1, (int) ($photoCounts[$i] ?? 1))];
        }

        $violation = [
            'missing' => $r->input('violation_missing', 'one_violation') === 'none' ? 'none' : 'one_violation',
        ];

        $data = [
            $title,
            trim((string) $r->input('description')),
            json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($photos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            max(1, (int) $r->input('deadline_minutes', 60)),
            round((float) str_replace(',', '.', (string) $r->input('compensation', 0)), 2),
            json_encode($violation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if ($id) {
            $this->db->prepare('UPDATE task_templates SET title=?,description=?,fields_json=?,photos_json=?,deadline_minutes=?,compensation=?,violation_json=?,is_active=?,updated_at=NOW() WHERE id=?')
                ->execute([...$data, (int) !!$r->input('is_active'), $id]);
        } else {
            $this->db->prepare('INSERT INTO task_templates(title,description,fields_json,photos_json,deadline_minutes,compensation,violation_json,is_active,created_at,updated_at) VALUES(?,?,?,?,?,?,?,1,NOW(),NOW())')
                ->execute($data);
        }

        Session::flash('success', 'Aufgabenvorlage gespeichert.');
        Response::redirect('/admin/aufgaben');
    }

    private function arr(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
