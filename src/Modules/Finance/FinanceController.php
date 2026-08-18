<?php
require_once __DIR__ . '/FinanceModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class FinanceController {
    private $model;
    public function __construct($pdo) { $this->model = new FinanceModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['type', 'amount', 'description', 'date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Finance record added successfully!');
                    header('Location: /index.php?module=Finance&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add finance record.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
