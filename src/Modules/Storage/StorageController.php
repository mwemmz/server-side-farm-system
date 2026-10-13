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
            $id = (int) ($_POST['id'] ?? 0);
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['name', 'capacity'], $data);
            if (!isset($errors['capacity'])) {
                $e = ValidationHelper::number($data, 'capacity', ['label' => 'Capacity', 'min' => 0]);
                if ($e) $errors['capacity'] = $e;
            }
            if (empty($errors)) {
                $unit = trim((string) ($data['unit'] ?? ''));
                $row = [
                    'name' => trim($data['name']),
                    'capacity' => $data['capacity'],
                    'unit' => ($unit !== '' ? $unit : 'kg'),
                ];
                $exists = $this->model->getById($id);
                if ($exists && $this->model->update($id, $row)) {
                    SessionHelper::setFlash('success', 'Storage facility updated!');
                    header('Location: /index.php?module=Storage&action=index');
                    exit;
                }
                $errors['general'] = $exists ? "Failed to update storage facility." : "Storage facility not found.";
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
