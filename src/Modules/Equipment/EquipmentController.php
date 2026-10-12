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
                try {
                    if ($this->model->addEquipment(trim($data['name']), trim($data['status']))) {
                        SessionHelper::setFlash('success', 'Equipment added successfully!');
                        header('Location: index.php?module=Equipment&action=manage');
                        exit;
                    }
                    $errors['general'] = 'Failed to add equipment.';
                } catch (PDOException $e) {
                    $errors['general'] = 'Could not save equipment.';
                }
            }
            return ['errors' => $errors, 'data' => $data, 'items' => $this->model->getAllEquipment()];
        }
        return ['items' => $this->model->getAllEquipment()];
    }
}
?>
