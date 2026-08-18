<?php
require_once __DIR__ . '/LivestockModel.php';
class LivestockController {
    private $model;
    public function __construct($pdo) { $this->model = new LivestockModel($pdo); }
    public function index() { return $this->model->getAllAnimals(); }
    public function add($farm_id, $type, $breed, $dob) { return $this->model->createAnimal($farm_id, $type, $breed, $dob); }
}
