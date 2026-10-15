<?php
require_once __DIR__ . '/CropModel.php';
require_once __DIR__ . '/../Pest/PestModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class CropController {
    private $model;
    private $pestModel;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->model = new CropModel($pdo);
        $this->pestModel = new PestModel($pdo);
    }

    private function viewData() {
        return [
            'crops' => $this->model->getAllCrops(),
            'pests' => $this->pestModel->getCropPests(),
            'issueCatalog' => $this->pestModel->catalogFor('crop'),
        ];
    }

    public function index() {
        return $this->viewData();
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['farm_id', 'name', 'planting_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (!isset($errors['farm_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'farms', $data['farm_id'], 'farm');
                if ($e) $errors['farm_id'] = $e;
            }

            $variety = isset($data['variety']) && trim($data['variety']) !== '' ? trim($data['variety']) : null;
            $harvest = isset($data['expected_harvest_date']) && trim($data['expected_harvest_date']) !== '' ? trim($data['expected_harvest_date']) : null;

            if ($harvest !== null && !isset($errors['planting_date']) && $harvest < $data['planting_date']) {
                $errors['expected_harvest_date'] = "Harvest date cannot be earlier than the planting date.";
            }

            if (empty($errors)) {
                try {
                    if ($this->model->createCrop((int) $data['farm_id'], trim($data['name']), $variety, $data['planting_date'], $harvest)) {
                        SessionHelper::setFlash('success', 'Crop added successfully!');
                        SessionHelper::flashCreated('Crop', $this->model->lastId(), trim($data['name']), ['Planted' => $data['planting_date']]);
                        header('Location: index.php?module=Crop&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add crop.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save crop: the selected farm may no longer exist.";
                }
            }
            return array_merge(['errors' => $errors, 'data' => $data], $this->viewData());
        }
        return $this->viewData();
    }

    /** Log a pest/disease against a crop. Folded in from the old standalone Pest module. */
    public function addPest() {
        $data = $_POST;
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = ValidationHelper::validateRequired(['crop_id', 'pest_name', 'detected_date'], $data);

            if (!isset($errors['detected_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'detected_date', 'Detected date');
                if ($e) $errors['detected_date'] = $e;
            }

            if (!isset($errors['crop_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'crops', $data['crop_id'], 'crop');
                if ($e) $errors['crop_id'] = $e;
            }

            if (empty($errors)) {
                $stmt = $this->pdo->prepare("SELECT farm_id, name FROM crops WHERE id = ?");
                $stmt->execute([(int) $data['crop_id']]);
                $crop = $stmt->fetch();

                $row = [
                    'farm_id'       => $crop ? ($crop['farm_id'] ?: null) : null,
                    'crop_id'       => (int) $data['crop_id'],
                    'pest_name'     => trim($data['pest_name']),
                    'issue_kind'    => (trim($data['issue_kind'] ?? '') !== '' ? trim($data['issue_kind']) : 'pest'),
                    'severity'      => (trim($data['severity'] ?? '') !== '' ? trim($data['severity']) : null),
                    'detected_date' => $data['detected_date'],
                    'action_taken'  => trim($data['action_taken'] ?? ''),
                ];
                try {
                    if ($this->pestModel->create($row)) {
                        SessionHelper::setFlash('success', 'Pest record added successfully!');
                        SessionHelper::flashCreated('Crop', $this->pestModel->lastId(), trim($data['pest_name']), ['Crop' => ($crop['name'] ?? '—'), 'Detected' => $data['detected_date']]);
                        header('Location: index.php?module=Crop&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add pest record.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save pest record: the selected crop may no longer exist.";
                }
            }

            return array_merge(['pestErrors' => $errors, 'pestData' => $data], $this->viewData());
        }

        return $this->viewData();
    }

    /** Mark a crop health incident treated or resolved. */
    public function incidentStatus() {
        $id = ValidationHelper::id($_POST['id'] ?? $_GET['id'] ?? '');
        $status = $_POST['status'] ?? 'resolved';
        $stockItemId = ($_POST['inventory_item_id'] ?? '') !== '' ? (int) $_POST['inventory_item_id'] : null;
        $stockQty = ($_POST['quantity_used'] ?? '') !== '' ? (float) $_POST['quantity_used'] : null;
        if ($id === null) {
            SessionHelper::setFlash('error', 'Invalid or missing health incident id.');
        } elseif ($this->pestModel->setStatus($id, $status, $_POST['notes'] ?? null, $stockItemId, $stockQty)) {
            SessionHelper::setFlash('success', 'Health incident marked ' . htmlspecialchars($status) . '.');
        } else {
            SessionHelper::setFlash('error', 'Could not update the health incident, or there is not enough stock for the treatment.');
        }
        header('Location: index.php?module=Crop&action=manage');
        exit;
    }
}
?>
