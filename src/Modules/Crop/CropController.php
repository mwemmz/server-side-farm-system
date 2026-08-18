<?php
require_once __DIR__ . '/CropModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class CropController {
    private $model;

    public function __construct($pdo) {
        $this->model = new CropModel($pdo);
    }

    public function index() {
        return $this->model->getAllCrops();
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['farm_id', 'name', 'variety', 'planting_date', 'expected_harvest_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->createCrop($data['farm_id'], $data['name'], $data['variety'], $data['planting_date'], $data['expected_harvest_date'])) {
                    SessionHelper::setFlash('success', 'Crop added successfully!');
                    header('Location: /index.php?module=Crop&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add crop.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'crops' => $this->model->getAllCrops()];
        }
        return ['crops' => $this->model->getAllCrops()];
    }
}
?>
