<?php
require_once __DIR__ . '/../Helpers/JwtHelper.php';
require_once __DIR__ . '/../Helpers/AuditHelper.php';
require_once __DIR__ . '/IntelligenceUtils.php';

/**
 * Feature 1 — Automated Inventory Reorder Engine.
 *
 * Computes live balances from stock_movements and, when an item falls below its
 * reorder threshold, creates a draft purchase order (if auto_create_po) and
 * always emits a notification. Draft POs are never auto-submitted — they wait
 * for human approval (see the purchase-order approval flow).
 */
class ReorderEngine {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * GET/POST/PUT /api/v1/reorder-rules
     * GET  -> list rules
     * POST -> create rule
     * PUT  -> update rule  (body must include a rule id or the query id)
     */
    public function handleRules($method, $id = null) {
        if ($method === 'get') {
            return $this->listRules();
        }
        if ($method === 'post') {
            return $this->createRule($_POST ?: $this->body());
        }
        if ($method === 'put') {
            return $this->updateRule($id ?: ($_POST['id'] ?? null), $_POST ?: $this->body());
        }
        api_err('Method not allowed', 405);
    }

    private function body() {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [$raw];
    }

    private function listRules() {
        $rows = $this->pdo->query("
            SELECT rr.id, rr.item_id, i.name AS item_name, rr.threshold_qty, rr.reorder_qty,
                   rr.preferred_supplier_id, s.name AS supplier_name, rr.auto_create_po,
                   COALESCE( (SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity ELSE 0 END),0)
                                - COALESCE(SUM(CASE WHEN direction='out' THEN quantity ELSE 0 END),0)
                              FROM stock_movements sm WHERE sm.item_id = rr.item_id), i.quantity) AS current_balance
            FROM reorder_rules rr
            JOIN inventory i ON i.id = rr.item_id
            LEFT JOIN suppliers s ON s.id = rr.preferred_supplier_id
            ORDER BY rr.id
        ")->fetchAll();
        api_ok($rows);
    }

    private function createRule($data) {
        $item = $data['item_id'] ?? null;
        $threshold = (int) ($data['threshold_qty'] ?? 10);
        $reorder = (int) ($data['reorder_qty'] ?? 20);
        $supplier = $data['preferred_supplier_id'] ?? null;
        $auto = !empty($data['auto_create_po']);

        if (!$item) {
            api_err('item_id is required');
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO reorder_rules (item_id, threshold_qty, reorder_qty, preferred_supplier_id, auto_create_po)
            VALUES (?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([(int) $item, $threshold, $reorder, $supplier ? (int) $supplier : null, $auto]);
        $id = (int) $stmt->fetchColumn();
        IntelligenceUtils::auditSystem($this->pdo, 'create_reorder_rule', 'reorder_rules', $id, $data);
        api_ok(['id' => $id], 201);
    }

    private function updateRule($id, $data) {
        if (!$id) {
            api_err('Missing rule id');
        }
        $fields = [];
        $params = [];
        foreach (['threshold_qty','reorder_qty','preferred_supplier_id'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = ?";
                $params[] = $data[$f] === '' ? null : $data[$f];
            }
        }
        if (array_key_exists('auto_create_po', $data)) {
            $fields[] = "auto_create_po = ?";
            $params[] = !empty($data['auto_create_po']);
        }
        if ($fields) {
            $params[] = (int) $id;
            $this->pdo->prepare("UPDATE reorder_rules SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        }
        IntelligenceUtils::auditSystem($this->pdo, 'update_reorder_rule', 'reorder_rules', (int) $id, $data);
        api_ok(['id' => (int) $id]);
    }

    /**
     * GET /api/v1/inventory-items/{id}/balance
     * Real-time balance = (in - out) from stock_movements, falling back to the
     * inventory opening quantity when no movements exist.
     */
    public function balance($itemId) {
        $item = $this->fetchItem($itemId);
        if (!$item) {
            api_err('Inventory item not found', 404);
        }
        $movements = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity ELSE 0 END),0) AS total_in,
                    COALESCE(SUM(CASE WHEN direction='out' THEN quantity ELSE 0 END),0) AS total_out,
                    COUNT(*) AS movement_count
             FROM stock_movements WHERE item_id = ?"
        );
        $movements->execute([(int) $itemId]);
        $m = $movements->fetch();

        $opening = (float) $item['quantity'];
        $balance = (int) $m['movement_count'] > 0
            ? (int) $m['total_in'] - (int) $m['total_out']
            : $opening;

        api_ok([
            'item_id'    => (int) $itemId,
            'item_name'  => $item['name'],
            'opening_qty'=> $opening,
            'total_in'   => (int) $m['total_in'],
            'total_out'  => (int) $m['total_out'],
            'balance'    => $balance,
            'inputs'     => ['method' => (int) $m['movement_count'] > 0 ? 'stock_movements' : 'opening_fallback'],
        ]);
    }

    /**
     * POST /api/v1/reorder-check/run
     * Manual trigger of the hourly cron logic.
     */
    public function runCronLogic() {
        $summary = ['rules_evaluated' => 0, 'below_threshold' => 0, 'pos_created' => 0, 'notifications_sent' => 0, 'checked' => []];

        $rules = $this->pdo->query("SELECT * FROM reorder_rules")->fetchAll();
        foreach ($rules as $rule) {
            $summary['rules_evaluated']++;
            $item = $this->fetchItem($rule['item_id']);
            if (!$item) continue;

            $balance = $this->itemBalance($rule['item_id'], (float) $item['quantity']);
            $threshold = (int) $rule['threshold_qty'];

            $checked = [
                'item_id'    => (int) $rule['item_id'],
                'item_name'  => $item['name'],
                'balance'    => $balance,
                'threshold'  => $threshold,
                'below'      => $balance < $threshold,
            ];

            if ($balance < $threshold) {
                $summary['below_threshold']++;

                // Always notify
                IntelligenceUtils::notify(
                    $this->pdo,
                    "Low stock alert: '{$item['name']}' balance {$balance} is below threshold {$threshold}."
                );
                $summary['notifications_sent']++;

                // Optionally create a draft purchase order (never auto-submitted)
                if (!empty($rule['auto_create_po']) && $rule['preferred_supplier_id']) {
                    $poId = $this->createDraftPo($rule);
                    $checked['po_created'] = $poId;
                    if ($poId) {
                        $summary['pos_created']++;
                    }
                }
            }
            $summary['checked'][] = $checked;
        }

        IntelligenceUtils::auditSystem($this->pdo, 'reorder_check_run', 'reorder_rules', null, $summary);
        api_ok($summary);
    }

    /** Compute an item's balance from movements (fallback to opening qty). */
    private function itemBalance($itemId, $openingQty) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity ELSE 0 END),0) AS ti,
                    COALESCE(SUM(CASE WHEN direction='out' THEN quantity ELSE 0 END),0) AS to,
                    COUNT(*) AS c FROM stock_movements WHERE item_id = ?"
        );
        $stmt->execute([(int) $itemId]);
        $m = $stmt->fetch();
        return (int) $m['c'] > 0 ? (int) $m['ti'] - (int) $m['to'] : (int) $openingQty;
    }

    private function createDraftPo($rule) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO purchase_orders (item_id, supplier_id, quantity, status)
             VALUES (?, ?, ?, 'draft') RETURNING id"
        );
        $stmt->execute([(int) $rule['item_id'], (int) $rule['preferred_supplier_id'], (int) $rule['reorder_qty']]);
        $id = (int) $stmt->fetchColumn();
        IntelligenceUtils::auditSystem(
            $this->pdo,
            'auto_create_po',
            'purchase_orders',
            $id,
            ['item_id' => (int) $rule['item_id'], 'quantity' => (int) $rule['reorder_qty']]
        );
        return $id;
    }

    private function fetchItem($itemId) {
        $stmt = $this->pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([(int) $itemId]);
        return $stmt->fetch();
    }

    /** Convenience: record a stock movement (used by callers/tests). */
    public function recordMovement($itemId, $direction, $quantity) {
        if (!in_array($direction, ['in', 'out'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO stock_movements (item_id, direction, quantity) VALUES (?, ?, ?)"
        );
        return $stmt->execute([(int) $itemId, $direction, $quantity]);
    }
}
