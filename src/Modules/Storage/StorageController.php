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
            $fields = ['name', 'capacity', 'current_stock'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Storage record added successfully!');
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
}
?>
