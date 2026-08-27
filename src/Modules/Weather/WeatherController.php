<?php
require_once __DIR__ . '/WeatherModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class WeatherController {
    private $model;
    public function __construct($pdo) { $this->model = new WeatherModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['farm_id', 'temperature', 'humidity'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Weather record added successfully!');
                    header('Location: /index.php?module=Weather&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add weather record.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
