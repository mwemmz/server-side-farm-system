<?php
require_once __DIR__ . '/FieldModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class FieldController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new FieldModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['farm_id', 'name', 'size', 'soil_type'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (!isset($errors['farm_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'farms', $data['farm_id'], 'farm');
                if ($e) $errors['farm_id'] = $e;
            }

            if (!isset($errors['size'])) {
                if (!is_numeric($data['size'])) {
                    $errors['size'] = "Size must be a number.";
                } elseif ((float) $data['size'] <= 0) {
                    $errors['size'] = "Size must be greater than 0.";
                }
            }

            if (empty($errors)) {
                $row = [
                    'farm_id'   => (int) $data['farm_id'],
                    'name'      => trim($data['name']),
                    'size'      => $data['size'],
                    'soil_type' => $data['soil_type'],
                ];
                try {
                    if ($this->model->create($row)) {
                        SessionHelper::setFlash('success', 'Field added successfully!');
                        SessionHelper::flashCreated('Field', $this->model->lastId(), trim($data['name']), ['Size (ha)' => $data['size']]);
                        header('Location: index.php?module=Field&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add field.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save field: the selected farm may no longer exist.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
