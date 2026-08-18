<?php
require_once __DIR__ . '/SalesModel.php';
class SalesController {
    private $model;
    public function __construct($pdo) { $this->model = new SalesModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
