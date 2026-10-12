<?php
require_once __DIR__ . '/SalesModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Finance/FinanceService.php';

class SalesController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new SalesModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['customer_name', 'amount', 'sale_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (!isset($errors['sale_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'sale_date', 'Sale date');
                if ($e) $errors['sale_date'] = $e;
            }
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
                    $saleId = $this->model->lastId();
                    // Cross-module: a sale posts an income record (idempotent by source).
                    $product = strtolower(trim((string) ($row['crop_name'] ?? '')));
                    $incomeCat = (strpos($product, 'cattle') !== false || strpos($product, 'goat') !== false
                        || strpos($product, 'pig') !== false || strpos($product, 'poultry') !== false
                        || strpos($product, 'livestock') !== false || strpos($product, 'chicken') !== false)
                        ? 'Livestock sales' : 'Crop sales';
                    (new FinanceService($this->pdo))->postSafe(
                        'income', $incomeCat, $data['amount'],
                        'Sale to ' . trim($data['customer_name']) . ($row['crop_name'] ? ' — ' . $row['crop_name'] : ''),
                        null, 'Sales', $saleId, $data['sale_date'], 'paid'
                    );
                    SessionHelper::setFlash('success', 'Sale added successfully!');
                    SessionHelper::flashCreated('Sales', $saleId, trim($data['customer_name']), ['Amount' => 'K' . number_format((float) $data['amount'], 2)]);
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
