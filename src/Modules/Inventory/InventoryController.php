<?php
require_once __DIR__ . '/InventoryModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class InventoryController {
    private $model;
    public function __construct($pdo) { $this->model = new InventoryModel($pdo); }
    public function index() { return $this->model->getAllItems(); }
    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['name', 'quantity', 'unit'];
            $errors = ValidationHelper::validateRequired($fields, $data);
            if (empty($errors)) {
                if ($this->model->addItem($data['name'], $data['quantity'], $data['unit'])) {
                    SessionHelper::setFlash('success', 'Item added successfully!');
                    header('Location: /index.php?module=Inventory&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add item.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'items' => $this->model->getAllItems()];
        }
        return ['items' => $this->model->getAllItems()];
    }
}
?>
