<?php
require_once __DIR__ . '/../BaseModuleModel.php';

class HarvestModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'harvest_records'); }

    /** Storage facilities that can receive a harvest (B7). */
    public function storageRecords() {
        try {
            return $this->pdo->query(
                "SELECT id, name, unit, capacity, current_stock, facility_type FROM storage_records ORDER BY name"
            )->fetchAll();
        } catch (\Throwable $e) {
            error_log('Harvest storage list failed: ' . $e->getMessage());
            return [];
        }
    }

    /** kg per storage unit, or null when the unit can't be matched to kg. */
    private function unitFactorToKg($unit) {
        $u = strtolower(trim((string) $unit));
        if (in_array($u, ['kg', 'kgs', 'kilogram', 'kilograms'], true)) return 1.0;
        if (in_array($u, ['t', 'ton', 'tonne', 'tonnes', 'mt', 'metric ton', 'metric tonne'], true)) return 1000.0;
        return null;
    }

    /**
     * Create a harvest record and, optionally, add the harvested quantity to a
     * storage facility — all in one transaction so the yield and the stored
     * stock can never disagree.
     *
     * @return array ['ok'=>bool,'id'=>?int,'error'=>?string]
     */
    public function createHarvest($row, $storageId = null) {
        $storageId = ($storageId !== null && $storageId !== '') ? (int) $storageId : null;
        try {
            $this->pdo->beginTransaction();

            $st = $this->pdo->prepare(
                "INSERT INTO harvest_records (crop_id, harvest_date, quantity, quality, storage_record_id)
                 VALUES (?, ?, ?, ?, ?) RETURNING id"
            );
            $st->execute([
                (int) $row['crop_id'],
                $row['harvest_date'],
                $row['quantity'],
                $row['quality'] ?? null,
                $storageId,
            ]);
            $hid = (int) $st->fetchColumn();

            if ($storageId) {
                $ss = $this->pdo->prepare("SELECT * FROM storage_records WHERE id = ? FOR UPDATE");
                $ss->execute([$storageId]);
                $s = $ss->fetch();
                if (!$s) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'error' => 'Storage facility not found.'];
                }

                $factor = $this->unitFactorToKg($s['unit']);
                if ($factor === null) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'error' => 'Storage unit "' . ($s['unit'] ?: '—') . '" cannot be matched to kg. Choose a facility measured in kg or tonnes.'];
                }

                $addInStorageUnit = ((float) $row['quantity']) / $factor; // kg ÷ (kg per unit)
                if ($s['capacity'] !== null && $s['capacity'] !== '' && (float) $s['capacity'] > 0) {
                    $newStock = (float) $s['current_stock'] + $addInStorageUnit;
                    if ($newStock > (float) $s['capacity']) {
                        $this->pdo->rollBack();
                        return ['ok' => false, 'error' => 'That would exceed ' . $s['name'] . '\'s capacity (' . rtrim(rtrim(number_format((float) $s['capacity'], 2), '0'), '.') . ' ' . ($s['unit'] ?: '') . ').'];
                    }
                }

                $this->pdo->prepare("UPDATE storage_records SET current_stock = COALESCE(current_stock,0) + ? WHERE id = ?")
                    ->execute([$addInStorageUnit, $storageId]);
            }

            $this->pdo->commit();
            return ['ok' => true, 'id' => $hid, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Harvest create failed: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Could not save the harvest record.'];
        }
    }

    public function lastId() { return (int) $this->pdo->lastInsertId(); }
}
