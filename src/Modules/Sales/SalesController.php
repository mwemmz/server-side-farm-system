<?php
require_once __DIR__ . '/SalesModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class SalesController {
    private $model;
    public function __construct($pdo) { $this->model = new SalesModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['customer_name', 'amount', 'sale_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Sale added successfully!');
                    header('Location: /index.php?module=Sales&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add sale.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
