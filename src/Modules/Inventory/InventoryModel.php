<?php
class InventoryModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllItems() { return $this->pdo->query("SELECT * FROM inventory")->fetchAll(); }
    public function lastId() { return (int) $this->pdo->lastInsertId(); }
    public function addItem($name, $quantity, $unit, $category = null) {
        $stmt = $this->pdo->prepare("INSERT INTO inventory (name, quantity, unit, category) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$name, $quantity, $unit, ($category !== '' ? $category : null)]);
    }

    public function getItem($id) {
        $st = $this->pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $st->execute([(int) $id]);
        return $st->fetch();
    }

    /** Effective balance = opening quantity + (movements in - movements out). */
    public function getBalance($itemId) {
        $item = $this->getItem($itemId);
        if (!$item) return 0.0;
        $st = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity ELSE 0 END),0)
                  - COALESCE(SUM(CASE WHEN direction='out' THEN quantity ELSE 0 END),0) AS net,
                    COUNT(*) AS c
             FROM stock_movements WHERE item_id = ?"
        );
        $st->execute([(int) $itemId]);
        $m = $st->fetch();
        return (int) $m['c'] > 0 ? (float) $m['net'] : (float) $item['quantity'];
    }

    /** All items with computed balance, category/purpose filter and low-stock flag. */
    public function getItemsWithBalance($f = []) {
        $where = [];
        $args = [];
        if (!empty($f['category'])) { $where[] = "i.category = ?"; $args[] = $f['category']; }
        if (!empty($f['purpose']))  { $where[] = "i.used_for = ?"; $args[] = $f['purpose']; }
        $sql = "SELECT i.*,
                       COALESCE((SELECT SUM(CASE WHEN sm.direction='in' THEN sm.quantity ELSE 0 END)
                                      - SUM(CASE WHEN sm.direction='out' THEN sm.quantity ELSE 0 END)
                                 FROM stock_movements sm WHERE sm.item_id = i.id), NULL) AS movement_net,
                       (SELECT COUNT(*) FROM stock_movements sm WHERE sm.item_id = i.id) AS movement_count,
                       COALESCE(rr.threshold_qty, i.min_level) AS threshold
                FROM inventory i
                LEFT JOIN reorder_rules rr ON rr.item_id = i.id";
        if ($where) { $sql .= " WHERE " . implode(' AND ', $where); }
        $sql .= " ORDER BY i.category NULLS LAST, i.name";
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['balance'] = ((int) $r['movement_count'] > 0) ? (float) $r['movement_net'] : (float) $r['quantity'];
            $thr = $r['threshold'];
            $r['low'] = ($thr !== null && (float) $thr > 0 && $r['balance'] < (float) $thr);
        }
        unset($r);
        return $rows;
    }

    /**
     * Record a stock movement. For 'out' the item row is locked and the balance
     * is re-checked inside the transaction; if it would go below zero the
     * movement is refused (returns a string error).
     *
     * @return true|string true on success, or an error message
     */
    public function move($itemId, $direction, $qty, $reason = null, $note = null, $refModule = null, $refId = null, $userId = null) {
        $itemId = (int) $itemId;
        $qty = (float) $qty;
        if (!in_array($direction, ['in', 'out'], true)) return 'Invalid direction.';
        if ($qty <= 0) return 'Quantity must be greater than zero.';

        try {
            $this->pdo->beginTransaction();

            // Lock the item row (serialises concurrent stock-outs).
            $lock = $this->pdo->prepare("SELECT id, name, quantity FROM inventory WHERE id = ? FOR UPDATE");
            $lock->execute([$itemId]);
            $item = $lock->fetch();
            if (!$item) {
                $this->pdo->rollBack();
                return 'Inventory item not found.';
            }

            if ($direction === 'out') {
                $balSt = $this->pdo->prepare(
                    "SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity ELSE 0 END),0)
                          - COALESCE(SUM(CASE WHEN direction='out' THEN quantity ELSE 0 END),0) AS net,
                            COUNT(*) AS c
                     FROM stock_movements WHERE item_id = ?"
                );
                $balSt->execute([$itemId]);
                $m = $balSt->fetch();
                $balance = ((int) $m['c'] > 0) ? (float) $m['net'] : (float) $item['quantity'];
                if ($qty > $balance) {
                    $this->pdo->rollBack();
                    return 'Not enough stock: ' . rtrim(rtrim(number_format($balance, 2), '0'), '.') . ' available.';
                }
            }

            $ins = $this->pdo->prepare(
                "INSERT INTO stock_movements (item_id, direction, quantity, reason, note, ref_module, ref_id, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $itemId, $direction, $qty,
                ($reason !== '' ? $reason : null),
                ($note !== '' ? $note : null),
                ($refModule !== '' ? $refModule : null),
                ($refId !== null && $refId !== '' ? (int) $refId : null),
                ($userId ? (int) $userId : null),
            ]);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log('Stock movement failed: ' . $e->getMessage());
            return 'Could not record the stock movement.';
        }
    }

    public function getMovements($itemId = null, $limit = 50) {
        if ($itemId) {
            $st = $this->pdo->prepare(
                "SELECT sm.*, i.name AS item_name, i.unit AS item_unit
                 FROM stock_movements sm JOIN inventory i ON i.id = sm.item_id
                 WHERE sm.item_id = ? ORDER BY sm.created_at DESC, sm.id DESC LIMIT ?"
            );
            $st->bindValue(1, (int) $itemId, PDO::PARAM_INT);
            $st->bindValue(2, (int) $limit, PDO::PARAM_INT);
            $st->execute();
        } else {
            $st = $this->pdo->prepare(
                "SELECT sm.*, i.name AS item_name, i.unit AS item_unit
                 FROM stock_movements sm JOIN inventory i ON i.id = sm.item_id
                 ORDER BY sm.created_at DESC, sm.id DESC LIMIT ?"
            );
            $st->bindValue(1, (int) $limit, PDO::PARAM_INT);
            $st->execute();
        }
        return $st->fetchAll();
    }

    /** Items whose balance is below their reorder threshold / min level. */
    public function lowStockItems() {
        $out = [];
        foreach ($this->getItemsWithBalance() as $r) {
            if (!empty($r['low'])) { $out[] = $r; }
        }
        return $out;
    }
}
?>
