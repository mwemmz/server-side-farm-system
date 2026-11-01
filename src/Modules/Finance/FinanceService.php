<?php
/**
 * Central finance posting service (Phase 3 / B2).
 *
 * Every cross-module money movement goes through post() so that:
 *   - the money always lands in finance_records with a category and a link back
 *     to the owning record (source_module + source_id);
 *   - the same source can never post twice (unique index on the pair);
 *   - a failed insert never takes the caller's page down (postSafe()).
 *
 * Expansion: ops records also carry costing dimensions (field/crop/livestock/
 * equipment/customer). syncSource() keeps an existing row in step when the
 * owning record is edited, and removeSource() deletes it when the record (or
 * its cost) is removed.
 */
class FinanceService {

    /** Columns syncSource() is allowed to write. Never built from request input. */
    public const DIMENSION_COLUMNS = ['field_id', 'crop_id', 'livestock_id', 'equipment_id', 'customer_id'];

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Insert a finance record. Idempotent when a source is supplied: a repeat
     * post for the same (source_module, source_id) returns the existing id.
     *
     * @param array $dimensions optional map of self::DIMENSION_COLUMNS => int|null
     * @return int|null new/existing finance record id, or null on no-op
     */
    public function post($type, $category, $amount, $description, $farmId = null, $sourceModule = null, $sourceId = null, $date = null, $paymentStatus = 'paid', $dueDate = null, $paymentMethod = null, $dimensions = []) {
        $type = (strtolower((string) $type) === 'income') ? 'income' : 'expense';
        $date = $date ?: date('Y-m-d');
        $amount = (float) $amount;
        $category = ($category !== null && trim((string) $category) !== '') ? trim((string) $category) : null;
        $farmId = ($farmId !== null && $farmId !== '') ? (int) $farmId : null;
        $sourceId = ($sourceId !== null && $sourceId !== '') ? (int) $sourceId : null;

        // Return the existing record for this source if one already exists.
        if ($sourceModule !== null && $sourceId !== null) {
            $existing = $this->findSource($sourceModule, $sourceId);
            if ($existing !== null) {
                return $existing;
            }
        }

        $cols = ['type', 'category', 'amount', 'description', 'date', 'farm_id',
                 'source_module', 'source_id', 'payment_status', 'due_date', 'payment_method',
                 'related_module', 'related_id'];
        $vals = [
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
        ];

        foreach ($this->cleanDimensions($dimensions) as $col => $val) {
            $cols[] = $col;
            $vals[] = $val;
        }

        $sql = 'INSERT INTO finance_records (' . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ') RETURNING id';
        $st = $this->pdo->prepare($sql);
        $st->execute($vals);
        return (int) $st->fetchColumn();
    }

    /**
     * Update the finance row for a source, or create it when it does not exist.
     * Used when an ops record is edited (the amount/date/dimensions may change).
     */
    public function syncSource($sourceModule, $sourceId, $type, $category, $amount, $description, $farmId = null, $date = null, $paymentStatus = 'paid', $dueDate = null, $dimensions = []) {
        if ($sourceModule === null || $sourceId === null) {
            return null;
        }
        $existing = $this->findSource($sourceModule, $sourceId);
        if ($existing === null) {
            return $this->post($type, $category, $amount, $description, $farmId, $sourceModule, $sourceId, $date, $paymentStatus, $dueDate, null, $dimensions);
        }

        $type = (strtolower((string) $type) === 'income') ? 'income' : 'expense';
        $date = $date ?: date('Y-m-d');
        $category = ($category !== null && trim((string) $category) !== '') ? trim((string) $category) : null;
        $farmId = ($farmId !== null && $farmId !== '') ? (int) $farmId : null;

        $sets = ['type = ?', 'category = ?', 'amount = ?', 'description = ?', 'date = ?', 'farm_id = ?',
                 'payment_status = ?', 'due_date = ?'];
        $vals = [$type, $category, (float) $amount, $description, $date, $farmId,
                 $paymentStatus ?: 'paid', $dueDate ?: null];
        foreach ($this->cleanDimensions($dimensions) as $col => $val) {
            $sets[] = "{$col} = ?";
            $vals[] = $val;
        }
        $vals[] = $existing;
        $st = $this->pdo->prepare('UPDATE finance_records SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $st->execute($vals);
        return $existing;
    }

    /** Delete the finance row owned by a source (used when the record is deleted). */
    public function removeSource($sourceModule, $sourceId) {
        if ($sourceModule === null || $sourceId === null) {
            return;
        }
        try {
            $st = $this->pdo->prepare('DELETE FROM finance_records WHERE source_module = ? AND source_id = ?');
            $st->execute([$sourceModule, (int) $sourceId]);
        } catch (PDOException $e) {
            error_log('FinanceService::removeSource failed: ' . $e->getMessage());
        }
    }

    /** Existing finance id for a source, or null. */
    public function findSource($sourceModule, $sourceId) {
        $st = $this->pdo->prepare(
            "SELECT id FROM finance_records WHERE source_module = ? AND source_id = ? LIMIT 1"
        );
        $st->execute([$sourceModule, (int) $sourceId]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /**
     * post() that never throws. Logs and returns null on any database error so a
     * failure to record money cannot 500 the calling module.
     */
    public function postSafe($type, $category, $amount, $description, $farmId = null, $sourceModule = null, $sourceId = null, $date = null, $paymentStatus = 'paid', $dueDate = null, $paymentMethod = null, $dimensions = []) {
        try {
            return $this->post($type, $category, $amount, $description, $farmId, $sourceModule, $sourceId, $date, $paymentStatus, $dueDate, $paymentMethod, $dimensions);
        } catch (PDOException $e) {
            error_log('FinanceService::post failed: ' . $e->getMessage());
            return null;
        }
    }

    /** syncSource() that never throws. */
    public function syncSourceSafe($sourceModule, $sourceId, $type, $category, $amount, $description, $farmId = null, $date = null, $paymentStatus = 'paid', $dueDate = null, $dimensions = []) {
        try {
            return $this->syncSource($sourceModule, $sourceId, $type, $category, $amount, $description, $farmId, $date, $paymentStatus, $dueDate, $dimensions);
        } catch (PDOException $e) {
            error_log('FinanceService::syncSource failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Keep only known dimension columns with a positive integer value. */
    private function cleanDimensions($dimensions) {
        $out = [];
        if (!is_array($dimensions)) return $out;
        foreach (self::DIMENSION_COLUMNS as $col) {
            if (!array_key_exists($col, $dimensions)) continue;
            $v = $dimensions[$col];
            if ($v === null || $v === '') { $out[$col] = null; continue; }
            if (is_scalar($v) && ctype_digit(trim((string) $v))) {
                $n = (int) $v;
                if ($n >= 1) { $out[$col] = $n; }
            }
        }
        return $out;
    }
}
