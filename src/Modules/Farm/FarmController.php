<?php
require_once __DIR__ . '/FarmModel.php';

class FarmController {
    private $model;

    public function __construct($pdo) {
        $this->model = new FarmModel($pdo);
    }

    public function index() {
        return $this->model->getAllFarms();
    }

    public function add($name, $location) {
        return $this->model->createFarm($name, $location);
    }
}
?>
