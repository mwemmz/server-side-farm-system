<?php
require_once __DIR__ . '/WeatherModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class WeatherController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new WeatherModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['farm_id', 'temperature', 'humidity'], $data);

            if (!isset($errors['farm_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'farms', $data['farm_id'], 'farm');
                if ($e) $errors['farm_id'] = $e;
            }
            if (!isset($errors['temperature'])) {
                $e = ValidationHelper::number($data, 'temperature', ['label' => 'Temperature', 'min' => -50, 'max' => 60]);
                if ($e) $errors['temperature'] = $e;
            }
            if (!isset($errors['humidity'])) {
                $e = ValidationHelper::number($data, 'humidity', ['label' => 'Humidity', 'min' => 0, 'max' => 100]);
                if ($e) $errors['humidity'] = $e;
            }

            if (empty($errors)) {
                $row = [
                    'farm_id'     => (int) $data['farm_id'],
                    'temperature' => $data['temperature'],
                    'humidity'    => $data['humidity'],
                ];
                try {
                    if ($this->model->create($row)) {
                        SessionHelper::setFlash('success', 'Weather record added successfully!');
                        SessionHelper::flashCreated('Weather', $this->model->lastId(), 'Weather record', ['Temperature' => $data['temperature'] . '°C']);
                        header('Location: index.php?module=Weather&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add weather record.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save weather record: the selected farm may no longer exist.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
