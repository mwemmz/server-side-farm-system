<?php
require_once __DIR__ . '/SalesModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/AuditHelper.php';

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
                $data['inventory_id'] = !empty($data['inventory_id']) ? (int) $data['inventory_id'] : null;
                $data['livestock_id'] = !empty($data['livestock_id']) ? (int) $data['livestock_id'] : null;
                $data['field_id'] = !empty($data['field_id']) ? (int) $data['field_id'] : null;
                $saleId = $this->model->createSale($data);
                if ($saleId > 0) {
                    AuditHelper::log($this->model->getPdo(), 'human', 'sale_created', 'sale', $saleId, $data, $_SESSION['user_id'] ?? null);
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
