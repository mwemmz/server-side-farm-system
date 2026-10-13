<?php
class InventoryModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllItems() { return $this->pdo->query("SELECT * FROM inventory")->fetchAll(); }
    public function lastId() { return (int) $this->pdo->lastInsertId(); }
    public function addItem($name, $quantity, $unit, $category = null) {
        $stmt = $this->pdo->prepare("INSERT INTO inventory (name, quantity, opening_quantity, unit, category) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$name, $quantity, $quantity, $unit, ($category !== '' ? $category : null)]);
    }

    public function getItem($id) {
        $st = $this->pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $st->execute([(int) $id]);
        return $st->fetch();
    }

    /**
     * Resolve an existing item by name (trimmed, case-insensitive). Falls back to
     * the closest contained match (shortest name wins) so "urea" resolves to
     * "Urea Fertilizer" instead of creating a duplicate. Returns null when no
     * existing item matches at all.
     */
    public function findByNameOrClosest($name) {
        $name = trim((string) $name);
        if ($name === '') return null;
        $st = $this->pdo->prepare("SELECT id FROM inventory WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1");
        $st->execute([$name]);
        $id = $st->fetchColumn();
        if ($id !== false) return (int) $id;

        $needle = strtolower($name);
        foreach ($this->pdo->query("SELECT id, name FROM inventory ORDER BY LENGTH(TRIM(name)) ASC")->fetchAll() as $r) {
            $hay = strtolower(trim((string) $r['name']));
            if ($hay !== '' && (strpos($hay, $needle) !== false || strpos($needle, $hay) !== false)) {
                return (int) $r['id'];
            }
        }
        return null;
    }

    /** Current on-hand balance — the authoritative value held on the inventory row. */
    public function getBalance($itemId) {
        $item = $this->getItem($itemId);
        return $item ? (float) $item['quantity'] : 0.0;
    }

    /** All items with balance (= inventory.quantity), category/purpose filter and low-stock flag. */
    public function getItemsWithBalance($f = []) {
        $where = [];
        $args = [];
        if (!empty($f['category'])) { $where[] = "i.category = ?"; $args[] = $f['category']; }
        if (!empty($f['purpose']))  { $where[] = "i.used_for = ?"; $args[] = $f['purpose']; }
        $sql = "SELECT i.*,
                       COALESCE(rr.threshold_qty, i.min_level) AS threshold
                FROM inventory i
                LEFT JOIN reorder_rules rr ON rr.item_id = i.id";
        if ($where) { $sql .= " WHERE " . implode(' AND ', $where); }
        $sql .= " ORDER BY i.category NULLS LAST, i.name";
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['balance'] = (float) $r['quantity'];
            $thr = $r['threshold'];
            $r['low'] = ($thr !== null && (float) $thr > 0 && $r['balance'] < (float) $thr);
        }
        unset($r);
        return $rows;
    }

    /**
     * Record a stock movement. For 'out' the item row is locked and the on-hand
     * balance is re-checked inside the transaction; if it would go below zero the
     * movement is refused (returns a string error). When called inside an existing
     * transaction the work joins it and failures are thrown so the caller rolls back.
     *
     * @return true|string true on success, or an error message
     */
    public function move($itemId, $direction, $qty, $reason = null, $note = null, $refModule = null, $refId = null, $userId = null) {
        $itemId = (int) $itemId;
        $qty = (float) $qty;
        if (!in_array($direction, ['in', 'out'], true)) return 'Invalid direction.';
        if ($qty <= 0) return 'Quantity must be greater than zero.';

        $owns = !$this->pdo->inTransaction();
        try {
            if ($owns) {
                $this->pdo->beginTransaction();
            }

            // Lock the item row (serialises concurrent stock-outs).
            $lock = $this->pdo->prepare("SELECT id, name, quantity, unit FROM inventory WHERE id = ? FOR UPDATE");
            $lock->execute([$itemId]);
            $item = $lock->fetch();
            if (!$item) {
                if ($owns) { $this->pdo->rollBack(); return 'Inventory item not found.'; }
                throw new RuntimeException('Inventory item not found.');
            }

            $balance = (float) $item['quantity'];
            $unit = trim((string) ($item['unit'] ?? ''));
            if ($direction === 'out' && $qty > $balance) {
                $avail = rtrim(rtrim(number_format($balance, 2), '0'), '.') . ($unit !== '' ? ' ' . $unit : '');
                $msg = 'Not enough stock: ' . $avail . ' available.';
                if ($owns) { $this->pdo->rollBack(); return $msg; }
                throw new RuntimeException($msg);
            }

            $newBalance = $direction === 'in' ? $balance + $qty : $balance - $qty;
            $this->pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?")->execute([$newBalance, $itemId]);

            $ins = $this->pdo->prepare(
                "INSERT INTO stock_movements (item_id, direction, quantity, balance_after, reason, note, ref_module, ref_id, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $itemId, $direction, $qty, $newBalance,
                ($reason !== '' ? $reason : null),
                ($note !== '' ? $note : null),
                ($refModule !== '' ? $refModule : null),
                ($refId !== null && $refId !== '' ? (int) $refId : null),
                ($userId ? (int) $userId : null),
            ]);

            if ($owns) {
                $this->pdo->commit();
            }
            return true;
        } catch (PDOException $e) {
            if ($owns && $this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log('Stock movement failed: ' . $e->getMessage());
            if (!$owns) { throw $e; }
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
