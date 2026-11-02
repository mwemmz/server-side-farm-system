<?php
require_once __DIR__ . '/HarvestModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/AuditHelper.php';
require_once __DIR__ . '/../../Helpers/UndoHelper.php';

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
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
        $id = ValidationHelper::id($_POST['id'] ?? '');
        if (!$id) { SessionHelper::setFlash('error', 'Invalid harvest.'); header('Location: index.php?module=Harvest&action=manage'); exit; }
        foreach (['harvest_losses' => 'harvest_id', 'packaging_records' => 'harvest_id', 'quality_grades' => 'harvest_id', 'produce_dispatches' => 'harvest_id', 'stored_produce' => 'harvest_id'] as $table => $col) {
            try {
                $st = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$col} = ?");
                $st->execute([$id]);
                if ((int)$st->fetchColumn() > 0) {
                    SessionHelper::setFlash('error', "Cannot delete harvest: it has records in {$table}. Remove those first.");
                    header('Location: index.php?module=Harvest&action=manage'); exit;
                }
            } catch (PDOException $e) { /* table may not exist */ }
        }
        try {
            $st = $this->pdo->prepare("SELECT * FROM harvest_records WHERE id = ?");
            $st->execute([$id]);
            $rowData = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $this->pdo->beginTransaction();
            $this->pdo->prepare("DELETE FROM harvest_records WHERE id = ?")->execute([$id]);
            AuditHelper::log($this->pdo, 'human', 'harvest_deleted', 'harvest_records', $id, [], (int)($_SESSION['user_id'] ?? 0));
            $this->pdo->commit();
            UndoHelper::stash($this->pdo, 'harvest_records', $rowData, 'Harvest', 'index.php?module=Harvest&action=manage');
            SessionHelper::setFlash('success', 'Harvest deleted.');
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            SessionHelper::setFlash('error', 'Could not delete harvest (it may be referenced elsewhere).');
        }
        header('Location: index.php?module=Harvest&action=manage'); exit;
    }}
