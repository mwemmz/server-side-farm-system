<?php
require_once __DIR__ . '/EquipmentModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class EquipmentController {
    private $model;
    public function __construct($pdo) { $this->model = new EquipmentModel($pdo); }
    public function index() { return $this->model->getAllEquipment(); }
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['name', 'status'], $data);
            if (empty($errors)) {
                if ($this->model->addEquipment($data['name'], $data['status'])) {
                    SessionHelper::setFlash('success', 'Equipment added!');
                    header('Location: /index.php?module=Equipment&action=index');
                    exit;
                }
            }
            return ['errors' => $errors, 'data' => $data, 'items' => $this->model->getAllEquipment()];
        }
        return ['items' => $this->model->getAllEquipment()];
    }
}
