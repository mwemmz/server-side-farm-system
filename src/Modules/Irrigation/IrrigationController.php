<?php
require_once __DIR__ . '/IrrigationModel.php';
class IrrigationController {
    private $model;
    public function __construct($pdo) { $this->model = new IrrigationModel($pdo); }
    public function index() { return $this->model->getAllSystems(); }
    public function add($farm_id, $type, $status) { return $this->model->createSystem($farm_id, $type, $status); }
}
