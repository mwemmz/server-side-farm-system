<?php
require_once __DIR__ . '/SupplierModel.php';
class SupplierController {
    private $model;
    public function __construct($pdo) { $this->model = new SupplierModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
