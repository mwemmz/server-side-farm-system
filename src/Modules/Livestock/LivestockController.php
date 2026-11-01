<?php
require_once __DIR__ . '/LivestockModel.php';
require_once __DIR__ . '/../Pest/PestModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Finance/FinanceService.php';
require_once __DIR__ . '/../Inventory/InventoryModel.php';

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
            'vaccAnimals'   => $this->model->getActiveAnimals(),
            'pests'         => $this->pestModel->getLivestockPests(),
            'vaccineCatalog'=> $this->model->getVaccineCatalog(),
            'treatments'    => $this->model->getTreatments(),
            'withdrawals'   => $withdrawals,
            'vaccSpecies'   => $this->model->getAnimalsWithSpecies(),
            'issueCatalog'  => $this->pestModel->catalogFor('livestock'),
        ];
    }

    public function index() { return $this->viewData(); }

    /** Shared validation for add + edit. Returns [errors, clean]. */
    private function validateAnimal(array $data, $exceptId = 0, $isEdit = false) {
        // Array-valued fields (name[]=x) are never valid: treat them as empty.
        $bad = [];
        foreach ($data as $k => $v) { if (is_array($v)) { $data[$k] = ''; $bad[] = $k; } }
        $errors = ValidationHelper::validateRequired(['farm_id', 'type', 'breed', 'dob'], $data);
        $farmId = null;
        if (!isset($errors['farm_id'])) {
            $farmId = ValidationHelper::id($data['farm_id']);
            $e = $farmId === null ? 'Please choose a valid farm.' : ValidationHelper::exists($this->pdo, 'farms', $farmId, 'farm');
            if ($e) $errors['farm_id'] = $e;
        }
        foreach (['type' => 'Type', 'breed' => 'Breed'] as $k => $lab) {
            if (isset($data[$k]) && is_array($data[$k])) $errors[$k] = $lab . ' is invalid.';
        }
        if (!isset($errors['dob']) && (is_array($data['dob']) || !ValidationHelper::dateNotFuture($data['dob'] ?? ''))) {
            $errors['dob'] = "Date of birth cannot be in the future.";
        }
        $tag = is_array($data['tag_no'] ?? '') ? "\0" : trim((string) ($data['tag_no'] ?? ''));
        if ($tag !== '' && !LivestockModel::tagValid($tag)) {
            $errors['tag_no'] = 'Tag may use letters, digits, dashes and spaces only (max 30 characters).';
        } elseif ($tag !== '' && $farmId !== null && $this->model->tagTaken($farmId, $tag, $exceptId)) {
            $errors['tag_no'] = 'Tag ' . $tag . ' is already used on this farm.';
        } elseif ($isEdit && $tag === '') {
            $errors['tag_no'] = 'An animal must keep a tag number.';
        }
        $name = is_array($data['name'] ?? '') ? "\0" : trim((string) ($data['name'] ?? ''));
        if ($name !== '' && (strlen($name) > 80 || preg_match('/[\x00-\x1f<>]/', $name))) $errors['name'] = 'Name is too long or has invalid characters (max 80).';
        $sex = is_array($data['sex'] ?? '') ? 'x' : strtolower(trim((string) ($data['sex'] ?? '')));
        if ($sex !== '' && !in_array($sex, ['male', 'female'], true)) $errors['sex'] = 'Choose male or female.';
        $status = strtolower(trim((string) ($data['status'] ?? 'active'))) ?: 'active';
        if (!in_array($status, ['active', 'sold', 'dead', 'culled'], true)) $errors['status'] = 'Choose a valid status.';
        $notes = is_array($data['notes'] ?? '') ? '' : trim((string) ($data['notes'] ?? ''));
        if (strlen($notes) > 2000) $errors['notes'] = 'Notes are too long (max 2000).';
        foreach ($bad as $k) { if (in_array($k, ['tag_no', 'name', 'sex', 'status', 'notes', 'farm_id', 'type', 'breed', 'dob'], true)) $errors[$k] = 'Invalid value submitted.'; }
        return [$errors, [
            'farm_id' => $farmId, 'type' => is_array($data['type'] ?? '') ? '' : trim((string) ($data['type'] ?? '')),
            'breed' => is_array($data['breed'] ?? '') ? '' : trim((string) ($data['breed'] ?? '')), 'dob' => is_array($data['dob'] ?? '') ? '' : trim((string) ($data['dob'] ?? '')),
            'tag_no' => $tag, 'name' => $name, 'sex' => $sex, 'status' => $status, 'notes' => $notes,
        ]];
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            list($errors, $c) = $this->validateAnimal($data);
            if (empty($errors)) {
                try {
                    if ($this->model->createAnimal($c['farm_id'], $c['type'], $c['breed'], $c['dob'], $c['tag_no'], $c['name'], $c['sex'], $c['notes'])) {
                        SessionHelper::setFlash('success', 'Animal added successfully!');
                        SessionHelper::flashCreated('Livestock', $this->model->lastId(), $c['type'] . ' ' . $c['breed'], ['Tag' => $this->model->lastTag, 'Type' => $c['type'], 'Breed' => $c['breed']]);
                        header('Location: index.php?module=Livestock&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add animal.";
                } catch (PDOException $e) {
                    error_log('Livestock add failed: ' . $e->getMessage());
                    if ($e->getCode() === '23505') {
                        $errors['tag_no'] = 'That tag is already used on this farm. Leave it empty to auto-generate one.';
                    } else {
                        $errors['general'] = "Could not save animal: the selected farm may no longer exist.";
                    }
                }
            }
            return array_merge(['errors' => $errors, 'data' => $data], $this->viewData());
        }
        return $this->viewData();
    }

    /** Edit an animal (GET shows the form, POST saves). */
    public function edit() {
        $id = ValidationHelper::id($_GET['id'] ?? ($_POST['id'] ?? ''));
        $animal = $id !== null ? $this->model->getAnimal($id) : null;
        if (!$animal) {
            return array_merge(['mode' => 'edit', 'notFound' => true], $this->viewData());
        }
        $errors = [];
        $data = $animal;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = array_merge($animal, $_POST);
            list($errors, $c) = $this->validateAnimal($_POST, $id, true);
            if (empty($errors)) {
                try {
                    $this->model->updateAnimal($id, $c);
                    SessionHelper::setFlash('success', 'Animal updated.');
                    SessionHelper::flashCreated('Livestock', $id, $c['tag_no'] . ' ' . $c['breed'], ['Tag' => $c['tag_no'], 'Status' => ucfirst($c['status'])]);
                    header('Location: index.php?module=Livestock&action=profile&id=' . $id);
                    exit;
                } catch (PDOException $e) {
                    error_log('Livestock edit failed: ' . $e->getMessage());
                    $errors['general'] = $e->getCode() === '23505' ? 'That tag is already used on this farm.' : 'Could not save the animal.';
                }
            }
        }
        return array_merge(['mode' => 'edit', 'animal' => $animal, 'errors' => $errors, 'data' => $data], $this->viewData());
    }

    /** Animal profile: details + vaccinations, treatments and health incidents. */
    public function profile() {
        $id = ValidationHelper::id($_GET['id'] ?? '');
        $animal = $id !== null ? $this->model->getAnimal($id) : null;
        if (!$animal) {
            return array_merge(['mode' => 'profile', 'notFound' => true], $this->viewData());
        }
        $same = function ($r) use ($id) { return (int) ($r['livestock_id'] ?? 0) === $id; };
        $v = $this->viewData();
        return array_merge($v, [
            'mode' => 'profile', 'animal' => $animal,
            'pVacc' => array_values(array_filter($v['vaccinations'], $same)),
            'pTreat' => array_values(array_filter($v['treatments'], $same)),
            'pIncidents' => array_values(array_filter($v['pests'], $same)),
        ]);
    }

    /** JSON: the tag the next animal of this type on this farm would get. */
    public function nextTag() {
        header('Content-Type: application/json; charset=UTF-8');
        $farm = ValidationHelper::id($_GET['farm_id'] ?? '');
        $type = is_array($_GET['type'] ?? '') ? '' : trim((string) ($_GET['type'] ?? ''));
        if ($type === '') { echo json_encode(['success' => true, 'tag' => '']); exit; }
        echo json_encode(['success' => true, 'tag' => $this->model->nextTag($farm ?? 0, $type)]);
        exit;
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

            // The species always comes from the animal record, never from the
            // form — that stops a disease vaccine reaching the wrong species.
            $animalType = $this->model->getAnimalType((int) ($data['livestock_id'] ?? 0));
            $vac = $this->model->vaccineForSpecies(trim((string) ($data['vaccine_name'] ?? '')), $animalType);
            if (!$vac['match']) {
                $errors['vaccine_name'] = trim($data['vaccine_name'])
                    . ' is for ' . implode(' / ', $vac['species'])
                    . ' — not this ' . ($animalType ? ucfirst($animalType) : 'animal') . '.';
            }
            $catalog = $vac['row'];

            if (empty($errors)) {
                // Auto-compute the next due date from the catalogue interval when
                // the user leaves it blank (server-side backstop for the JS helper).
                $nextDue = !empty($data['next_due_date']) ? $data['next_due_date'] : null;
                if ($catalog && empty($catalog['dose_interval_days']) && empty($catalog['booster_after_days'])) {
                    // Single-dose vaccine (Marek's is given at day-old, no booster)
                    // — it must never carry a scheduled next dose.
                    $nextDue = null;
                } elseif ($nextDue === null && $catalog && !empty($catalog['dose_interval_days']) && !empty($data['vaccination_date'])) {
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
            $ids = is_array($ids) ? $ids : [];
            $idList = [];
            $rejected = 0;
            foreach ($ids as $v) {
                $id = ValidationHelper::id($v);
                if ($id === null) { $rejected++; continue; }
                $idList[$id] = true;
            }
            $idList = array_keys($idList);
            if ($rejected > 0) {
                $errors['animal_ids'] = $rejected . ' invalid animal id(s) were submitted. Try again.';
            } elseif (!$idList) {
                $errors['animal_ids'] = "Select at least one animal.";
            } elseif (count($idList) > 500) {
                $errors['animal_ids'] = "A batch is limited to 500 animals — please split it.";
            }
            $errors = array_merge($errors, ValidationHelper::validateRequired(['vaccine_name', 'vaccination_date'], $data));

            if (!isset($errors['vaccination_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'vaccination_date', 'Vaccination date');
                if ($e) $errors['vaccination_date'] = $e;
            }

            // The species and vaccine checks run over a validated id list, and the
            // insert below only ever runs when the whole batch validated — the old
            // bug stored the batch while a species error was still on display.
            if (empty($errors) && $idList) {
                $types = $this->model->getAnimalTypes($idList);
                if (count($types) !== count($idList)) {
                    $errors['animal_ids'] = (count($idList) - count($types)) . ' selected animal(s) are no longer available. Remove them and try again.';
                } else {
                    $keys = [];
                    foreach ($types as $type) {
                        $k = $this->model->speciesKey($type);
                        if ($k !== '') $keys[$k] = true;
                    }
                    $speciesLabels = array_keys($keys);
                    if (count($keys) > 1) {
                        $errors['animal_ids'] = "One vaccine batch = one species. Remove the animals that aren't " . ucfirst($speciesLabels[0]) . '.';
                    }
                    $firstType = $types ? reset($types) : '';
                    $vac = $this->model->vaccineForSpecies(trim((string) ($data['vaccine_name'] ?? '')), $firstType);
                    if (!$vac['match']) {
                        $errors['general'] = trim($data['vaccine_name'])
                            . ' is for ' . implode(' / ', $vac['species'])
                            . ' — not this ' . ($firstType ? ucfirst($firstType) : 'animal') . '.';
                    }
                }
            }
            $catalog = isset($vac['row']) ? $vac['row'] : null;

            if (empty($errors)) {
                $nextDue = !empty($data['next_due_date']) ? $data['next_due_date'] : null;
                if ($catalog && empty($catalog['dose_interval_days']) && empty($catalog['booster_after_days'])) {
                    // Single-dose vaccine (Marek's is given at day-old, no booster)
                    // — it must never carry a scheduled next dose.
                    $nextDue = null;
                } elseif ($nextDue === null && $catalog && !empty($catalog['dose_interval_days'])) {
                    $nextDue = date('Y-m-d', strtotime($data['vaccination_date'] . ' +' . (int) $catalog['dose_interval_days'] . ' days'));
                }
                $count = $this->model->batchVaccinate(
                    $idList,
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
                        $mv = (new InventoryModel($this->pdo))->move(
                            (int) $data['inventory_item_id'], 'out', (float) $data['quantity_used'],
                            'Treatment', 'Treatment: ' . trim($data['treatment_name']), 'Livestock', (int) $data['livestock_id']
                        );
                        if ($mv !== true) {
                            throw new RuntimeException('Could not deduct stock: ' . $mv);
                        }
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
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    error_log('Add treatment failed: ' . $e->getMessage());
                    $errors['general'] = "Could not save the treatment: the selected animal or item may no longer exist, or there is not enough stock for the treatment.";
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
        $stockItemId = ($_POST['inventory_item_id'] ?? '') !== '' ? (int) $_POST['inventory_item_id'] : null;
        $stockQty = ($_POST['quantity_used'] ?? '') !== '' ? (float) $_POST['quantity_used'] : null;
        if ($this->pestModel->setStatus($id, $status, $_POST['notes'] ?? null, $stockItemId, $stockQty)) {
            SessionHelper::setFlash('success', 'Health incident marked ' . htmlspecialchars($status) . '.');
        } else {
            SessionHelper::setFlash('error', 'Could not update the health incident, or there is not enough stock for the treatment.');
        }
        header('Location: index.php?module=Livestock&action=manage');
        exit;
    }
}
?>
