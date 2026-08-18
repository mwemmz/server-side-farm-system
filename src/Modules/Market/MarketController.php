<?php
require_once __DIR__ . '/MarketModel.php';
class MarketController {
    private $model;
    public function __construct($pdo) { $this->model = new MarketModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
