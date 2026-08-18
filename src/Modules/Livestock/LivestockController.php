<?php
require_once __DIR__ . '/LivestockModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class LivestockController {
    private $model;
    public function __construct($pdo) { $this->model = new LivestockModel($pdo); }
    public function index() { return $this->model->getAllAnimals(); }
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['farm_id', 'type', 'breed', 'dob'];
            $errors = ValidationHelper::validateRequired($fields, $data);
            if (empty($errors)) {
                if ($this->model->createAnimal($data['farm_id'], $data['type'], $data['breed'], $data['dob'])) {
                    SessionHelper::setFlash('success', 'Animal added successfully!');
                    header('Location: /index.php?module=Livestock&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add animal.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'animals' => $this->model->getAllAnimals()];
        }
        return ['animals' => $this->model->getAllAnimals()];
    }
}
