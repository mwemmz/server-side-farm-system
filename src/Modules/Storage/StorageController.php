<?php
require_once __DIR__ . '/StorageModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class StorageController {
    private $model;
    public function __construct($pdo) { $this->model = new StorageModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['name', 'capacity', 'current_stock', 'unit'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (!isset($errors['capacity'])) {
                $e = ValidationHelper::number($data, 'capacity', ['label' => 'Capacity', 'min' => 0]);
                if ($e) $errors['capacity'] = $e;
            }
            if (!isset($errors['current_stock'])) {
                $e = ValidationHelper::number($data, 'current_stock', ['label' => 'Current stock', 'min' => 0]);
                if ($e) $errors['current_stock'] = $e;
            }

            if (empty($errors)) {
                $row = array_intersect_key($data, array_flip(['name', 'facility_type', 'capacity', 'current_stock', 'unit']));
                $row['name'] = trim($row['name']);
                foreach (['facility_type'] as $k) { if (isset($row[$k]) && trim($row[$k]) === '') $row[$k] = null; }
                $row['unit'] = trim((string) ($row['unit'] ?? ''));
                if ($row['unit'] === '') { $row['unit'] = 'kg'; }
                if ($this->model->create($row)) {
                    SessionHelper::setFlash('success', 'Storage record added successfully!');
                    SessionHelper::flashCreated('Storage', $this->model->lastId(), trim($data['name']));
                    header('Location: /index.php?module=Storage&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add storage record.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }

    /** Edit a storage facility's name, capacity or unit (inline edit). */
    public function edit() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $id = ValidationHelper::id($data['id'] ?? '');
            if ($id === null) {
                $errors = ['general' => "Invalid or missing storage facility id."];
                return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
            }
            $errors = ValidationHelper::validateRequired(['name', 'capacity'], $data);
            if (!isset($errors['capacity'])) {
                $e = ValidationHelper::number($data, 'capacity', ['label' => 'Capacity', 'min' => 0]);
                if ($e) $errors['capacity'] = $e;
            }

            $exists = $this->model->getById($id);
            if (!$exists) {
                $errors['general'] = "Storage facility not found.";
                return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
            }

            if (empty($errors)) {
                $unit = trim((string) ($data['unit'] ?? ''));
                $unit = ($unit !== '' ? $unit : 'kg');
                $stored = (float) ($exists['current_stock'] ?? 0);
                // Never shrink capacity below what the facility already holds.
                if ((float) $data['capacity'] < $stored) {
                    $errors['capacity'] = 'Capacity cannot be lower than the current stock of '
                        . self::fmtQty($stored) . ' ' . ($exists['unit'] ?? 'kg') . '.';
                }
                // Changing the unit with stock inside would silently relabel the quantity.
                if (strcasecmp($unit, (string) ($exists['unit'] ?? 'kg')) !== 0 && $stored > 0) {
                    $errors['unit'] = 'Empty the facility before changing its unit ('
                        . self::fmtQty($stored) . ' ' . ($exists['unit'] ?? 'kg') . ' stored).';
                }
            }

            if (empty($errors)) {
                $row = [
                    'name' => trim($data['name']),
                    'capacity' => $data['capacity'],
                    'unit' => ($unit ?? 'kg'),
                ];
                if ($this->model->update($id, $row)) {
                    SessionHelper::setFlash('success', 'Storage facility updated!');
                    header('Location: /index.php?module=Storage&action=index');
                    exit;
                }
                $errors['general'] = "Failed to update storage facility.";
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }

    /** Pretty-print a quantity: 60.00 -> "60", avoid floating point noise. */
    private static function fmtQty($v) {
        $s = number_format((float) $v, 2, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');
        return $s !== '' ? $s : '0';
    }
}
?>
