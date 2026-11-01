<?php
require_once __DIR__ . '/ProcurementModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Finance/FinanceService.php';
require_once __DIR__ . '/../Inventory/InventoryModel.php';

class ProcurementController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new ProcurementModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['supplier_id', 'item_name', 'quantity', 'cost', 'purchase_date'], $data);

            if (!isset($errors['purchase_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'purchase_date', 'Purchase date');
                if ($e) $errors['purchase_date'] = $e;
            }
            if (!isset($errors['supplier_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'suppliers', $data['supplier_id'], 'supplier');
                if ($e) $errors['supplier_id'] = $e;
            }
            if (!isset($errors['quantity'])) {
                $e = ValidationHelper::number($data, 'quantity', ['label' => 'Quantity', 'integer' => true, 'min' => 1]);
                if ($e) $errors['quantity'] = $e;
            }
            if (!isset($errors['cost'])) {
                $e = ValidationHelper::number($data, 'cost', ['label' => 'Cost', 'min' => 0]);
                if ($e) $errors['cost'] = $e;
            }

            if (empty($errors)) {
                $row = [
                    'supplier_id'   => (int) $data['supplier_id'],
                    'item_name'     => trim($data['item_name']),
                    'quantity'      => (int) $data['quantity'],
                    'cost'          => $data['cost'],
                    'purchase_date' => $data['purchase_date'],
                ];
                try {
                    if ($this->model->create($row)) {
                        $procId = $this->model->lastId();
                        // Cross-module: a purchase posts an expense (idempotent by source).
                        $item = strtolower(trim($data['item_name']));
                        $expCat = 'Other';
                        foreach (['seed' => 'Seeds', 'fertili' => 'Fertiliser', 'feed' => 'Animal feed',
                                  'vet' => 'Veterinary & medicines', 'drug' => 'Veterinary & medicines',
                                  'fuel' => 'Fuel', 'diesel' => 'Fuel', 'petrol' => 'Fuel',
                                  'chem' => 'Chemicals & pesticides', 'spray' => 'Chemicals & pesticides'] as $needle => $label) {
                            if (strpos($item, $needle) !== false) { $expCat = $label; break; }
                        }
                        (new FinanceService($this->pdo))->postSafe(
                            'expense', $expCat, $data['cost'],
                            'Purchase: ' . trim($data['item_name']) . ' x' . (int) $data['quantity'],
                            null, 'Procurement', $procId, $data['purchase_date'], 'paid'
                        );
                        // Cross-module: received purchase increases stock (match or create the item).
                        try {
                            $inv = new InventoryModel($this->pdo);
                            $itemId = $inv->findByNameOrClosest($data['item_name']);
                            if ($itemId === null) {
                                $ins = $this->pdo->prepare("INSERT INTO inventory (name, quantity, opening_quantity, unit, category) VALUES (?, 0, 0, 'pcs', ?) RETURNING id");
                                $ins->execute([trim($data['item_name']), $expCat]);
                                $itemId = (int) $ins->fetchColumn();
                            }
                            $inv->move((int) $itemId, 'in', (float) $data['quantity'], 'Purchase', 'Procurement #' . $procId, 'Procurement', $procId, (int) ($_SESSION['user_id'] ?? 0));
                        } catch (PDOException $e) {
                            error_log('Procurement stock-in failed: ' . $e->getMessage());
                        }
                        SessionHelper::setFlash('success', 'Procurement record added successfully!');
                        SessionHelper::flashCreated('Procurement', $procId, trim($data['item_name']), ['Quantity' => $data['quantity']]);
                        header('Location: index.php?module=Procurement&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add procurement record.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save procurement record: the selected supplier may no longer exist.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
