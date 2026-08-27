<?php
require_once __DIR__ . '/HarvestModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class HarvestController {
    private $model;
    public function __construct($pdo) { $this->model = new HarvestModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['crop_id', 'harvest_date', 'quantity', 'quality'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Harvest record added successfully!');
                    header('Location: /index.php?module=Harvest&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add harvest record.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
