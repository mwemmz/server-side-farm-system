<?php
require_once __DIR__ . '/LivestockModel.php';
require_once __DIR__ . '/../Pest/PestModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class LivestockController {
    private $model;
    private $pestModel;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new LivestockModel($pdo); $this->pestModel = new PestModel($pdo); }

    private function viewData() {
        $animals = $this->model->getAllAnimals();
        return [
            'animals'       => $animals,
            'vaccinations'  => $this->model->getVaccinations(),
            'vaccAnimals'   => $animals,
            'pests'         => $this->pestModel->getLivestockPests(),
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
            if (!isset($errors['dob']) && !ValidationHelper::dateNotFuture($data['dob'] ?? '')) {
                $errors['dob'] = "Date of birth cannot be in the future.";
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

            if (!isset($errors['vaccination_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'vaccination_date', 'Vaccination date');
                if ($e) $errors['vaccination_date'] = $e;
            }

            if (!isset($errors['vaccination_date']) && !isset($errors['next_due_date']) && !empty($data['next_due_date'])
                && strtotime((string) $data['next_due_date']) < strtotime((string) $data['vaccination_date'])) {
                $errors['next_due_date'] = "Next due date cannot be before the vaccination date.";
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

    /** Log a pest/disease against an animal. Folded in from the old standalone Pest module. */
    public function addPest() {
        $data = $_POST;
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = ValidationHelper::validateRequired(['livestock_id', 'pest_name', 'detected_date'], $data);

            if (!isset($errors['detected_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'detected_date', 'Detected date');
                if ($e) $errors['detected_date'] = $e;
            }

            if (!isset($errors['livestock_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'livestock', $data['livestock_id'], 'animal');
                if ($e) $errors['livestock_id'] = $e;
            }

            if (empty($errors)) {
                $stmt = $this->pdo->prepare("SELECT farm_id, type, breed FROM livestock WHERE id = ?");
                $stmt->execute([(int) $data['livestock_id']]);
                $animal = $stmt->fetch();

                $row = [
                    'farm_id'       => $animal ? ($animal['farm_id'] ?: null) : null,
                    'livestock_id'  => (int) $data['livestock_id'],
                    'pest_name'     => trim($data['pest_name']),
                    'severity'      => (trim($data['severity'] ?? '') !== '' ? trim($data['severity']) : null),
                    'detected_date' => $data['detected_date'],
                    'action_taken'  => trim($data['action_taken'] ?? ''),
                ];
                try {
                    if ($this->pestModel->create($row)) {
                        SessionHelper::setFlash('success', 'Health record added successfully!');
                        $label = $animal ? (ucfirst((string) $animal['type']) . ' ' . ($animal['breed'] ?? '')) : ('Animal #' . (int) $data['livestock_id']);
                        SessionHelper::flashCreated('Livestock', $this->pestModel->lastId(), trim($data['pest_name']), ['Animal' => trim($label), 'Detected' => $data['detected_date']]);
                        header('Location: index.php?module=Livestock&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add health record.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save health record: the selected animal may no longer exist.";
                }
            }

            return array_merge(['pestErrors' => $errors, 'pestData' => $data], $this->viewData());
        }

        return $this->viewData();
    }
}
?>
