<?php
require_once __DIR__ . '/FarmModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class FarmController {
    private $model;

    public function __construct($pdo) {
        $this->model = new FarmModel($pdo);
    }

    public function index() {
        return $this->model->getAllFarms();
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['name', 'location'], $data);

            if (empty($errors)) {
                try {
                    if ($this->model->createFarm(trim($data['name']), trim($data['location']))) {
                        SessionHelper::setFlash('success', 'Farm added successfully!');
                        header('Location: index.php?module=Farm&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add farm.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save farm. Please try again.";
                }
            }

            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAllFarms()];
        }

        return ['records' => $this->model->getAllFarms()];
    }

    public function create() {
        return $this->add();
    }
}
?>
