<?php
require_once __DIR__ . '/LivestockModel.php';
require_once __DIR__ . '/../Pest/PestModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Finance/FinanceService.php';

class LivestockController {
    private $model;
    private $pestModel;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new LivestockModel($pdo); $this->pestModel = new PestModel($pdo); }

    private function viewData() {
        $animals = $this->model->getAllAnimals();
        $withdrawals = [];
        foreach ($this->model->getActiveWithdrawals() as $w) {
            $withdrawals[(int) $w['livestock_id']] = $w['until'];
        }
        return [
            'animals'       => $animals,
            'vaccinations'  => $this->model->getVaccinations(),
            'vaccAnimals'   => $animals,
            'pests'         => $this->pestModel->getLivestockPests(),
            'vaccineCatalog'=> $this->model->getVaccineCatalog(),
            'treatments'    => $this->model->getTreatments(),
            'withdrawals'   => $withdrawals,
            'vaccSpecies'   => $this->model->getAnimalsWithSpecies(),
            'issueCatalog'  => $this->pestModel->catalogFor('livestock'),
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
                // Auto-compute the next due date from the catalogue interval when
                // the user leaves it blank (server-side backstop for the JS helper).
                $nextDue = !empty($data['next_due_date']) ? $data['next_due_date'] : null;
                $catalog = $this->model->getVaccineCatalogByName(trim($data['vaccine_name']));
                if ($nextDue === null && $catalog && !empty($catalog['dose_interval_days']) && !empty($data['vaccination_date'])) {
                    $nextDue = date('Y-m-d', strtotime($data['vaccination_date'] . ' +' . (int) $catalog['dose_interval_days'] . ' days'));
                }
                $extra = [
                    'dose_number'       => ($data['dose_number'] ?? '') !== '' ? (int) $data['dose_number'] : null,
                    'batch_number'      => trim($data['batch_number'] ?? '') ?: null,
                    'cost'              => ($data['cost'] ?? '') !== '' ? (float) $data['cost'] : null,
                    'vaccine_catalog_id'=> $catalog ? (int) $catalog['id'] : null,
                ];
                if ($this->model->addVaccination($data['livestock_id'], $data['vaccine_name'], $data['vaccination_date'], $nextDue, $extra)) {
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

    /** Vaccinate several animals at once (one vaccine + date) in a single transaction. */
    public function batchVaccinate() {
        $data = $_POST;
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids = $data['animal_ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                $errors['animal_ids'] = "Select at least one animal.";
            }
            $errors = array_merge($errors, ValidationHelper::validateRequired(['vaccine_name', 'vaccination_date'], $data));

            if (!isset($errors['vaccination_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'vaccination_date', 'Vaccination date');
                if ($e) $errors['vaccination_date'] = $e;
            }

            if (empty($errors)) {
                $catalog = $this->model->getVaccineCatalogByName(trim($data['vaccine_name']));
                $nextDue = !empty($data['next_due_date']) ? $data['next_due_date'] : null;
                if ($nextDue === null && $catalog && !empty($catalog['dose_interval_days'])) {
                    $nextDue = date('Y-m-d', strtotime($data['vaccination_date'] . ' +' . (int) $catalog['dose_interval_days'] . ' days'));
                }
                $count = $this->model->batchVaccinate(
                    array_map('intval', $ids),
                    trim($data['vaccine_name']),
                    $data['vaccination_date'],
                    $nextDue,
                    ['batch_number' => trim($data['batch_number'] ?? '') ?: null, 'vaccine_catalog_id' => $catalog ? (int) $catalog['id'] : null]
                );
                if ($count !== false) {
                    SessionHelper::setFlash('success', "Vaccination recorded for {$count} animal(s).");
                    header('Location: index.php?module=Livestock&action=manage');
                    exit;
                }
                $errors['general'] = "Could not record the batch vaccination.";
            }

            return array_merge(['batchErrors' => $errors, 'batchData' => $data], $this->viewData());
        }

        return $this->viewData();
    }

    /** Record a treatment; optionally draws down stock and logs the cost. */
    public function addTreatment() {
        $data = $_POST;
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = ValidationHelper::validateRequired(['livestock_id', 'treatment_name', 'treatment_date'], $data);

            if (!isset($errors['livestock_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'livestock', $data['livestock_id'], 'animal');
                if ($e) $errors['livestock_id'] = $e;
            }
            if (!isset($errors['treatment_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'treatment_date', 'Treatment date');
                if ($e) $errors['treatment_date'] = $e;
            }
            if (!empty($data['inventory_item_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'inventory', $data['inventory_item_id'], 'inventory item');
                if ($e) $errors['inventory_item_id'] = $e;
            }

            if (empty($errors)) {
                $withdrawDays = ($data['withdrawal_days'] ?? '') !== '' ? (int) $data['withdrawal_days'] : null;
                $data['withdrawal_ends'] = ($withdrawDays !== null && !empty($data['treatment_date']))
                    ? date('Y-m-d', strtotime($data['treatment_date'] . ' +' . $withdrawDays . ' days'))
                    : null;

                try {
                    $this->pdo->beginTransaction();
                    if (!$this->model->addTreatment($data)) {
                        throw new PDOException('insert failed');
                    }
                    if (!empty($data['inventory_item_id']) && ($data['quantity_used'] ?? '') !== '') {
                        $mv = $this->pdo->prepare(
                            "INSERT INTO stock_movements (item_id, direction, quantity, reason, ref_module, ref_id, note)
                             VALUES (?, 'out', ?, 'Treated livestock', 'Livestock', ?, ?)"
                        );
                        $mv->execute([
                            (int) $data['inventory_item_id'],
                            (float) $data['quantity_used'],
                            (int) $data['livestock_id'],
                            'Treatment: ' . trim($data['treatment_name']),
                        ]);
                    }
                    $treatmentId = $this->model->lastId();
                    $this->pdo->commit();
                    // Cross-module: a paid treatment posts an expense (idempotent by source).
                    if (($data['cost'] ?? '') !== '') {
                        (new FinanceService($this->pdo))->postSafe(
                            'expense', 'Veterinary & medicines', $data['cost'],
                            'Animal treatment: ' . trim($data['treatment_name']),
                            null, 'Treatment', $treatmentId, $data['treatment_date'], 'paid'
                        );
                    }
                    SessionHelper::setFlash('success', 'Treatment recorded successfully!');
                    SessionHelper::flashCreated('Livestock', $treatmentId, trim($data['treatment_name']), ['Date' => $data['treatment_date'], 'Withdrawal ends' => ($data['withdrawal_ends'] ?: '—')]);
                    header('Location: index.php?module=Livestock&action=manage');
                    exit;
                } catch (PDOException $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    error_log('Add treatment failed: ' . $e->getMessage());
                    $errors['general'] = "Could not save the treatment: the selected animal or item may no longer exist.";
                }
            }

            return array_merge(['treatmentErrors' => $errors, 'treatmentData' => $data], $this->viewData());
        }

        return $this->viewData();
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
                    'issue_kind'    => (trim($data['issue_kind'] ?? '') !== '' ? trim($data['issue_kind']) : 'disease'),
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

    /** Mark an animal health incident treated or resolved. */
    public function incidentStatus() {
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $status = $_POST['status'] ?? 'resolved';
        if ($this->pestModel->setStatus($id, $status, $_POST['notes'] ?? null)) {
            SessionHelper::setFlash('success', 'Health incident marked ' . htmlspecialchars($status) . '.');
        } else {
            SessionHelper::setFlash('error', 'Could not update the health incident.');
        }
        header('Location: index.php?module=Livestock&action=manage');
        exit;
    }
}
?>
