<?php
require_once __DIR__ . '/MarketModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/AuditHelper.php';

class MarketController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new MarketModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['crop_name', 'price', 'market_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (!isset($errors['market_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'market_date', 'Market date');
                if ($e) $errors['market_date'] = $e;
            }
            if (!isset($errors['price'])) {
                $e = ValidationHelper::number($data, 'price', ['label' => 'Price', 'min' => 0]);
                if ($e) $errors['price'] = $e;
            }

            if (empty($errors)) {
                $row = array_intersect_key($data, array_flip(['crop_name', 'price', 'market_date']));
                try {
                    if ($this->model->create($row)) {
                        SessionHelper::setFlash('success', 'Market data added successfully!');
                        SessionHelper::flashCreated('Market', $this->model->lastId(), trim($data['crop_name']), ['Price (K/kg)' => $data['price']]);
                        header('Location: index.php?module=Market&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add market data.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save market data. Please try again.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }

    public function edit() {
        $id = ValidationHelper::id($_GET['id'] ?? $_POST['id'] ?? '');
        if (!$id) {
            SessionHelper::setFlash('error', 'Select a market record to edit.');
            header('Location: index.php?module=Market&action=manage'); exit;
        }
        $record = $this->model->getById($id);
        if (!$record) {
            SessionHelper::setFlash('error', 'That market record no longer exists.');
            header('Location: index.php?module=Market&action=manage'); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['records' => $this->model->getAll(), 'editRecord' => $record];
        }
        $data = $_POST;
        $errors = ValidationHelper::validateRequired(['crop_name', 'price', 'market_date'], $data);
        if (!isset($errors['market_date'])) {
            $e = ValidationHelper::dateNotFuture($data, 'market_date', 'Market date');
            if ($e) $errors['market_date'] = $e;
        }
        if (!isset($errors['price'])) {
            $e = ValidationHelper::number($data, 'price', ['label' => 'Price', 'min' => 0]);
            if ($e) $errors['price'] = $e;
        }
        if ($errors) return ['records' => $this->model->getAll(), 'editRecord' => array_merge($record, $data), 'errors' => $errors];
        try {
            $this->model->update($id, [
                'crop_name' => trim($data['crop_name']),
                'price' => $data['price'],
                'market_date' => $data['market_date'],
            ]);
            AuditHelper::log($this->pdo ?? null, 'human', 'market_record_updated', 'market_data', $id, $data, $_SESSION['user_id'] ?? null);
            SessionHelper::setFlash('success', 'Market record updated.');
        } catch (Throwable $e) {
            SessionHelper::setFlash('error', 'Could not update market record.');
        }
        header('Location: index.php?module=Market&action=manage'); exit;
    }
}
?>
