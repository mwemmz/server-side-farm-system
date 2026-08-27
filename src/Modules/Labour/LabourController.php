<?php
require_once __DIR__ . '/LabourModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class LabourController {
    private $model;
    public function __construct($pdo) { $this->model = new LabourModel($pdo); }
    public function index() { return $this->model->getAll(); }
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['farm_id', 'name', 'role', 'attendance_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);
            if (empty($errors)) {
                $allowedFields = ['farm_id', 'name', 'role', 'attendance_date'];
                $inputData = array_intersect_key($data, array_flip($allowedFields));
                if ($this->model->create($inputData)) {
                    SessionHelper::setFlash('success', 'Labour added successfully!');
                    header('Location: /index.php?module=Labour&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add labour.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'labour' => $this->model->getAll()];
        }
        return ['labour' => $this->model->getAll()];
    }
}
