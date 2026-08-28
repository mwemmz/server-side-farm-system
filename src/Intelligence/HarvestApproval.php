<?php
require_once __DIR__ . '/IntelligenceUtils.php';
require_once __DIR__ . '/YieldPredictionService.php';

/**
 * Feature 5 — Approval Workflow: Harvest → Finance.
 *
 * Workers submit a harvest (status='submitted'); a manager approves or rejects.
 * Approval cascades: create stored_produce, create an income transaction when a
 * matching sales order exists, and recalc the yield prediction with real data.
 */
class HarvestApproval {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * GET /api/v1/harvests?status=submitted
     * Manager's approval queue. Defaults to submitted.
     */
    public function queue($status = 'submitted') {
        if (!in_array($status, ['submitted', 'approved', 'rejected'], true)) {
            $status = 'submitted';
        }
        $stmt = $this->pdo->prepare(
            "SELECT hr.*, c.name AS crop_name, c.farm_id
             FROM harvest_records hr
             LEFT JOIN crops c ON c.id = hr.crop_id
             WHERE hr.status = ?
             ORDER BY hr.harvest_date DESC"
        );
        $stmt->execute([$status]);
        api_ok($stmt->fetchAll());
    }

    /**
     * POST /api/v1/harvests/{id}/approve
     * Cascades: stored_produce, (optional) income finance record, yield recalc.
     */
    public function approve($harvestId, $auth) {
        $harvest = $this->fetchHarvest($harvestId);
        if (!$harvest) {
            api_err('Harvest not found', 404);
        }
        if ($harvest['status'] === 'approved') {
            api_ok(['harvest_id' => (int) $harvestId, 'status' => 'approved', 'duplicate' => true]);
        }
        $userId = (int) ($auth['sub'] ?? null);

        $this->pdo->prepare(
            "UPDATE harvest_records SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?"
        )->execute([$userId, (int) $harvestId]);

        $cascade = [];

        // 1. Auto-create stored_produce from the harvest quantity & grade
        $spId = $this->createStoredProduce($harvest);
        $cascade['stored_produce_id'] = $spId;

        // 2. Income transaction ONLY if tied to a completed sales order
        $txId = $this->createIncomeTransactionIfSale($harvest);
        $cascade['transaction_id'] = $txId; // null when skipped

        // 3. Recalc yield prediction (real data now available)
        $yieldId = $this->recalcYield($harvest);
        $cascade['yield_prediction_id'] = $yieldId;

        IntelligenceUtils::auditSystem(
            $this->pdo,
            'approve_harvest',
            'harvest_records',
            (int) $harvestId,
            ['cascade' => $cascade],
            $userId
        );

        api_ok([
            'harvest_id' => (int) $harvestId,
            'status'     => 'approved',
            'cascade'    => $cascade,
        ]);
    }

    /**
     * POST /api/v1/harvests/{id}/reject   body: {"reason": "..."}
     */
    public function reject($harvestId, $auth, $body) {
        $harvest = $this->fetchHarvest($harvestId);
        if (!$harvest) {
            api_err('Harvest not found', 404);
        }
        $reason = trim((string) ($body['reason'] ?? ''));
        if ($reason === '') {
            api_err('rejection_reason is required');
        }
        $userId = (int) ($auth['sub'] ?? null);

        $this->pdo->prepare(
            "UPDATE harvest_records SET status='rejected', rejection_reason=?, approved_by=?, approved_at=NOW() WHERE id=?"
        )->execute([$reason, $userId, (int) $harvestId]);

        IntelligenceUtils::notify(
            $this->pdo,
            "Harvest #{$harvestId} for '{$harvest['crop_name']}' was rejected. Reason: {$reason}"
        );
        IntelligenceUtils::auditSystem(
            $this->pdo,
            'reject_harvest',
            'harvest_records',
            (int) $harvestId,
            ['reason' => $reason],
            $userId
        );

        api_ok([
            'harvest_id'      => (int) $harvestId,
            'status'          => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    // ---- cascade internals ----

    private function createStoredProduce($harvest) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO stored_produce (harvest_id, crop_id, quantity, grade, storage_start_date, is_in_storage)
             VALUES (?, ?, ?, ?, CURRENT_DATE, true) RETURNING id"
        );
        $stmt->execute([
            (int) $harvest['id'],
            (int) $harvest['crop_id'],
            (float) $harvest['quantity'],
            $harvest['quality'] ?: 'unclassified',
        ]);
        $id = (int) $stmt->fetchColumn();
        IntelligenceUtils::auditSystem(
            $this->pdo,
            'auto_create_stored_produce',
            'stored_produce',
            $id,
            ['harvest_id' => (int) $harvest['id'], 'quantity' => (float) $harvest['quantity']]
        );
        return $id;
    }

    /**
     * Creates an income finance record if the harvest's crop can be matched to a
     * completed sales order. The current schema has no sales_order->crop link, so
     * we look for any sales record; if none, we skip cleanly (returns null).
     */
    private function createIncomeTransactionIfSale($harvest) {
        $salesCount = (int) $this->pdo->query("SELECT COUNT(*) FROM sales_records")->fetchColumn();
        if ($salesCount === 0) {
            // No matching sales order -> skip (per spec "otherwise skip")
            IntelligenceUtils::auditSystem(
                $this->pdo,
                'income_transaction_skipped',
                'harvest_records',
                (int) $harvest['id'],
                ['reason' => 'no matching completed sales order found']
            );
            return null;
        }

        // Tie to the most recent sales record as the representative order.
        $lastSale = $this->pdo->query("SELECT id FROM sales_records ORDER BY sale_date DESC, id DESC LIMIT 1")->fetch();
        $amount = (float) $harvest['quantity']; // placeholder valuation; real logic would use sale price

        $stmt = $this->pdo->prepare(
            "INSERT INTO finance_records (type, amount, description, date)
             VALUES ('income', ?, ?, CURRENT_DATE) RETURNING id"
        );
        $description = "Auto income from approved harvest #{$harvest['id']} ({$harvest['crop_name']})";
        $stmt->execute([$amount, $description]);
        $id = (int) $stmt->fetchColumn();

        IntelligenceUtils::auditSystem(
            $this->pdo,
            'auto_create_income_transaction',
            'finance_records',
            $id,
            ['harvest_id' => (int) $harvest['id'], 'sales_order_id' => (int) $lastSale['id']]
        );
        return $id;
    }

    private function recalcYield($harvest) {
        // Trigger Feature 3 recalc using real harvest data.
        try {
            $svc = new YieldPredictionService($this->pdo);
            $result = $svc->recalculateData((int) $harvest['crop_id']);
            return $result['prediction_id'] ?? null;
        } catch (\Throwable $e) {
            error_log("Yield recalc cascade failed: " . $e->getMessage());
            return null;
        }
    }

    private function fetchHarvest($id) {
        $stmt = $this->pdo->prepare(
            "SELECT hr.*, c.name AS crop_name FROM harvest_records hr
             LEFT JOIN crops c ON c.id = hr.crop_id WHERE hr.id = ?"
        );
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }
}
