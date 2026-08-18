<?php
require_once __DIR__ . '/EquipmentModel.php';
class EquipmentController {
    private $model;
    public function __construct($pdo) { $this->model = new EquipmentModel($pdo); }
    public function index() { return $this->model->getAllEquipment(); }
    public function add($name, $status) { return $this->model->addEquipment($name, $status); }
}
?>
