<?php
namespace App\Services;

use PDO;
use RuntimeException;
use DateTimeImmutable;
use DateTimeZone;

final class OrderService
{
    public function __construct(private PDO $db) {}

    public function accept(int $sellerId, int $offerId, array $optionIds = [], bool $rightsAccepted = false): int
    {
        $this->db->beginTransaction();

        try {
            $q = $this->db->prepare("SELECT o.id offer_id,o.seller_id private_seller_id,o.is_private,o.acceptance_deadline,o.title offer_title,ov.*,c.id category_id,c.name category_name,c.is_digital FROM offers o JOIN offer_versions ov ON ov.id=o.current_version_id JOIN categories c ON c.id=o.category_id WHERE o.id=? AND o.status='active' FOR UPDATE");
            $q->execute([$offerId]);
            $offer = $q->fetch();

            if (!$offer) {
                throw new RuntimeException('Angebot ist nicht mehr verfügbar.');
            }
            if ((int) $offer['is_private'] === 1 && (int) $offer['private_seller_id'] !== $sellerId) {
                throw new RuntimeException('Dieses Angebot ist nicht für dein Konto bestimmt.');
            }
            if ($offer['acceptance_deadline'] && strtotime($offer['acceptance_deadline']) < time()) {
                throw new RuntimeException('Die Annahmefrist ist abgelaufen.');
            }

            $componentQ = $this->db->prepare("SELECT oc.*,c.name category_name,c.is_digital category_is_digital FROM offer_components oc JOIN categories c ON c.id=oc.category_id WHERE oc.offer_version_id=? ORDER BY oc.sort_order,oc.id");
            $componentQ->execute([$offer['id']]);
            $definitions = $componentQ->fetchAll();

            if (!$definitions) {
                $definitions = [[
                    'category_id' => (int) $offer['category_id'],
                    'category_name' => $offer['category_name'],
                    'category_is_digital' => (int) $offer['is_digital'],
                    'component_type' => (int) $offer['is_digital'] === 1 ? 'digital' : 'physical',
                    'title' => $offer['title'],
                    'compensation' => (float) $offer['compensation'],
                    'fulfillment_model' => $offer['fulfillment_model'],
                    'duration_value' => $offer['duration_value'],
                    'duration_unit' => $offer['duration_unit'],
                    'config_json' => json_encode([
                        'evidence' => json_decode($offer['evidence_json'] ?: '[]', true) ?: [],
                        'start_control' => json_decode($offer['start_control_json'] ?: '[]', true) ?: [],
                        'shipping' => json_decode($offer['shipping_json'] ?: '[]', true) ?: [],
                        'end_workflow' => json_decode($offer['end_workflow_json'] ?: '[]', true) ?: [],
                        'settings' => json_decode($offer['settings_json'] ?: '[]', true) ?: [],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'sort_order' => 0,
                ]];
            }

            $categoryIds = [];
            $hasPhysical = false;
            $hasDigital = false;
            foreach ($definitions as $definition) {
                $categoryIds[(int) $definition['category_id']] = true;
                $type = $definition['component_type'] === 'digital' || (int) ($definition['category_is_digital'] ?? 0) === 1 ? 'digital' : 'physical';
                $hasDigital = $hasDigital || $type === 'digital';
                $hasPhysical = $hasPhysical || $type === 'physical';
            }

            foreach (array_keys($categoryIds) as $categoryId) {
                $block = $this->db->prepare("SELECT ord.order_number FROM orders ord JOIN order_components oc ON oc.order_id=ord.id WHERE ord.seller_id=? AND oc.category_id=? AND ord.status NOT IN('rejected','cancelled','paid','archived') LIMIT 1 FOR UPDATE");
                $block->execute([$sellerId, $categoryId]);
                if ($block->fetch()) {
                    throw new RuntimeException('Mindestens eine Kategorie dieses Angebots ist bereits durch einen aktiven Auftrag belegt.');
                }
            }

            if ($hasDigital && !$rightsAccepted) {
                throw new RuntimeException('Die Rechtevereinbarung muss vor Annahme eines Angebots mit digitalen Bestandteilen bestätigt werden.');
            }

            $selected = [];
            $optionsTotal = 0.0;
            if ($optionIds) {
                $ids = array_values(array_unique(array_map('intval', $optionIds)));
                $marks = implode(',', array_fill(0, count($ids), '?'));
                $op = $this->db->prepare("SELECT * FROM offer_options WHERE offer_version_id=? AND is_active=1 AND id IN($marks)");
                $op->execute(array_merge([$offer['id']], $ids));
                $selected = $op->fetchAll();
                foreach ($selected as $option) {
                    $optionsTotal += (float) $option['price'];
                }
            }

            $number = (new OrderNumberService($this->db))->next();
            $total = (float) $offer['compensation'] + $optionsTotal;
            $snapshot = json_encode([
                'offer_version_id' => (int) $offer['id'],
                'title' => $offer['title'],
                'description' => $offer['description'],
                'compensation' => (float) $offer['compensation'],
                'fulfillment_model' => $offer['fulfillment_model'],
                'duration_value' => $offer['duration_value'],
                'duration_unit' => $offer['duration_unit'],
                'rules' => json_decode($offer['rules_json'] ?: '[]', true),
                'evidence' => json_decode($offer['evidence_json'] ?: '[]', true),
                'start_control' => json_decode($offer['start_control_json'] ?: '[]', true),
                'shipping' => json_decode($offer['shipping_json'] ?: '[]', true),
                'end_workflow' => json_decode($offer['end_workflow_json'] ?: '[]', true),
                'violation' => json_decode($offer['violation_json'] ?: '[]', true),
                'settings' => json_decode($offer['settings_json'] ?: '[]', true),
                'components' => $definitions,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $initialStatus = $hasPhysical ? 'precheck' : 'running';
            $initialPhase = $hasPhysical ? 'preparation' : 'execution';
            $startedAtSql = $hasPhysical ? 'NULL' : 'NOW()';

            $insert = $this->db->prepare("INSERT INTO orders(order_number,seller_id,offer_id,offer_version_id,status,phase,accepted_at,started_at,base_compensation,current_total,config_snapshot,created_at,updated_at) VALUES(?,?,?,?,?,?,NOW(),$startedAtSql,?,?,?,NOW(),NOW())");
            $insert->execute([$number, $sellerId, $offerId, $offer['id'], $initialStatus, $initialPhase, $offer['compensation'], $total, $snapshot]);
            $orderId = (int) $this->db->lastInsertId();

            $this->db->prepare("INSERT INTO order_runs(order_id,run_no,status,started_at,created_at) VALUES(?,1,?,?,NOW())")
                ->execute([$orderId, $hasPhysical ? 'preparation' : 'running', $hasPhysical ? null : date('Y-m-d H:i:s')]);
            $runId = (int) $this->db->lastInsertId();

            foreach ($definitions as $idx => $definition) {
                $type = $definition['component_type'] === 'digital' || (int) ($definition['category_is_digital'] ?? 0) === 1 ? 'digital' : 'physical';
                $config = json_decode($definition['config_json'] ?: '[]', true) ?: [];
                $componentStatus = $type === 'digital' ? 'running' : 'preparation';

                $this->db->prepare("INSERT INTO order_components(order_id,category_id,component_type,title,compensation,fulfillment_model,duration_value,duration_unit,status,config_json,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())")
                    ->execute([
                        $orderId,
                        (int) $definition['category_id'],
                        $type,
                        (string) $definition['title'],
                        (float) $definition['compensation'],
                        (string) ($definition['fulfillment_model'] ?? 'once'),
                        $definition['duration_value'] !== null ? (int) $definition['duration_value'] : null,
                        $definition['duration_unit'] ?? null,
                        $componentStatus,
                        json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        (int) ($definition['sort_order'] ?? $idx),
                    ]);
                $componentId = (int) $this->db->lastInsertId();

                if ($type === 'digital') {
                    $this->createDigitalParts($componentId, $definition, $config);
                } else {
                    $this->createPrecheckRequirements($orderId, $runId, $componentId, (string) $definition['category_name'], $config);
                }
            }

            foreach ($selected as $option) {
                $this->db->prepare('INSERT INTO order_options(order_id,offer_option_id,name,price,config_snapshot,created_at) VALUES(?,?,?,?,?,NOW())')
                    ->execute([$orderId, $option['id'], $option['name'], $option['price'], $option['requirements_json']]);
            }

            if ($hasDigital) {
                $this->db->prepare('INSERT INTO rights_acceptances(order_id,seller_id,clause_version,accepted_at) VALUES(?,?,?,NOW())')
                    ->execute([$orderId, $sellerId, '2026-09-20']);
            }

            $walletQ = $this->db->prepare('SELECT id FROM wallets WHERE seller_id=? FOR UPDATE');
            $walletQ->execute([$sellerId]);
            $walletId = (int) $walletQ->fetchColumn();
            $this->db->prepare('UPDATE wallets SET balance_reserved=balance_reserved+?,updated_at=NOW() WHERE id=?')->execute([$total, $walletId]);
            $this->db->prepare("INSERT INTO wallet_entries(wallet_id,order_id,entry_type,status,amount,created_at) VALUES(?,?,'order_reservation','reserved',?,NOW())")
                ->execute([$walletId, $orderId, $total]);

            $this->db->prepare('INSERT INTO chats(order_id,is_readonly,created_at) VALUES(?,0,NOW())')->execute([$orderId]);
            $chatId = (int) $this->db->lastInsertId();
            $this->db->prepare("INSERT INTO chat_messages(chat_id,sender_type,message,created_at) VALUES(?,'system',?,NOW())")
                ->execute([$chatId, 'Auftrag #' . $number . ' wurde angenommen.']);

            $this->db->prepare("INSERT INTO system_events(seller_id,order_id,event_type,actor_type,actor_id,payload_json,created_at) VALUES(?,?,'order_accepted','seller',?,?,NOW())")
                ->execute([$sellerId, $orderId, $sellerId, json_encode(['order_number' => $number, 'total' => $total, 'components' => count($definitions)], JSON_UNESCAPED_UNICODE)]);

            if (!$hasPhysical) {
                $this->instantiateOfferTasks($orderId, (int) $offer['id'], new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')));
            }

            if ((int) $offer['is_private'] === 1) {
                $this->db->prepare("UPDATE offers SET private_offer_status='accepted',updated_at=NOW() WHERE id=?")->execute([$offerId]);
            }

            $this->db->commit();
            return $orderId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function startAfterPrecheck(int $orderId): void
    {
        $this->db->beginTransaction();

        try {
            $orderQ = $this->db->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
            $orderQ->execute([$orderId]);
            $order = $orderQ->fetch();
            if (!$order) {
                throw new RuntimeException('Auftrag nicht gefunden.');
            }
            if ($order['started_at']) {
                throw new RuntimeException('Die physischen Bestandteile wurden bereits gestartet.');
            }

            $runQ = $this->db->prepare('SELECT id FROM order_runs WHERE order_id=? ORDER BY run_no DESC LIMIT 1');
            $runQ->execute([$orderId]);
            $runId = (int) $runQ->fetchColumn();

            $physicalQ = $this->db->prepare("SELECT oc.*,c.name category_name FROM order_components oc JOIN categories c ON c.id=oc.category_id WHERE oc.order_id=? AND oc.component_type='physical' ORDER BY oc.sort_order,oc.id");
            $physicalQ->execute([$orderId]);
            $physical = $physicalQ->fetchAll();
            if (!$physical) {
                throw new RuntimeException('Dieser Auftrag besitzt keine physischen Bestandteile mit Vorabkontrolle.');
            }

            foreach ($physical as $component) {
                $itemQ = $this->db->prepare('SELECT COUNT(*) FROM order_items WHERE order_id=? AND order_run_id=? AND order_component_id=?');
                $itemQ->execute([$orderId, $runId, $component['id']]);
                if ((int) $itemQ->fetchColumn() < 1) {
                    throw new RuntimeException('Für „' . $component['title'] . '“ wurde noch kein konkreter Artikel festgelegt.');
                }

                $requirementsQ = $this->db->prepare('SELECT * FROM precheck_requirements WHERE order_id=? AND order_run_id=? AND order_component_id=? ORDER BY sort_order,id');
                $requirementsQ->execute([$orderId, $runId, $component['id']]);
                $requirements = $requirementsQ->fetchAll();

                foreach ($requirements as $requirement) {
                    $acceptedQ = $this->db->prepare("SELECT COUNT(*) FROM evidences WHERE order_id=? AND order_run_id=? AND precheck_requirement_id=? AND evidence_type='precheck' AND review_status='accepted'");
                    $acceptedQ->execute([$orderId, $runId, $requirement['id']]);
                    if ((int) $acceptedQ->fetchColumn() < (int) $requirement['required_count']) {
                        throw new RuntimeException('Vorabkontrolle „' . $requirement['label'] . '“ für „' . $component['title'] . '“ ist noch nicht vollständig akzeptiert.');
                    }
                }
            }

            $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
            $startDate = $now->setTime(0, 0);

            foreach ($physical as $component) {
                $config = json_decode($component['config_json'] ?: '[]', true) ?: [];
                $evidence = $config['evidence'] ?? [];
                if (!$evidence) {
                    $snapshot = json_decode($order['config_snapshot'] ?: '[]', true) ?: [];
                    $evidence = $snapshot['evidence'] ?? [];
                }
                $windows = $evidence['windows'] ?? [
                    ['name' => 'Morgen', 'start' => '06:00', 'end' => '10:00', 'required_count' => 1],
                    ['name' => 'Mittag', 'start' => '12:00', 'end' => '16:00', 'required_count' => 1],
                    ['name' => 'Abend', 'start' => '18:00', 'end' => '23:59', 'required_count' => 1],
                ];

                $future = false;
                foreach ($windows as $window) {
                    [$h, $m] = array_map('intval', explode(':', (string) $window['start']));
                    if ($startDate->setTime($h, $m) > $now) {
                        $future = true;
                        break;
                    }
                }

                $this->db->prepare("UPDATE order_components SET status='running',updated_at=NOW() WHERE id=?")->execute([$component['id']]);

                if ($component['fulfillment_model'] === 'days') {
                    $first = $future ? $startDate : $startDate->modify('+1 day');

                    if (!$future) {
                        $this->db->prepare("INSERT INTO order_days(order_id,order_run_id,order_component_id,day_no,calendar_date,day_type,status,created_at) VALUES(?,?,?,NULL,?,'start','active',NOW())")
                            ->execute([$orderId, $runId, $component['id'], $startDate->format('Y-m-d')]);
                    }

                    $days = max(1, (int) $component['duration_value']);
                    for ($n = 1; $n <= $days; $n++) {
                        $date = $first->modify('+' . ($n - 1) . ' day');
                        $this->db->prepare("INSERT INTO order_days(order_id,order_run_id,order_component_id,day_no,calendar_date,day_type,status,created_at) VALUES(?,?,?,?,?,'regular','planned',NOW())")
                            ->execute([$orderId, $runId, $component['id'], $n, $date->format('Y-m-d')]);
                        $dayId = (int) $this->db->lastInsertId();

                        foreach ($windows as $window) {
                            [$sh, $sm] = array_map('intval', explode(':', (string) $window['start']));
                            [$eh, $em] = array_map('intval', explode(':', (string) $window['end']));
                            $from = $date->setTime($sh, $sm);
                            $to = $date->setTime($eh, $em);
                            $this->db->prepare("INSERT INTO evidence_windows(order_day_id,name,starts_at,ends_at,grace_ends_at,required_count,config_json,status,created_at) VALUES(?,?,?,?,?,?,?,'open',NOW())")
                                ->execute([
                                    $dayId,
                                    (string) $window['name'],
                                    $from->format('Y-m-d H:i:s'),
                                    $to->format('Y-m-d H:i:s'),
                                    $to->modify('+1 hour')->format('Y-m-d H:i:s'),
                                    (int) ($window['required_count'] ?? 1),
                                    json_encode($window, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                ]);
                        }
                    }
                }
            }

            $this->db->prepare("UPDATE orders SET status='running',phase='execution',started_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$orderId]);
            $this->db->prepare("UPDATE order_runs SET status='running',started_at=COALESCE(started_at,NOW()) WHERE id=?")->execute([$runId]);
            $this->instantiateOfferTasks($orderId, (int) $order['offer_version_id'], $now);

            $chatQ = $this->db->prepare('SELECT id FROM chats WHERE order_id=?');
            $chatQ->execute([$orderId]);
            if ($chatId = $chatQ->fetchColumn()) {
                $this->db->prepare("INSERT INTO chat_messages(chat_id,sender_type,message,created_at) VALUES(?,'system','Alle physischen Vorabkontrollen wurden freigegeben. Die Durchführung wurde gestartet.',NOW())")
                    ->execute([$chatId]);
            }

            $this->db->prepare("INSERT INTO system_events(seller_id,order_id,event_type,actor_type,payload_json,created_at) SELECT seller_id,id,'order_started','admin','{}',NOW() FROM orders WHERE id=?")
                ->execute([$orderId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function instantiateOfferTasks(int $orderId, int $offerVersionId, DateTimeImmutable $start): void
    {
        $q = $this->db->prepare('SELECT * FROM offer_tasks WHERE offer_version_id=? ORDER BY sort_order,id');
        $q->execute([$offerVersionId]);

        foreach ($q->fetchAll() as $offerTask) {
            $exists = $this->db->prepare('SELECT id FROM tasks WHERE order_id=? AND offer_task_id=?');
            $exists->execute([$orderId, $offerTask['id']]);
            if ($exists->fetchColumn()) {
                continue;
            }

            $config = json_decode($offerTask['config_json'] ?: '[]', true) ?: [];
            $description = trim((string) ($config['description'] ?? ''));
            $scheduleType = (string) ($config['schedule_type'] ?? 'once');
            if (!in_array($scheduleType, ['once', 'recurring', 'interval'], true)) {
                $scheduleType = 'once';
            }

            $componentId = null;
            if (!empty($config['order_component_id'])) {
                $componentId = (int) $config['order_component_id'];
            } elseif (!empty($config['category_id'])) {
                $component = $this->db->prepare('SELECT id FROM order_components WHERE order_id=? AND category_id=? ORDER BY id LIMIT 1');
                $component->execute([$orderId, (int) $config['category_id']]);
                $componentId = $component->fetchColumn() ?: null;
            }

            $taskConfig = [
                'fields' => is_array($config['fields'] ?? null) ? $config['fields'] : [],
                'photos' => is_array($config['photos'] ?? null) ? $config['photos'] : [],
                'violation' => is_array($config['violation'] ?? null) ? $config['violation'] : [],
            ];

            $this->db->prepare('INSERT INTO tasks(order_id,order_component_id,task_template_id,offer_task_id,title,description,schedule_type,config_json,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())')
                ->execute([
                    $orderId,
                    $componentId,
                    $offerTask['task_template_id'] !== null ? (int) $offerTask['task_template_id'] : null,
                    (int) $offerTask['id'],
                    (string) $offerTask['title'],
                    $description ?: null,
                    $scheduleType,
                    json_encode($taskConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            $taskId = (int) $this->db->lastInsertId();

            $offsets = [];
            if (isset($config['offset_minutes']) && is_numeric($config['offset_minutes'])) {
                $offsets[] = (int) $config['offset_minutes'];
            }
            if (is_array($config['offsets_minutes'] ?? null)) {
                foreach ($config['offsets_minutes'] as $offset) {
                    if (is_numeric($offset)) {
                        $offsets[] = (int) $offset;
                    }
                }
            }

            $repeatEvery = max(0, (int) ($config['repeat_every_minutes'] ?? 0));
            $repeatCount = max(0, min(100, (int) ($config['repeat_count'] ?? 0)));
            if ($repeatEvery > 0 && $repeatCount > 0) {
                for ($i = 1; $i <= $repeatCount; $i++) {
                    $offsets[] = $repeatEvery * $i;
                }
            }

            if (!$offsets) {
                $offsets[] = max(0, (int) ($config['deadline_minutes'] ?? 60));
            }

            $offsets = array_values(array_unique($offsets));
            sort($offsets);

            foreach ($offsets as $offset) {
                $due = $start->modify(($offset >= 0 ? '+' : '') . $offset . ' minutes');
                $this->db->prepare("INSERT INTO task_executions(task_id,due_at,grace_ends_at,review_status,created_at) VALUES(?,?,?,'open',NOW())")
                    ->execute([$taskId, $due->format('Y-m-d H:i:s'), $due->modify('+1 hour')->format('Y-m-d H:i:s')]);
            }
        }
    }

    private function createDigitalParts(int $componentId, array $definition, array $config): void
    {
        $defs = $config['digital_components'] ?? [];
        if (!$defs) {
            $digital = $config['digital'] ?? [];
            $defs = [[
                'title' => $definition['title'],
                'format_type' => $digital['format_type'] ?? 'mixed',
                'compensation' => (float) $definition['compensation'],
                'requirements' => $digital,
                'deadline' => $digital['deadline'] ?? null,
            ]];
        }

        $first = true;
        foreach ($defs as $def) {
            $requirements = is_array($def['requirements'] ?? null) ? $def['requirements'] : [];
            $requirements['title'] = $def['title'] ?? $definition['title'];
            $compensation = array_key_exists('compensation', $def)
                ? (float) $def['compensation']
                : ($first ? (float) $definition['compensation'] : 0.0);

            $this->db->prepare("INSERT INTO digital_components(order_component_id,format_type,requirements_json,compensation,status,deadline,created_at,updated_at) VALUES(?,?,?,?, 'open',?,NOW(),NOW())")
                ->execute([
                    $componentId,
                    (string) ($def['format_type'] ?? 'mixed'),
                    json_encode($requirements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $compensation,
                    $def['deadline'] ?? null,
                ]);
            $first = false;
        }
    }

    private function createPrecheckRequirements(int $orderId, int $runId, int $componentId, string $categoryName, array $config): void
    {
        $startControl = $config['start_control'] ?? [];
        $requirements = $startControl['requirements'] ?? $config['precheck_requirements'] ?? [];

        if (!$requirements) {
            $requirements = $this->defaultPrechecks($categoryName);
        }

        foreach (array_values($requirements) as $idx => $requirement) {
            if (is_string($requirement)) {
                $requirement = ['key' => 'req_' . ($idx + 1), 'label' => $requirement];
            }
            $key = trim((string) ($requirement['key'] ?? ('req_' . ($idx + 1))));
            $label = trim((string) ($requirement['label'] ?? ('Pflichtaufnahme ' . ($idx + 1))));
            $description = trim((string) ($requirement['description'] ?? ''));
            $count = max(1, (int) ($requirement['required_count'] ?? 1));

            $this->db->prepare('INSERT INTO precheck_requirements(order_id,order_run_id,order_component_id,requirement_key,label,description,required_count,sort_order,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())')
                ->execute([$orderId, $runId, $componentId, $key, $label, $description ?: null, $count, $idx]);
        }
    }

    private function defaultPrechecks(string $categoryName): array
    {
        $name = mb_strtolower($categoryName);

        if (str_contains($name, 'sock')) {
            return [
                ['key' => 'article_front', 'label' => 'Ausgewählte Socken – Gesamtansicht'],
                ['key' => 'article_detail', 'label' => 'Ausgewählte Socken – Detail/Perspektive'],
                ['key' => 'feet_front', 'label' => 'Nackte Füße – Vorder-/Oberseite'],
                ['key' => 'feet_sole', 'label' => 'Nackte Füße – Sohlenansicht'],
            ];
        }
        if (str_contains($name, 'schuh')) {
            return [
                ['key' => 'shoes_outer', 'label' => 'Schuhe außen – mehrere Winkel', 'required_count' => 2],
                ['key' => 'shoes_inside', 'label' => 'Schuhe – Innenbereich'],
                ['key' => 'shoes_sole', 'label' => 'Schuhe – Sohlen'],
                ['key' => 'feet', 'label' => 'Nackte Füße – mehrere Perspektiven', 'required_count' => 2],
            ];
        }
        if (str_contains($name, 'slip') || str_contains($name, 'top') || str_contains($name, 'bh') || str_contains($name, 'dessous')) {
            return [
                ['key' => 'front', 'label' => 'Kleidungsstück – Vorderseite'],
                ['key' => 'back', 'label' => 'Kleidungsstück – Rückseite'],
                ['key' => 'detail', 'label' => 'Relevantes Detail'],
                ['key' => 'worn', 'label' => 'Im getragenen Zustand vor Beginn'],
            ];
        }

        return [
            ['key' => 'article_overview', 'label' => 'Artikel – Gesamtansicht'],
            ['key' => 'article_detail', 'label' => 'Artikel – weitere Perspektive/Detail'],
        ];
    }
}
