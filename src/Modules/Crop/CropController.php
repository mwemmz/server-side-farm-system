<?php
require_once __DIR__ . '/CropModel.php';

class CropController {
    private $model;

    public function __construct($pdo) {
        $this->model = new CropModel($pdo);
    }

    public function index() {
        return $this->model->getAllCrops();
    }

    public function add($farm_id, $name, $variety, $planting_date, $expected_harvest_date) {
        return $this->model->createCrop($farm_id, $name, $variety, $planting_date, $expected_harvest_date);
    }
}
?>
