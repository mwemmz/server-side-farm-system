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

            if (!isset($errors['amount'])) {
                $e = ValidationHelper::number($data, 'amount', ['label' => 'Amount', 'positive' => true]);
                if ($e) $errors['amount'] = $e;
            }
            if (!isset($errors['quantity']) && isset($data['quantity']) && trim($data['quantity']) !== '') {
                $e = ValidationHelper::number($data, 'quantity', ['label' => 'Quantity', 'min' => 0]);
                if ($e) $errors['quantity'] = $e;
            }

            if (empty($errors)) {
                $row = array_intersect_key($data, array_flip(['customer_name', 'crop_name', 'quantity', 'unit', 'amount', 'payment_method', 'sale_date']));
                $row['customer_name'] = trim($row['customer_name']);
                foreach (['crop_name', 'unit', 'payment_method'] as $k) { if (isset($row[$k]) && trim($row[$k]) === '') $row[$k] = null; }
                if (isset($row['quantity']) && trim((string) $row['quantity']) === '') $row['quantity'] = null;
                if ($this->model->create($row)) {
                    SessionHelper::setFlash('success', 'Sale added successfully!');
                    SessionHelper::flashCreated('Sales', $this->model->lastId(), trim($data['customer_name']), ['Amount' => 'K' . number_format((float) $data['amount'], 2)]);
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
