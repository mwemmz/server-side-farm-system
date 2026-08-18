<?php
require_once __DIR__ . '/AnalyticsModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class AnalyticsController {
    private $model;
    public function __construct($pdo) { $this->model = new AnalyticsModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['module_name', 'data_points'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                if ($this->model->create($data)) {
                    SessionHelper::setFlash('success', 'Analytics data added successfully!');
                    header('Location: /index.php?module=Analytics&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add analytics data.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
