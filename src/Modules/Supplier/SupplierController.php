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
            $fields = ['name', 'contact_info'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Supplier added successfully!');
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
