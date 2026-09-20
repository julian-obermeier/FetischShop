<?php
namespace App\Services;

use App\Core\Request;

final class OfferFormService
{
    public static function configuration(Request $r): array
    {
        $rules = self::lines((string) $r->input('rules_text'));

        $windowNames = self::array($r->input('evidence_name', []));
        $windowStarts = self::array($r->input('evidence_start', []));
        $windowEnds = self::array($r->input('evidence_end', []));
        $windowCounts = self::array($r->input('evidence_count', []));
        $windows = [];
        foreach ($windowNames as $i => $name) {
            $name = trim((string) $name);
            $start = trim((string) ($windowStarts[$i] ?? ''));
            $end = trim((string) ($windowEnds[$i] ?? ''));
            if ($name === '' || $start === '' || $end === '') {
                continue;
            }
            $windows[] = [
                'name' => $name,
                'start' => $start,
                'end' => $end,
                'required_count' => max(1, (int) ($windowCounts[$i] ?? 1)),
            ];
        }

        $labels = self::array($r->input('precheck_label', []));
        $descriptions = self::array($r->input('precheck_description', []));
        $counts = self::array($r->input('precheck_count', []));
        $requirements = [];
        foreach ($labels as $i => $label) {
            $label = trim((string) $label);
            if ($label === '') {
                continue;
            }
            $requirements[] = [
                'key' => 'req_' . ($i + 1),
                'label' => $label,
                'description' => trim((string) ($descriptions[$i] ?? '')),
                'required_count' => max(1, (int) ($counts[$i] ?? 1)),
            ];
        }

        $stepTitles = self::array($r->input('shipping_step_title', []));
        $stepInstructions = self::array($r->input('shipping_step_instructions', []));
        $stepTypes = self::array($r->input('shipping_step_type', []));
        $stepRequired = self::array($r->input('shipping_step_required', []));
        $steps = [];
        foreach ($stepTitles as $i => $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $type = (string) ($stepTypes[$i] ?? 'checkbox');
            if (!in_array($type, ['checkbox', 'photo', 'text', 'tracking_or_receipt'], true)) {
                $type = 'checkbox';
            }
            $steps[] = [
                'title' => $title,
                'instructions' => trim((string) ($stepInstructions[$i] ?? '')),
                'type' => $type,
                'required' => ((string) ($stepRequired[$i] ?? '1')) === '1',
            ];
        }

        return [
            'rules' => ['items' => $rules],
            'evidence' => ['windows' => $windows],
            'start_control' => ['requirements' => $requirements],
            'shipping' => ['instructions' => trim((string) $r->input('shipping_notes'))],
            'end_workflow' => ['steps' => $steps],
            'violation' => [
                'digital_violation_mode' => in_array((string) $r->input('digital_violation_mode'), ['extension', 'log_only'], true)
                    ? (string) $r->input('digital_violation_mode')
                    : 'extension',
                'physical_extension_days' => 1,
                'notes' => trim((string) $r->input('violation_notes')),
            ],
            'settings' => [
                'seller_option_changes_before_start' => true,
            ],
        ];
    }

    public static function options(Request $r): array
    {
        $names = self::array($r->input('option_name', []));
        $descriptions = self::array($r->input('option_description', []));
        $prices = self::array($r->input('option_price', []));
        $requirements = self::array($r->input('option_requirements', []));
        $sort = self::array($r->input('option_sort_order', []));
        $rows = [];

        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'name' => $name,
                'description' => trim((string) ($descriptions[$i] ?? '')),
                'price' => round((float) str_replace(',', '.', (string) ($prices[$i] ?? 0)), 2),
                'requirements' => ['text' => trim((string) ($requirements[$i] ?? ''))],
                'sort_order' => (int) ($sort[$i] ?? $i),
            ];
        }

        return $rows;
    }

    public static function components(Request $r, array $configuration): array
    {
        $categories = self::array($r->input('component_category_id', []));
        $types = self::array($r->input('component_type', []));
        $titles = self::array($r->input('component_title', []));
        $compensations = self::array($r->input('component_compensation', []));
        $models = self::array($r->input('component_fulfillment_model', []));
        $durations = self::array($r->input('component_duration_value', []));
        $units = self::array($r->input('component_duration_unit', []));
        $sort = self::array($r->input('component_sort_order', []));
        $formats = self::array($r->input('component_format_type', []));
        $minLengths = self::array($r->input('component_min_length', []));
        $maxLengths = self::array($r->input('component_max_length', []));
        $maxFiles = self::array($r->input('component_max_file_mb', []));
        $deadlines = self::array($r->input('component_deadline', []));
        $rows = [];

        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            $categoryId = (int) ($categories[$i] ?? 0);
            if ($title === '' || $categoryId < 1) {
                continue;
            }

            $type = (($types[$i] ?? 'physical') === 'digital') ? 'digital' : 'physical';
            $model = (string) ($models[$i] ?? ($type === 'digital' ? 'digital' : 'days'));
            $config = [];

            if ($type === 'physical') {
                $config['evidence'] = $configuration['evidence'];
                $config['start_control'] = $configuration['start_control'];
                $config['shipping'] = $configuration['shipping'];
                $config['end_workflow'] = $configuration['end_workflow'];
            } else {
                $format = (string) ($formats[$i] ?? 'mixed');
                if (!in_array($format, ['text', 'audio', 'video', 'mixed'], true)) {
                    $format = 'mixed';
                }
                $config['digital'] = [
                    'format_type' => $format,
                    'min_length' => max(0, (int) ($minLengths[$i] ?? 0)),
                    'max_length' => max(0, (int) ($maxLengths[$i] ?? 0)),
                    'max_file_mb' => max(1, min(500, (int) ($maxFiles[$i] ?? 150))),
                    'deadline' => trim((string) ($deadlines[$i] ?? '')) ?: null,
                ];
            }

            $durationRaw = trim((string) ($durations[$i] ?? ''));
            $rows[] = [
                'category_id' => $categoryId,
                'component_type' => $type,
                'title' => $title,
                'compensation' => round((float) str_replace(',', '.', (string) ($compensations[$i] ?? 0)), 2),
                'fulfillment_model' => $model,
                'duration_value' => $durationRaw === '' ? null : max(1, (int) $durationRaw),
                'duration_unit' => trim((string) ($units[$i] ?? '')) ?: null,
                'config' => $config,
                'sort_order' => (int) ($sort[$i] ?? $i),
            ];
        }

        return $rows;
    }

    public static function tasks(Request $r): array
    {
        $templateIds = self::array($r->input('offer_task_template_id', []));
        $titles = self::array($r->input('offer_task_title', []));
        $descriptions = self::array($r->input('offer_task_description', []));
        $categoryIds = self::array($r->input('offer_task_category_id', []));
        $scheduleTypes = self::array($r->input('offer_task_schedule_type', []));
        $offsets = self::array($r->input('offer_task_offset_minutes', []));
        $repeatEvery = self::array($r->input('offer_task_repeat_every_minutes', []));
        $repeatCount = self::array($r->input('offer_task_repeat_count', []));
        $sort = self::array($r->input('offer_task_sort_order', []));
        $rows = [];

        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $scheduleType = (string) ($scheduleTypes[$i] ?? 'once');
            if (!in_array($scheduleType, ['once', 'recurring', 'interval'], true)) {
                $scheduleType = 'once';
            }
            $rows[] = [
                'task_template_id' => !empty($templateIds[$i]) ? (int) $templateIds[$i] : null,
                'title' => $title,
                'config' => [
                    'description' => trim((string) ($descriptions[$i] ?? '')),
                    'category_id' => !empty($categoryIds[$i]) ? (int) $categoryIds[$i] : null,
                    'schedule_type' => $scheduleType,
                    'offset_minutes' => max(0, (int) ($offsets[$i] ?? 60)),
                    'repeat_every_minutes' => max(0, (int) ($repeatEvery[$i] ?? 0)),
                    'repeat_count' => max(0, min(100, (int) ($repeatCount[$i] ?? 0))),
                ],
                'sort_order' => (int) ($sort[$i] ?? $i),
            ];
        }

        return $rows;
    }

    private static function lines(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R+/', $value) ?: []),
            static fn(string $line): bool => $line !== ''
        ));
    }

    private static function array(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
