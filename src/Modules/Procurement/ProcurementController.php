<?php
require_once __DIR__ . '/ProcurementModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class ProcurementController {
    private $model;
    public function __construct($pdo) { $this->model = new ProcurementModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['supplier_id', 'item_name', 'quantity', 'cost', 'purchase_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Procurement record added successfully!');
                    header('Location: /index.php?module=Procurement&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add procurement record.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
