<?php
require_once __DIR__ . '/IrrigationModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class IrrigationController {
    private $model;
    public function __construct($pdo) { $this->model = new IrrigationModel($pdo); }
    public function index() { return $this->model->getAllSystems(); }
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['farm_id', 'type', 'status'];
            $errors = ValidationHelper::validateRequired($fields, $data);
            if (empty($errors)) {
                if ($this->model->createSystem($data['farm_id'], $data['type'], $data['status'])) {
                    SessionHelper::setFlash('success', 'System added successfully!');
                    header('Location: /index.php?module=Irrigation&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add system.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'systems' => $this->model->getAllSystems()];
        }
        return ['systems' => $this->model->getAllSystems()];
    }
}
