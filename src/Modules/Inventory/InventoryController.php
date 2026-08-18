<?php
require_once __DIR__ . '/InventoryModel.php';
class InventoryController {
    private $model;
    public function __construct($pdo) { $this->model = new InventoryModel($pdo); }
    public function index() { return $this->model->getAllItems(); }
    public function add($name, $quantity, $unit) { return $this->model->addItem($name, $quantity, $unit); }
}
?>
