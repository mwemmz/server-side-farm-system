<?php
require_once __DIR__ . '/SupplierModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class SupplierController {
    private $model;
    public function __construct($pdo) { $this->model = new SupplierModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['name'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                $row = array_intersect_key($data, array_flip(['name', 'category', 'phone', 'contact_info']));
                $row['name'] = trim($row['name']);
                foreach (['category', 'phone', 'contact_info'] as $k) { if (isset($row[$k]) && trim($row[$k]) === '') $row[$k] = null; }
                if ($this->model->create($row)) {
                    SessionHelper::setFlash('success', 'Supplier added successfully!');
                    SessionHelper::flashCreated('Supplier', $this->model->lastId(), trim($data['name']));
                    header('Location: /index.php?module=Supplier&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add supplier.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
