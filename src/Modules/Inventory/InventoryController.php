<?php
require_once __DIR__ . '/InventoryModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class InventoryController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new InventoryModel($pdo); }

    private function filters() {
        return [
            'category' => $_GET['category'] ?? '',
            'purpose'  => $_GET['purpose'] ?? '',
        ];
    }

    public function index() {
        $f = $this->filters();
        return [
            'items'    => $this->model->getItemsWithBalance($f),
            'movements'=> $this->model->getMovements(null, 25),
            'filters'  => $f,
        ];
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['name', 'quantity', 'unit'];
            $errors = ValidationHelper::validateRequired($fields, $data);
            if (!isset($errors['quantity'])) {
                $e = ValidationHelper::number($data, 'quantity', ['label' => 'Quantity', 'min' => 0]);
                if ($e) $errors['quantity'] = $e;
            }
            if (empty($errors)) {
                if ($this->model->addItem(trim($data['name']), $data['quantity'], trim($data['unit']), trim($data['category'] ?? ''))) {
                    SessionHelper::setFlash('success', 'Item added successfully!');
                    SessionHelper::flashCreated('Inventory', $this->model->lastId(), trim($data['name']), ['Quantity' => $data['quantity'] . ' ' . $data['unit']]);
                    header('Location: /index.php?module=Inventory&action=index');
                    exit;
                } else {
                    $errors['general'] = "Failed to add item.";
                }
            }
            return array_merge($this->index(), ['errors' => $errors, 'data' => $data]);
        }
        return $this->index();
    }

    /** Stock in / stock out (shared handler keyed by the 'direction' field). */
    public function move() {
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $direction = $data['direction'] ?? '';
            $errors = ValidationHelper::validateRequired(['item_id', 'quantity'], $data);
            if (!in_array($direction, ['in', 'out'], true)) {
                $errors['direction'] = 'Direction must be "in" (stock received) or "out" (stock issued).';
            }
            if (!isset($errors['item_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'inventory', $data['item_id'], 'inventory item');
                if ($e) $errors['item_id'] = $e;
            }
            if (!isset($errors['quantity'])) {
                $e = ValidationHelper::number($data, 'quantity', ['label' => 'Quantity', 'positive' => true]);
                if ($e) $errors['quantity'] = $e;
            }

            if (empty($errors)) {
                $result = $this->model->move(
                    (int) $data['item_id'], $direction, (float) $data['quantity'],
                    trim($data['reason'] ?? ''), trim($data['note'] ?? ''),
                    'Manual', null, (int) ($_SESSION['user_id'] ?? 0)
                );
                if ($result === true) {
                    $item = $this->model->getItem((int) $data['item_id']);
                    if ($direction === 'out') {
                        $this->maybeNotifyLowStock($item);
                    }
                    SessionHelper::setFlash('success', 'Stock ' . ($direction === 'in' ? 'received' : 'issued') . ' successfully!');
                    header('Location: index.php?module=Inventory&action=index');
                    exit;
                }
                $errors['general'] = is_string($result) ? $result : 'Could not record stock movement.';
            }

            return array_merge($this->index(), ['moveErrors' => $errors, 'moveData' => $data, 'moveDirection' => $direction]);
        }
        return $this->index();
    }

    /** Item movement history (JSON-free, rendered by the view via ?action=history&id=). */
    public function history() {
        $itemId = (int) ($_GET['id'] ?? 0);
        return array_merge($this->index(), [
            'historyItem' => $this->model->getItem($itemId),
            'history'     => $this->model->getMovements($itemId, 100),
        ]);
    }

    /** Emit a single unread low-stock notification per item until it is restocked. */
    private function maybeNotifyLowStock($item) {
        if (!$item) return;
        $balance = $this->model->getBalance((int) $item['id']);
        $thr = null;
        $st = $this->pdo->prepare("SELECT threshold_qty FROM reorder_rules WHERE item_id = ?");
        $st->execute([(int) $item['id']]);
        $thr = $st->fetchColumn();
        if ($thr === false) { $thr = $item['min_level'] ?? null; }
        if ($thr === null || (float) $thr <= 0 || $balance >= (float) $thr) {
            return;
        }
        $msg = "Low stock alert: '{$item['name']}' balance {$balance} is below threshold {$thr}.";
        $chk = $this->pdo->prepare(
            "SELECT 1 FROM notifications
             WHERE is_read = FALSE AND related_module = 'Inventory' AND related_id = ? LIMIT 1"
        );
        $chk->execute([(int) $item['id']]);
        if ($chk->fetchColumn()) {
            return; // already an open alert for this item
        }
        try {
            $ins = $this->pdo->prepare(
                "INSERT INTO notifications (message, notif_type, related_module, related_id)
                 VALUES (?, 'Warning', 'Inventory', ?)"
            );
            $ins->execute([$msg, (int) $item['id']]);
        } catch (PDOException $e) {
            error_log('Low stock notify failed: ' . $e->getMessage());
        }
    }
}
