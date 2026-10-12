<?php
require_once __DIR__ . '/IrrigationModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class IrrigationController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new IrrigationModel($pdo); }
    public function index() { return $this->model->getAllSystems(); }
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['farm_id', 'type', 'status'], $data);
            if (!isset($errors['farm_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'farms', $data['farm_id'], 'farm');
                if ($e) $errors['farm_id'] = $e;
            }
            if (empty($errors)) {
                try {
                    if ($this->model->createSystem((int) $data['farm_id'], trim($data['type']), trim($data['status']), trim($data['water_source'] ?? ''))) {
                        SessionHelper::setFlash('success', 'Irrigation system added successfully!');
                        SessionHelper::flashCreated('Irrigation', $this->model->lastId(), trim($data['type']), ['Status' => trim($data['status'])]);
                        header('Location: index.php?module=Irrigation&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add system.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save system: the selected farm may no longer exist.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'systems' => $this->model->getAllSystems()];
        }
        return ['systems' => $this->model->getAllSystems()];
    }
}
?>
