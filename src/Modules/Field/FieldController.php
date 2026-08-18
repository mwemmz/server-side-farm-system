<?php
require_once __DIR__ . '/FieldModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class FieldController {
    private $model;
    public function __construct($pdo) { $this->model = new FieldModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['farm_id', 'name', 'size', 'soil_type'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Field added successfully!');
                    header('Location: /index.php?module=Field&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add field.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
