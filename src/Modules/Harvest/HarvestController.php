<?php
require_once __DIR__ . '/HarvestModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class HarvestController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new HarvestModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['crop_id', 'harvest_date', 'quantity'], $data);

            if (!isset($errors['harvest_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'harvest_date', 'Harvest date');
                if ($e) $errors['harvest_date'] = $e;
            }

            if (!isset($errors['crop_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'crops', $data['crop_id'], 'crop');
                if ($e) $errors['crop_id'] = $e;
            }
            if (!isset($errors['quantity'])) {
                $e = ValidationHelper::number($data, 'quantity', ['label' => 'Quantity', 'positive' => true]);
                if ($e) $errors['quantity'] = $e;
            }

            // Optional "Store in" facility (B7).
            $storageId = (!empty($data['storage_record_id'])) ? (int) $data['storage_record_id'] : null;
            if ($storageId) {
                $e = ValidationHelper::exists($this->pdo, 'storage_records', $storageId, 'storage facility');
                if ($e) $errors['storage_record_id'] = $e;
            }

            if (empty($errors)) {
                $quality = trim($data['quality'] ?? '');
                $row = [
                    'crop_id'      => (int) $data['crop_id'],
                    'harvest_date' => $data['harvest_date'],
                    'quantity'     => $data['quantity'],
                    'quality'      => $quality !== '' ? $quality : null,
                ];
                $res = $this->model->createHarvest($row, $storageId);
                if (!empty($res['ok'])) {
                    SessionHelper::setFlash('success', 'Harvest record added successfully!');
                    SessionHelper::flashCreated('Harvest', $res['id'], 'Harvest record', ['Quantity (kg)' => $data['quantity']]);
                    header('Location: index.php?module=Harvest&action=manage');
                    exit;
                }
                $errors['general'] = $res['error'] ?? 'Could not save harvest record.';
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll(), 'storageRecords' => $this->model->storageRecords()];
        }
        return ['records' => $this->model->getAll(), 'storageRecords' => $this->model->storageRecords()];
    }
}
