<?php
require_once __DIR__ . '/LivestockModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class LivestockController {
    private $model;
    public function __construct($pdo) { $this->model = new LivestockModel($pdo); }

    private function viewData() {
        $animals = $this->model->getAllAnimals();
        return [
            'animals'       => $animals,
            'vaccinations'  => $this->model->getVaccinations(),
            'vaccAnimals'   => $animals,
        ];
    }

    public function index() { return $this->viewData(); }

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
            return array_merge(['errors' => $errors, 'data' => $data], $this->viewData());
        }
        return $this->viewData();
    }

    public function vaccinate() {
        $data = $_POST;
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fields = ['livestock_id', 'vaccine_name', 'vaccination_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (empty($errors)) {
                $nextDue = !empty($data['next_due_date']) ? $data['next_due_date'] : null;
                if ($this->model->addVaccination($data['livestock_id'], $data['vaccine_name'], $data['vaccination_date'], $nextDue)) {
                    SessionHelper::setFlash('success', 'Vaccination record added successfully!');
                    header('Location: /index.php?module=Livestock&action=manage');
                    exit;
                } else {
                    $errors['general'] = "Failed to add vaccination record.";
                }
            }
        }

        return array_merge(['errors' => $errors, 'data' => $data], $this->viewData());
    }
}