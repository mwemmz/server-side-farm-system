<?php
require_once __DIR__ . '/ReportsModel.php';
class ReportsController {
    private $model;
    public function __construct($pdo) { $this->model = new ReportsModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
