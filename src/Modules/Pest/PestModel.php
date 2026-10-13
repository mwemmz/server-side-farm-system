<?php
require_once __DIR__ . '/../BaseModuleModel.php';

/**
 * Health incidents (Phase 3 / B4).
 *
 * Historically this was the standalone "Pest" module over pest_records. Pests
 * and diseases are now unified in health_incidents for both crops and animals.
 * The class/method names are kept so existing callers (Crop/Livestock) work
 * unchanged, but every query reads and writes health_incidents only.
 */
class PestModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'health_incidents'); }

    /** Health incidents linked to a crop (shown inside the Crop module). */
    public function getCropPests() {
        return $this->pdo->query(
            "SELECT h.*, h.issue_name AS pest_name, h.treatment_notes AS action_taken, c.name AS crop_name
             FROM health_incidents h
             JOIN crops c ON c.id = h.crop_id
             WHERE h.subject_type = 'crop'
             ORDER BY h.detected_date DESC NULLS LAST, h.id DESC"
        )->fetchAll();
    }

    /** Health incidents linked to an animal (shown inside the Livestock module). */
    public function getLivestockPests() {
        return $this->pdo->query(
            "SELECT h.*, h.issue_name AS pest_name, h.treatment_notes AS action_taken,
                    l.type AS animal_type, l.breed AS animal_breed, l.tag_no
             FROM health_incidents h
             JOIN livestock l ON l.id = h.livestock_id
             WHERE h.subject_type = 'livestock'
             ORDER BY h.detected_date DESC NULLS LAST, h.id DESC"
        )->fetchAll();
    }

    /**
     * Insert a health incident. Accepts the legacy row shape used by the Crop
     * and Livestock controllers (pest_name, severity, detected_date,
     * action_taken, crop_id/livestock_id, farm_id) and maps it to the
     * health_incidents columns.
     */
    public function create($row) {
        $livestockId = !empty($row['livestock_id']) ? (int) $row['livestock_id'] : null;
        $cropId      = !empty($row['crop_id']) ? (int) $row['crop_id'] : null;
        $subjectType = $livestockId ? 'livestock' : 'crop';
        $subjectId   = $livestockId ?: $cropId;

        $stmt = $this->pdo->prepare(
            "INSERT INTO health_incidents
                (farm_id, subject_type, subject_id, issue_name, issue_kind, severity, detected_date,
                 status, treatment_notes, crop_id, livestock_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, ?, ?)"
        );
        return $stmt->execute([
            !empty($row['farm_id']) ? (int) $row['farm_id'] : null,
            $subjectType,
            $subjectId,
            trim((string) ($row['pest_name'] ?? $row['issue_name'] ?? 'Unknown')),
            $row['issue_kind'] ?? 'pest',
            (isset($row['severity']) && trim((string) $row['severity']) !== '') ? trim((string) $row['severity']) : null,
            (isset($row['detected_date']) && $row['detected_date'] !== '') ? $row['detected_date'] : date('Y-m-d'),
            (isset($row['action_taken']) && trim((string) $row['action_taken']) !== '') ? trim((string) $row['action_taken']) : null,
            $cropId,
            $livestockId,
        ]);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM health_incidents WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }

    /** Issue-catalogue suggestions for a subject type. */
    public function catalogFor($appliesTo = null) {
        if ($appliesTo) {
            $st = $this->pdo->prepare("SELECT * FROM health_issue_catalog WHERE applies_to = ? OR applies_to = 'both' ORDER BY name");
            $st->execute([$appliesTo]);
            return $st->fetchAll();
        }
        return $this->pdo->query("SELECT * FROM health_issue_catalog ORDER BY applies_to, name")->fetchAll();
    }

    /**
     * Mark an incident treated or resolved. Optional $stockItemId + $stockQty
     * deduct the treatment chemical from inventory (via InventoryModel::move) in
     * the same transaction; a shortage refuses the whole update atomically.
     */
    public function setStatus($id, $status, $notes = null, $stockItemId = null, $stockQty = null) {
        $this->pdo->beginTransaction();
        try {
            $status = in_array($status, ['open', 'treated', 'resolved'], true) ? $status : 'open';
            $resolvedDate = ($status === 'resolved') ? date('Y-m-d') : null;
            $st = $this->pdo->prepare(
                "UPDATE health_incidents
                 SET status = ?, treatment_notes = COALESCE(?, treatment_notes), resolved_date = ?
                 WHERE id = ?"
            );
            $st->execute([$status, ($notes !== null && trim($notes) !== '' ? trim($notes) : null), $resolvedDate, (int) $id]);
            if ((int) $st->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }
            if ($stockItemId && $stockQty !== null && (float) $stockQty > 0) {
                require_once __DIR__ . '/../Inventory/InventoryModel.php';
                $mv = (new InventoryModel($this->pdo))->move(
                    (int) $stockItemId, 'out', (float) $stockQty,
                    'Treatment', 'Health incident #' . (int) $id, 'HealthIncident', (int) $id
                );
                if ($mv !== true) {
                    throw new RuntimeException((string) $mv);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log('Health incident status update failed: ' . $e->getMessage());
            return false;
        }
    }
}
