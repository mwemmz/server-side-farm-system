<?php
require_once __DIR__ . '/WeatherModel.php';
class WeatherController {
    private $model;
    public function __construct($pdo) { $this->model = new WeatherModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
