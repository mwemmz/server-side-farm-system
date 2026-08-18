<?php
require_once __DIR__ . '/AnalyticsModel.php';
class AnalyticsController {
    private $model;
    public function __construct($pdo) { $this->model = new AnalyticsModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
