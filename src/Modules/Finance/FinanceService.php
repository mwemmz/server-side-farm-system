<?php
/**
 * Central finance posting service (Phase 3 / B2).
 *
 * Every cross-module money movement goes through post() so that:
 *   - the money always lands in finance_records with a category and a link back
 *     to the owning record (source_module + source_id);
 *   - the same source can never post twice (unique index on the pair);
 *   - a failed insert never takes the caller's page down (postSafe()).
 */
class FinanceService {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Insert a finance record. Idempotent when a source is supplied: a repeat
     * post for the same (source_module, source_id) returns the existing id.
     *
     * @return int|null new/existing finance record id, or null on no-op
     */
    public function post($type, $category, $amount, $description, $farmId = null, $sourceModule = null, $sourceId = null, $date = null, $paymentStatus = 'paid', $dueDate = null, $paymentMethod = null) {
        $type = (strtolower((string) $type) === 'income') ? 'income' : 'expense';
        $date = $date ?: date('Y-m-d');
        $amount = (float) $amount;
        $category = ($category !== null && trim((string) $category) !== '') ? trim((string) $category) : null;
        $farmId = ($farmId !== null && $farmId !== '') ? (int) $farmId : null;
        $sourceId = ($sourceId !== null && $sourceId !== '') ? (int) $sourceId : null;

        // Return the existing record for this source if one already exists.
        if ($sourceModule !== null && $sourceId !== null) {
            $ex = $this->pdo->prepare(
                "SELECT id FROM finance_records WHERE source_module = ? AND source_id = ? LIMIT 1"
            );
            $ex->execute([$sourceModule, $sourceId]);
            $existing = $ex->fetchColumn();
            if ($existing !== false) {
                return (int) $existing;
            }
        }

        $sql = "INSERT INTO finance_records
                    (type, category, amount, description, date, farm_id,
                     source_module, source_id, payment_status, due_date, payment_method,
                     related_module, related_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) RETURNING id";
        $st = $this->pdo->prepare($sql);
        $st->execute([
            $type,
            $category,
            $amount,
            $description,
            $date,
            $farmId,
            $sourceModule,
            $sourceId,
            $paymentStatus ?: 'paid',
            $dueDate ?: null,
            $paymentMethod ?: null,
            $sourceModule,
            $sourceId,
        ]);
        return (int) $st->fetchColumn();
    }

    /**
     * post() that never throws. Logs and returns null on any database error so a
     * failure to record money cannot 500 the calling module.
     */
    public function postSafe($type, $category, $amount, $description, $farmId = null, $sourceModule = null, $sourceId = null, $date = null, $paymentStatus = 'paid', $dueDate = null, $paymentMethod = null) {
        try {
            return $this->post($type, $category, $amount, $description, $farmId, $sourceModule, $sourceId, $date, $paymentStatus, $dueDate, $paymentMethod);
        } catch (PDOException $e) {
            error_log('FinanceService::post failed: ' . $e->getMessage());
            return null;
        }
    }
}
