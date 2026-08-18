<?php
require_once __DIR__ . '/StorageModel.php';
class StorageController {
    private $model;
    public function __construct($pdo) { $this->model = new StorageModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
