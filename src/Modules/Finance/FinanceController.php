<?php
require_once __DIR__ . '/FinanceModel.php';
class FinanceController {
    private $model;
    public function __construct($pdo) { $this->model = new FinanceModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
