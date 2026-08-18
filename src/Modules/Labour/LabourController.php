<?php
require_once __DIR__ . '/LabourModel.php';
class LabourController {
    private $model;
    public function __construct($pdo) { $this->model = new LabourModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
