<?php
require_once __DIR__ . '/HarvestModel.php';
class HarvestController {
    private $model;
    public function __construct($pdo) { $this->model = new HarvestModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
