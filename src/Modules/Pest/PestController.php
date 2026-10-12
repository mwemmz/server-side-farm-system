<?php
require_once __DIR__ . '/PestModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class PestController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new PestModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['farm_id', 'pest_name', 'detected_date', 'action_taken'], $data);
            if (!isset($errors['farm_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'farms', $data['farm_id'], 'farm');
                if ($e) $errors['farm_id'] = $e;
            }
            if (empty($errors)) {
                $row = [
                    'farm_id'       => (int) $data['farm_id'],
                    'pest_name'     => trim($data['pest_name']),
                    'severity'      => (trim($data['severity'] ?? '') !== '' ? trim($data['severity']) : null),
                    'detected_date' => $data['detected_date'],
                    'action_taken'  => trim($data['action_taken']),
                ];
                try {
                    if ($this->model->create($row)) {
                        SessionHelper::setFlash('success', 'Pest record added successfully!');
                        SessionHelper::flashCreated('Pest', $this->model->lastId(), trim($data['pest_name']));
                        header('Location: index.php?module=Pest&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add pest record.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save pest record: the selected farm may no longer exist.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
