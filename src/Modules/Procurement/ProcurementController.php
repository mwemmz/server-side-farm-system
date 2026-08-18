<?php
require_once __DIR__ . '/ProcurementModel.php';
class ProcurementController {
    private $model;
    public function __construct($pdo) { $this->model = new ProcurementModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
