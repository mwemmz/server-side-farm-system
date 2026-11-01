<?php
/**
 * OpsController — one guarded controller for the metadata-driven operations
 * catalogue. Every table/column name comes from OpsRegistry; every value from
 * the request is validated, coerced and bound before it reaches the database.
 *
 * Routes (dispatched from public/index.php):
 *   index.php?module=Ops                          landing grid
 *   index.php?module=Ops&view=<key>               records + add form
 *   index.php?module=Ops&view=<key>&action=add    POST create
 *   index.php?module=Ops&view=<key>&action=edit   POST update
 *   index.php?module=Ops&view=<key>&action=delete POST delete
 */
require_once __DIR__ . '/OpsRegistry.php';
require_once __DIR__ . '/OpsModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/RbacHelper.php';
require_once __DIR__ . '/../../Helpers/AuditHelper.php';
require_once __DIR__ . '/../Finance/FinanceService.php';

class OpsController
{
    /** Foreign-key tables the registry may point at. */
    private const FK_TABLES = [
        'fields', 'crops', 'farms', 'livestock', 'equipment', 'employees', 'inventory',
        'suppliers', 'customers', 'health_incidents', 'harvest_records', 'stored_produce',
        'sales_orders', 'invoices', 'spray_schedules', 'seed_records',
        'market_destinations',
    ];

    /** Date pairs where the second value must not precede the first. */
    private const DATE_PAIRS = [
        ['start_date', 'end_date'],
        ['assigned_date', 'released_date'],
        ['quote_date', 'valid_until'],
        ['invoice_date', 'due_date'],
        ['order_date', 'delivery_date'],
    ];

    private $pdo;
    private $model;
    private $finance;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->model = new OpsModel($pdo);
        $this->finance = new FinanceService($pdo);
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    /** Landing grid: groups of record types this role may work with. */
    public function landing(): array
    {
        $role = (string) ($_SESSION['role'] ?? '');
        $groups = [];
        foreach (OpsRegistry::groups() as $group => $keys) {
            foreach ($keys as $key) {
                $def = OpsRegistry::get($key);
                if (!$def) continue;
                if (!RbacHelper::can($role, (string) $def['permission'])) continue;
                $groups[$group][] = [
                    'key'     => $key,
                    'label'   => $def['label'],
                    'summary' => $def['summary'] ?? '',
                    'count'   => $this->model->count($def),
                ];
            }
        }
        return ['groups' => $groups];
    }

    /** Records list plus the add/edit form for one definition. */
    public function records(): array
    {
        $key = $this->requestedKey();
        $def = $this->definition($key);
        RbacHelper::requirePermission((string) $def['permission']);

        $mode = 'list';
        $formData = [];
        $editId = null;
        $action = (string) ($_GET['action'] ?? 'index');
        if ($action === 'add') {
            $mode = 'add';
        } elseif ($action === 'edit') {
            $editId = ValidationHelper::id($_GET['id'] ?? null);
            if (!$editId) {
                http_response_code(404);
                exit('Record not found.');
            }
            $row = $this->model->find($def, $editId);
            if ($row === null) {
                http_response_code(404);
                exit('Record not found.');
            }
            $mode = 'edit';
            $formData = $row;
        }

        return $this->viewData($key, $def, $mode, [], $formData, $editId);
    }

    // ------------------------------------------------------------------
    // Write
    // ------------------------------------------------------------------

    public function add(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect($this->requestedKey());
        }
        return $this->save(null);
    }

    public function edit(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect($this->requestedKey());
        }
        $id = ValidationHelper::id($_POST['id'] ?? $_GET['id'] ?? null);
        if (!$id) {
            SessionHelper::setFlash('error', 'That record could not be found.');
            $this->redirect($this->requestedKey());
        }
        return $this->save($id);
    }

    public function delete(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('This action must be submitted as a POST request.');
        }
        $key = $this->requestedKey();
        $def = $this->definition($key);
        RbacHelper::requirePermission((string) $def['permission']);

        $id = ValidationHelper::id($_POST['id'] ?? null);
        if (!$id) {
            SessionHelper::setFlash('error', 'That record could not be found.');
            $this->redirect($key);
        }
        $row = $this->model->find($def, $id);
        if ($row === null) {
            SessionHelper::setFlash('error', 'That record no longer exists.');
            $this->redirect($key);
        }

        $this->pdo->beginTransaction();
        try {
            $this->model->delete($def, $id);
            $this->afterDelete($def, $key, $id, $row);
            AuditHelper::log($this->pdo, 'human', 'ops_' . $key . '_deleted', $def['table'], $id, [
                'label' => $def['label'],
            ], $this->userId());
            $this->pdo->commit();
            SessionHelper::setFlash('success', $def['label'] . ' record deleted.');
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Ops delete failed (' . $def['table'] . '): ' . $e->getMessage());
            SessionHelper::setFlash('error', 'Could not delete this record. It may still be referenced elsewhere.');
        }
        $this->redirect($key);
        return [];
    }

    /** Shared create/update pipeline: validate, transact, hook, audit, post finance. */
    private function save($id): array
    {
        $key = $this->requestedKey();
        $def = $this->definition($key);
        RbacHelper::requirePermission((string) $def['permission']);

        $data = $_POST;
        $mode = $id === null ? 'add' : 'edit';
        $errors = $this->validate($def, $data);
        if (!empty($errors)) {
            return $this->viewData($key, $def, $mode, $errors, $data, $id);
        }

        $row = $this->buildRow($def, $data, $id === null ? 'create' : 'update');

        $this->pdo->beginTransaction();
        try {
            if ($id === null) {
                $newId = $this->model->create($def, $row);
                $verb = 'created';
            } else {
                $this->model->update($def, $id, $row);
                $newId = $id;
                $verb = 'updated';
            }
            if ($newId < 1) {
                throw new PDOException('Insert did not return a row id.');
            }

            $this->runHooks($def, $key, $newId, $row, $mode);
            $this->syncFinance($def, $key, $newId, $row);

            AuditHelper::log($this->pdo, 'human', 'ops_' . $key . '_' . $verb, $def['table'], $newId, [
                'label' => $def['label'],
                'fields' => array_keys($row),
            ], $this->userId());

            $this->pdo->commit();
            SessionHelper::setFlash('success', $def['label'] . ' saved successfully.');
            SessionHelper::flashCreated('Ops', $newId, $def['label']);
            $this->redirect($key);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Ops save failed (' . $def['table'] . '): ' . $e->getMessage());
            $errors['general'] = 'Could not save this record. Check that related records still exist and try again.';
            return $this->viewData($key, $def, $mode, $errors, $data, $id);
        }
        return [];
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    private function validate(array $def, array $data): array
    {
        $errors = [];
        foreach ($def['fields'] as $col => $field) {
            $type = (string) ($field['type'] ?? 'text');
            $label = $this->label($col, $field);
            $raw = $data[$col] ?? '';
            if (!is_scalar($raw)) $raw = '';
            $raw = trim((string) $raw);
            $required = !empty($field['required']);

            if ($raw === '') {
                if ($required) $errors[$col] = $label . ' is required.';
                continue;
            }

            switch ($type) {
                case 'number':
                    $opts = ['label' => $label];
                    foreach (['integer', 'positive', 'min', 'max'] as $opt) {
                        if (array_key_exists($opt, $field)) $opts[$opt] = $field[$opt];
                    }
                    $e = ValidationHelper::number($data, $col, $opts);
                    if ($e) $errors[$col] = $e;
                    break;

                case 'date':
                    $ts = strtotime($raw);
                    if ($ts === false) {
                        $errors[$col] = $label . ' is not a valid date.';
                    } elseif (!empty($field['not_future'])) {
                        $e = ValidationHelper::dateNotFuture($data, $col, $label);
                        if ($e) $errors[$col] = $e;
                    }
                    break;

                case 'select':
                    $allowed = array_map('strval', array_keys((array) ($field['options'] ?? [])));
                    if (!in_array($raw, $allowed, true)) {
                        $errors[$col] = 'Choose a valid ' . strtolower($label) . '.';
                    }
                    break;

                case 'fk':
                    $fkId = ValidationHelper::id($raw);
                    if (!$fkId) {
                        $errors[$col] = 'Choose a valid ' . strtolower($label) . '.';
                    } elseif (!$this->fkExists((string) $field['table'], $fkId)) {
                        $errors[$col] = 'The selected ' . strtolower($label) . ' no longer exists.';
                    }
                    break;

                case 'email':
                    if (!filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                        $errors[$col] = $label . ' must be a valid email address.';
                    }
                    break;

                case 'textarea':
                    if (mb_strlen($raw) > 5000) {
                        $errors[$col] = $label . ' is too long (max 5000 characters).';
                    }
                    break;

                default: // text / hidden
                    if (mb_strlen($raw) > 255) {
                        $errors[$col] = $label . ' is too long (max 255 characters).';
                    }
            }
        }

        // At least one of the requested columns must be supplied.
        if (!empty($def['require_any'])) {
            $any = false;
            foreach ($def['require_any'] as $col) {
                if (trim((string) ($data[$col] ?? '')) !== '') { $any = true; break; }
            }
            if (!$any) {
                $names = array_map(function ($c) { return ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $c))); }, $def['require_any']);
                $errors['general'] = 'Choose at least one of: ' . implode(', ', $names) . '.';
            }
        }

        // Cross-field date ordering.
        foreach (self::DATE_PAIRS as $pair) {
            list($start, $end) = $pair;
            if (isset($def['fields'][$start], $def['fields'][$end])) {
                $s = trim((string) ($data[$start] ?? ''));
                $e = trim((string) ($data[$end] ?? ''));
                if ($s !== '' && $e !== '') {
                    $st = strtotime($s);
                    $et = strtotime($e);
                    if ($st !== false && $et !== false && $et < $st) {
                        $errors[$end] = ucfirst(str_replace('_', ' ', $end)) . ' cannot be before ' . str_replace('_', ' ', $start) . '.';
                    }
                }
            }
        }

        // Invoice payment cannot exceed the invoice total.
        if ($def['table'] === 'invoices') {
            $total = $data['total_amount'] ?? '';
            $paid = $data['amount_paid'] ?? '';
            if (is_numeric($total) && is_numeric($paid) && (float) $paid > (float) $total) {
                $errors['amount_paid'] = 'Amount paid cannot exceed the total amount.';
            }
        }

        return $errors;
    }

    /** Coerce validated input into database values. */
    private function buildRow(array $def, array $data, string $mode): array
    {
        $row = [];
        foreach ($def['fields'] as $col => $field) {
            $type = (string) ($field['type'] ?? 'text');
            $raw = $data[$col] ?? '';
            if (!is_scalar($raw)) $raw = '';
            $raw = trim((string) $raw);

            if ($raw === '' && $mode === 'create' && empty($field['required'])) {
                // Let the database default apply instead of writing NULL.
                continue;
            }

            switch ($type) {
                case 'fk':
                    $row[$col] = $raw === '' ? null : (int) $raw;
                    break;
                case 'number':
                    if ($raw === '') {
                        $row[$col] = null;
                    } elseif (!empty($field['integer'])) {
                        $row[$col] = (int) $raw;
                    } else {
                        $row[$col] = (float) $raw;
                    }
                    break;
                case 'date':
                    $row[$col] = $raw === '' ? null : date('Y-m-d', (int) strtotime($raw));
                    break;
                default:
                    $row[$col] = $raw === '' ? null : $raw;
            }
        }
        return $row;
    }

    // ------------------------------------------------------------------
    // Finance posting
    // ------------------------------------------------------------------

    private function syncFinance(array $def, string $key, int $id, array $row): void
    {
        if (empty($def['finance'])) return;
        $spec = $def['finance'];
        $source = 'Ops:' . $key;

        $eligible = true;
        if (!empty($spec['when']) && is_array($spec['when'])) {
            foreach ($spec['when'] as $col => $allowed) {
                if (!in_array((string) ($row[$col] ?? ''), array_map('strval', (array) $allowed), true)) {
                    $eligible = false;
                    break;
                }
            }
        }
        $amount = $row[$spec['amount']] ?? null;
        if (!$eligible || $amount === null || (float) $amount <= 0) {
            $this->finance->removeSource($source, $id);
            return;
        }

        $date = null;
        if (!empty($spec['date'])) {
            $date = $row[$spec['date']] ?? null;
        }
        $date = $date ?: date('Y-m-d');

        $dims = [];
        foreach (FinanceService::DIMENSION_COLUMNS as $col) {
            if (array_key_exists($col, $row)) $dims[$col] = $row[$col];
        }

        $what = $row['product'] ?? $row['feed_name'] ?? $row['description'] ?? $row['seed_name'] ?? $row['title'] ?? ('#' . $id);
        $description = $def['label'] . ' — ' . $what;

        $this->finance->syncSourceSafe(
            $source,
            $id,
            'expense',
            $spec['category'],
            $amount,
            $description,
            $this->farmIdFor($row),
            $date,
            'paid',
            null,
            $dims
        );
    }

    /** Best-effort farm id for finance dimensions. */
    private function farmIdFor(array $row): ?int
    {
        if (!empty($row['farm_id'])) return (int) $row['farm_id'];
        $lookups = [
            'field_id'     => 'SELECT farm_id FROM fields WHERE id = ?',
            'crop_id'      => 'SELECT farm_id FROM crops WHERE id = ?',
            'livestock_id' => 'SELECT farm_id FROM livestock WHERE id = ?',
        ];
        foreach ($lookups as $col => $sql) {
            if (empty($row[$col])) continue;
            try {
                $st = $this->pdo->prepare($sql);
                $st->execute([(int) $row[$col]]);
                $farm = $st->fetchColumn();
                if ($farm !== false && $farm !== null) return (int) $farm;
            } catch (PDOException $e) {
                // Ignore: dimensions are optional metadata.
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Per-definition hooks
    // ------------------------------------------------------------------

    private function runHooks(array $def, string $key, int $id, array $row, string $mode): void
    {
        $hook = $def['hook'] ?? null;
        if (!$hook) return;
        $method = 'hook' . ucfirst($hook);
        if (method_exists($this, $method)) {
            $this->$method($id, $row, $mode);
        }
    }

    private function afterDelete(array $def, string $key, int $id, array $row): void
    {
        $this->finance->removeSource('Ops:' . $key, $id);

        if ($key === 'invoice_items' && !empty($row['invoice_id'])) {
            $this->recalculateInvoice((int) $row['invoice_id']);
        }
        if ($key === 'supplier_reviews' && !empty($row['supplier_id'])) {
            $this->updateSupplierRating((int) $row['supplier_id']);
        }
    }

    /** Mortality recorded -> take the animal out of the active herd. */
    private function hookMarkAnimalDead(int $id, array $row, string $mode): void
    {
        if (empty($row['livestock_id'])) return;
        $st = $this->pdo->prepare("UPDATE livestock SET status = 'dead' WHERE id = ?");
        $st->execute([(int) $row['livestock_id']]);
    }

    /** Fuel entry carries the odometer/hour meter reading. */
    private function hookUpdateEquipmentMeter(int $id, array $row, string $mode): void
    {
        $odometer = $row['odometer'] ?? null;
        if (empty($row['equipment_id']) || $odometer === null || $odometer === '') return;
        $st = $this->pdo->prepare(
            "UPDATE equipment SET meter_reading = GREATEST(COALESCE(meter_reading, 0), ?) WHERE id = ?"
        );
        $st->execute([(float) $odometer, (int) $row['equipment_id']]);
    }

    /** Repair logged -> mark the machine serviced. */
    private function hookMarkEquipmentServiced(int $id, array $row, string $mode): void
    {
        if (empty($row['equipment_id'])) return;
        $st = $this->pdo->prepare("UPDATE equipment SET maintenance_status = 'serviced' WHERE id = ?");
        $st->execute([(int) $row['equipment_id']]);
    }

    private function hookStampTaskCompletion(int $id, array $row, string $mode): void
    {
        $st = $this->pdo->prepare(
            "UPDATE tasks SET completed_at = CASE WHEN status = 'completed' THEN COALESCE(completed_at, NOW()) ELSE NULL END WHERE id = ?"
        );
        $st->execute([$id]);
    }

    private function hookSyncHarvestGrade(int $id, array $row, string $mode): void
    {
        if (empty($row['harvest_id']) || empty($row['grade'])) return;
        $st = $this->pdo->prepare("UPDATE harvest_records SET grade = ? WHERE id = ?");
        $st->execute([(string) $row['grade'], (int) $row['harvest_id']]);
    }

    private function hookStampInvoice(int $id, array $row, string $mode): void
    {
        $invoiceNo = trim((string) ($row['invoice_no'] ?? ''));
        if ($invoiceNo === '') {
            $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        }
        $status = $this->invoiceStatus(
            (float) ($row['total_amount'] ?? 0),
            (float) ($row['amount_paid'] ?? 0),
            $row['due_date'] ?? null,
            (string) ($row['status'] ?? '')
        );
        $st = $this->pdo->prepare("UPDATE invoices SET invoice_no = ?, status = ? WHERE id = ?");
        $st->execute([$invoiceNo, $status, $id]);
    }

    private function hookRecalculateInvoice(int $id, array $row, string $mode): void
    {
        if (empty($row['invoice_id'])) return;
        $this->recalculateInvoice((int) $row['invoice_id']);
    }

    private function hookUpdateSupplierRating(int $id, array $row, string $mode): void
    {
        if (empty($row['supplier_id'])) return;
        $this->updateSupplierRating((int) $row['supplier_id']);
    }

    private function recalculateInvoice(int $invoiceId): void
    {
        $st = $this->pdo->prepare(
            "SELECT COALESCE(SUM(COALESCE(amount, quantity * unit_price, 0)), 0) FROM invoice_items WHERE invoice_id = ?"
        );
        $st->execute([$invoiceId]);
        $total = (float) $st->fetchColumn();

        $st = $this->pdo->prepare("SELECT amount_paid, due_date, status FROM invoices WHERE id = ?");
        $st->execute([$invoiceId]);
        $invoice = $st->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) return;

        $status = $this->invoiceStatus($total, (float) ($invoice['amount_paid'] ?? 0), $invoice['due_date'] ?? null, (string) ($invoice['status'] ?? ''));
        $up = $this->pdo->prepare("UPDATE invoices SET total_amount = ?, status = ? WHERE id = ?");
        $up->execute([$total, $status, $invoiceId]);
    }

    private function invoiceStatus(float $total, float $paid, $dueDate, string $current): string
    {
        if ($current === 'cancelled') return 'cancelled';
        if ($total > 0 && $paid >= $total) return 'paid';
        if ($paid > 0) return 'partial';
        if (!empty($dueDate) && strtotime((string) $dueDate) !== false && (string) $dueDate < date('Y-m-d')) return 'overdue';
        return 'unpaid';
    }

    private function updateSupplierRating(int $supplierId): void
    {
        $st = $this->pdo->prepare(
            "SELECT ROUND(AVG(score), 2) FROM (
                SELECT ((COALESCE(quality_score,0) + COALESCE(delivery_score,0)
                       + COALESCE(price_score,0) + COALESCE(reliability_score,0))::numeric
                       / NULLIF((CASE WHEN quality_score IS NOT NULL THEN 1 ELSE 0 END
                               + CASE WHEN delivery_score IS NOT NULL THEN 1 ELSE 0 END
                               + CASE WHEN price_score IS NOT NULL THEN 1 ELSE 0 END
                               + CASE WHEN reliability_score IS NOT NULL THEN 1 ELSE 0 END), 0)) AS score
                FROM supplier_reviews WHERE supplier_id = ?
             ) s WHERE score IS NOT NULL"
        );
        $st->execute([$supplierId]);
        $rating = $st->fetchColumn();
        $up = $this->pdo->prepare("UPDATE suppliers SET rating = ? WHERE id = ?");
        $up->execute([$rating === false ? null : $rating, $supplierId]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function requestedKey(): string
    {
        $key = trim((string) ($_GET['view'] ?? $_POST['view'] ?? ''));
        if ($key === '') {
            $this->redirect('');
        }
        return $key;
    }

    private function definition(string $key): array
    {
        $def = OpsRegistry::get($key);
        if ($def === null) {
            http_response_code(404);
            exit('Unknown record type.');
        }
        return $def;
    }

    private function viewData(string $key, array $def, string $mode, array $errors = [], array $formData = [], $editId = null): array
    {
        return [
            'key'       => $key,
            'def'       => $def,
            'records'   => $this->model->all($def),
            'mode'      => $mode,
            'errors'    => $errors,
            'data'      => $formData,
            'edit_id'   => $editId,
            'options'   => $this->fkOptions($def),
        ];
    }

    /** Pre-load FK option lists (id => label) once per request. */
    private function fkOptions(array $def): array
    {
        $out = [];
        foreach ($def['fields'] as $col => $field) {
            if (($field['type'] ?? '') !== 'fk' || empty($field['options_sql'])) continue;
            try {
                $rows = $this->pdo->query((string) $field['options_sql'])->fetchAll(PDO::FETCH_NUM);
                $opts = [];
                foreach ($rows as $r) {
                    $opts[(string) $r[0]] = (string) ($r[1] ?? $r[0]);
                }
                $out[$col] = $opts;
            } catch (PDOException $e) {
                $out[$col] = [];
            }
        }
        return $out;
    }

    private function fkExists(string $table, int $id): bool
    {
        if (!in_array($table, self::FK_TABLES, true)) return false;
        try {
            $st = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE id = ?");
            $st->execute([$id]);
            return (bool) $st->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function label(string $col, array $field): string
    {
        if (!empty($field['label'])) return (string) $field['label'];
        return ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $col)));
    }

    private function userId(): ?int
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private function redirect(string $key): void
    {
        $target = $key === '' ? 'index.php?module=Ops' : 'index.php?module=Ops&view=' . urlencode($key);
        header('Location: ' . $target);
        exit;
    }
}
