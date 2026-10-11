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
            $fields = ['farm_id', 'name', 'planting_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            $variety = isset($data['variety']) && trim($data['variety']) !== '' ? trim($data['variety']) : null;
            $harvest = isset($data['expected_harvest_date']) && trim($data['expected_harvest_date']) !== '' ? trim($data['expected_harvest_date']) : null;

            if ($harvest !== null && !isset($errors['planting_date']) && $harvest < $data['planting_date']) {
                $errors['expected_harvest_date'] = "Harvest date cannot be earlier than the planting date.";
            }

            if (empty($errors)) {
                try {
                    if ($this->model->createCrop((int) $data['farm_id'], trim($data['name']), $variety, $data['planting_date'], $harvest)) {
                        SessionHelper::setFlash('success', 'Crop added successfully!');
                        header('Location: index.php?module=Crop&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add crop.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save crop: the selected farm may no longer exist.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'crops' => $this->model->getAllCrops()];
        }
        return ['crops' => $this->model->getAllCrops()];
    }
}
?>
