<?php
require_once __DIR__ . '/LivestockModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class LivestockController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new LivestockModel($pdo); }

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
            if (!isset($errors['farm_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'farms', $data['farm_id'], 'farm');
                if ($e) $errors['farm_id'] = $e;
            }
            if (empty($errors)) {
                try {
                    if ($this->model->createAnimal((int) $data['farm_id'], trim($data['type']), trim($data['breed']), trim($data['dob']))) {
                        SessionHelper::setFlash('success', 'Animal added successfully!');
                        SessionHelper::flashCreated('Livestock', $this->model->lastId(), trim($data['type']) . ' ' . trim($data['breed']), ['Type' => trim($data['type']), 'Breed' => trim($data['breed'])]);
                        header('Location: index.php?module=Livestock&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add animal.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save animal: the selected farm may no longer exist.";
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

            if (!isset($errors['livestock_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'livestock', $data['livestock_id'], 'animal');
                if ($e) $errors['livestock_id'] = $e;
            }

            if (empty($errors)) {
                $nextDue = !empty($data['next_due_date']) ? $data['next_due_date'] : null;
                if ($this->model->addVaccination($data['livestock_id'], $data['vaccine_name'], $data['vaccination_date'], $nextDue)) {
                    SessionHelper::setFlash('success', 'Vaccination record added successfully!');
                    SessionHelper::flashCreated('Livestock', $this->model->lastId(), trim($data['vaccine_name']), ['Vaccinated' => $data['vaccination_date'], 'Next due' => ($nextDue ?: '—')]);
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
?>
