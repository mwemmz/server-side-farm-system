<?php
require_once __DIR__ . '/../../Modules/Finance/FinanceService.php';

class EquipmentModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

    /**
     * All equipment with an effective service date. next_service_date is kept
     * in the column, but if it is null we still fall back to
     * last_service_date + service_interval_days so nothing is hidden.
     */
    public function getAllEquipment() {
        return $this->pdo->query(
            "SELECT e.*,
                    COALESCE(
                        e.next_service_date,
                        CASE WHEN e.last_service_date IS NOT NULL AND e.service_interval_days IS NOT NULL
                             THEN e.last_service_date + e.service_interval_days END
                    ) AS effective_service_date
             FROM equipment e
             ORDER BY (COALESCE(e.next_service_date,
                        CASE WHEN e.last_service_date IS NOT NULL AND e.service_interval_days IS NOT NULL
                             THEN e.last_service_date + e.service_interval_days END) IS NULL),
                      COALESCE(e.next_service_date,
                        CASE WHEN e.last_service_date IS NOT NULL AND e.service_interval_days IS NOT NULL
                             THEN e.last_service_date + e.service_interval_days END) ASC,
                      e.id DESC"
        )->fetchAll();
    }

    public function getById($id) {
        $st = $this->pdo->prepare("SELECT * FROM equipment WHERE id = ?");
        $st->execute([(int) $id]);
        return $st->fetch();
    }

    public function lastId() { return (int) $this->pdo->lastInsertId(); }

    /** Sensible service interval (days) for a piece of equipment, by type. */
    public static function intervalForType($type) {
        static $map = [
            'tractor'   => 90,
            'vehicle'   => 120,
            'truck'     => 120,
            'pump'      => 30,
            'generator' => 60,
            'sprayer'   => 45,
            'plough'    => 90,
            'harvester' => 90,
            'planter'   => 90,
        ];
        $k = strtolower(trim((string) $type));
        return $map[$k] ?? null;
    }

    public function addEquipment($name, $status, $type = null, $farm_id = null, $purchase_date = null, $service_interval_days = null) {
        $interval = ($service_interval_days !== null && $service_interval_days !== '') ? (int) $service_interval_days : self::intervalForType($type);
        $purchase = ($purchase_date !== null && $purchase_date !== '') ? $purchase_date : null;
        // Seed the first service date from the purchase date + interval when both exist.
        $next = ($purchase && $interval) ? date('Y-m-d', strtotime($purchase . ' +' . $interval . ' days')) : null;
        $stmt = $this->pdo->prepare(
            "INSERT INTO equipment (name, maintenance_status, equipment_type, farm_id, purchase_date, service_interval_days, next_service_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            $name, $status,
            ($type !== '' ? $type : null),
            ($farm_id ? (int) $farm_id : null),
            $purchase,
            $interval,
            $next,
        ]);
    }

    /** Service state for a row: OK | due | overdue | none. */
    public static function serviceState($effectiveDate) {
        if (empty($effectiveDate)) return 'none';
        $today = new DateTimeImmutable('today');
        try { $d = new DateTimeImmutable($effectiveDate); } catch (\Throwable $e) { return 'none'; }
        if ($d < $today) return 'overdue';
        if ($d <= $today->modify('+14 days')) return 'due';
        return 'ok';
    }

    /**
     * Record a service: reset the schedule, optionally log a finance expense
     * and optionally consume a spare part from inventory (stock-out reason
     * "Maintenance"). Everything happens in one transaction.
     *
     * @return array ['ok'=>bool,'error'=>?string]
     */
    public function recordService($id, $serviceDate, $cost = null, $inventoryItemId = null, $quantityUsed = null, $note = null, $userId = null) {
        $id = (int) $id;
        $eq = $this->getById($id);
        if (!$eq) return ['ok' => false, 'error' => 'Equipment not found.'];

        $interval = $eq['service_interval_days'] !== null ? (int) $eq['service_interval_days'] : self::intervalForType($eq['equipment_type'] ?? null);
        $next = ($interval && $serviceDate) ? date('Y-m-d', strtotime($serviceDate . ' +' . $interval . ' days')) : null;

        try {
            $this->pdo->beginTransaction();

            $set  = "last_service_date = ?, next_service_date = ?, maintenance_status = 'Operational'";
            $params = [$serviceDate, $next];
            if ($interval && empty($eq['service_interval_days'])) {
                // The row had no schedule: adopt the type default so the clock
                // keeps ticking after this service too.
                $set .= ", service_interval_days = ?";
                $params[] = $interval;
            }
            $params[] = $id;
            $this->pdo->prepare("UPDATE equipment SET $set WHERE id = ?")->execute($params);

            $inventoryModel = null;
            if ($inventoryItemId && $quantityUsed !== null && (float) $quantityUsed > 0) {
                require_once __DIR__ . '/../Inventory/InventoryModel.php';
                $inventoryModel = new InventoryModel($this->pdo);
                $ok = $inventoryModel->move(
                    (int) $inventoryItemId, 'out', (float) $quantityUsed,
                    'Maintenance', $note ?: ('Equipment service #' . $id),
                    'Equipment', $id, $userId
                );
                if ($ok !== true) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'error' => is_string($ok) ? $ok : 'Insufficient stock for the spare part.'];
                }
            }

            if ($cost !== null && (float) $cost > 0) {
                $finance = new FinanceService($this->pdo);
                $finance->postSafe(
                    'expense', 'Equipment maintenance', (float) $cost,
                    'Service: ' . ($eq['name'] ?? ('Equipment #' . $id)) . ($note ? ' — ' . $note : ''),
                    $eq['farm_id'] !== null ? (int) $eq['farm_id'] : null,
                    null, null, $serviceDate, 'paid'
                );
            }

            $this->pdo->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Equipment service failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not record the service.'];
        }
    }
}
