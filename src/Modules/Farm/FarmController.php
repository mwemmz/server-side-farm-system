<?php
require_once __DIR__ . '/FarmModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/AuditHelper.php';

class FarmController {
    private $model;

    public function __construct($pdo) {
        $this->model = new FarmModel($pdo);
    }

    public function index() {
        return $this->model->getAllFarms();
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['name', 'location'], $data);

            if (empty($errors)) {
                try {
                    if ($this->model->createFarm(trim($data['name']), trim($data['location']))) {
                        SessionHelper::setFlash('success', 'Farm added successfully!');
                        SessionHelper::flashCreated('Farm', $this->model->lastId(), trim($data['name']), ['Location' => trim($data['location'])]);
                        header('Location: index.php?module=Farm&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add farm.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save farm. Please try again.";
                }
            }

            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAllFarms()];
        }

        return ['records' => $this->model->getAllFarms()];
    }

    public function edit() {
        $id = ValidationHelper::id($_GET['id'] ?? $_POST['id'] ?? '');
        if (!$id) {
            SessionHelper::setFlash('error', 'Select a farm to edit.');
            header('Location: index.php?module=Farm&action=manage'); exit;
        }
        $record = $this->model->getById($id);
        if (!$record) {
            SessionHelper::setFlash('error', 'That farm no longer exists.');
            header('Location: index.php?module=Farm&action=manage'); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['records' => $this->model->getAllFarms(), 'editRecord' => $record];
        }
        $data = $_POST;
        $errors = ValidationHelper::validateRequired(['name', 'location'], $data);
        if ($errors) return ['records' => $this->model->getAllFarms(), 'editRecord' => array_merge($record, $data), 'errors' => $errors];
        try {
            $this->model->update($id, ['name' => trim($data['name']), 'location' => trim($data['location'])]);
            AuditHelper::log($this->model->getPdo(), 'human', 'farm_updated', 'farms', $id, $data, $_SESSION['user_id'] ?? null);
            SessionHelper::setFlash('success', 'Farm updated.');
        } catch (Throwable $e) {
            error_log('Farm update failed: ' . $e->getMessage());
            SessionHelper::setFlash('error', 'Could not update farm.');
        }
        header('Location: index.php?module=Farm&action=manage'); exit;
    }

    public function create() {
        return $this->add();
    }
}
?>
