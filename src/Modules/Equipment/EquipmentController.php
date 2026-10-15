<?php
require_once __DIR__ . '/EquipmentModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class EquipmentController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new EquipmentModel($pdo); }

    /** List (also feeds the Equipment control panel). */
    public function index() { return $this->model->getAllEquipment(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['name', 'status'], $data);
            if (!isset($errors['purchase_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'purchase_date', 'Purchase date');
                if ($e) $errors['purchase_date'] = $e;
            }
            if (empty($errors)) {
                try {
                    if ($this->model->addEquipment(
                        trim($data['name']), trim($data['status']),
                        trim($data['equipment_type'] ?? ''), trim($data['farm_id'] ?? ''),
                        trim($data['purchase_date'] ?? ''), trim($data['service_interval_days'] ?? '')
                    )) {
                        SessionHelper::setFlash('success', 'Equipment added successfully!');
                        SessionHelper::flashCreated('Equipment', $this->model->lastId(), trim($data['name']), ['Status' => trim($data['status'])]);
                        header('Location: index.php?module=Equipment&action=manage');
                        exit;
                    }
                    $errors['general'] = 'Failed to add equipment.';
                } catch (PDOException $e) {
                    $errors['general'] = 'Could not save equipment.';
                }
            }
            return ['errors' => $errors, 'data' => $data, 'items' => $this->model->getAllEquipment()];
        }
        return ['items' => $this->model->getAllEquipment()];
    }

    /** Record a service: reset the schedule, optional cost + optional spare part. */
    public function recordService() {
        $id = ValidationHelper::id($_POST['id'] ?? '');
        $data = $_POST;
        $errors = [];
        if ($id === null) $errors['general'] = 'Missing equipment.';
        if (!isset($errors['general'])) {
            $e = ValidationHelper::dateNotFuture($data, 'service_date', 'Service date');
            if ($e) $errors['service_date'] = $e;
        }

        if (empty($errors)) {
            $cost = (isset($data['cost']) && trim((string) $data['cost']) !== '') ? (float) $data['cost'] : null;
            $itemId = (!empty($data['inventory_item_id'])) ? (int) $data['inventory_item_id'] : null;
            $qty = (isset($data['quantity_used']) && trim((string) $data['quantity_used']) !== '') ? (float) $data['quantity_used'] : null;
            $res = $this->model->recordService(
                $id,
                $data['service_date'] ?: date('Y-m-d'),
                $cost, $itemId, $qty,
                trim($data['note'] ?? ''),
                $_SESSION['user_id'] ?? null
            );
            if (!empty($res['ok'])) {
                SessionHelper::setFlash('success', 'Service recorded — next service clock reset.');
            } else {
                SessionHelper::setFlash('error', $res['error'] ?? 'Could not record the service.');
            }
        } else {
            SessionHelper::setFlash('error', reset($errors));
        }
        header('Location: index.php?module=Equipment&action=manage');
        exit;
    }
}
