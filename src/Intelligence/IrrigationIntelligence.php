<?php
require_once __DIR__ . '/IntelligenceUtils.php';

/**
 * Feature 2 — Weather-Driven Irrigation Recommendation.
 *
 * Turns irrigation from "log what you did" into "tell me what to do": for each
 * field that has an irrigation system, compare rainfall over the last 7 days
 * against the crop's weekly water need and recommend a top-up in liters.
 */
class IrrigationIntelligence {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Daily job (runnable manually via POST /api/v1/irrigation-recommendations/run).
     * Returns inputs + results for auditability.
     */
    public function runDailyJob() {
        $summary = ['fields_evaluated' => 0, 'recommendations_created' => 0, 'checked' => []];

        $fields = $this->pdo->query("
            SELECT DISTINCT f.*
            FROM fields f
            JOIN irrigation_systems irs ON irs.farm_id = f.farm_id
            WHERE irs.status IS NOT NULL
            ORDER BY f.id
        ")->fetchAll();

        foreach ($fields as $field) {
            $summary['fields_evaluated']++;
            $crop = $this->activeCropForFarm($field['farm_id']);
            // The field's irrigation is served by the farm's system; use farm rainfall
            $waterNeed = $crop ? (float) $crop['water_need_mm_per_week'] : 35.0;
            $cropName  = $crop ? $crop['name'] : 'unknown crop';

            $rain = $this->rainfallLast7Days($field['farm_id']);
            $rainfallMm = $rain['total_mm'];

            $deficitMm = max(0.0, $waterNeed - $rainfallMm);

            $checked = [
                'field_id'     => (int) $field['id'],
                'field_name'   => $field['name'],
                'crop'         => $cropName,
                'water_need_mm'=> round($waterNeed, 2),
                'rainfall_mm'  => round($rainfallMm, 2),
                'deficit_mm'   => round($deficitMm, 2),
            ];

            if ($deficitMm > 0) {
                $liters = $this->mmToLiters($field['size'], $deficitMm);
                $reason = sprintf(
                    'Rainfall %.1fmm is %.1fmm below the %.1fmm weekly water need for %s. Apply %.0f L.',
                    $rainfallMm, $deficitMm, $waterNeed, $cropName, $liters
                );
                $recId = $this->insertRecommendation($field['id'], $waterNeed, $deficitMm, $field['size'], $reason);
                $checked['recommendation_id'] = $recId;
                $checked['recommended_liters'] = $liters;
                $checked['reason'] = $reason;
                $summary['recommendations_created']++;
            }
            $summary['checked'][] = $checked;
        }

        IntelligenceUtils::auditSystem($this->pdo, 'irrigation_recommendation_run', 'irrigation_recommendations', null, $summary);
        api_ok($summary);
    }

    /**
     * GET /api/v1/fields/{id}/irrigation-recommendations
     */
    public function listForField($fieldId) {
        $field = $this->fetchField($fieldId);
        if (!$field) {
            api_err('Field not found', 404);
        }
        $rows = $this->pdo->prepare("
            SELECT * FROM irrigation_recommendations WHERE field_id = ? ORDER BY created_at DESC
        ");
        $rows->execute([(int) $fieldId]);
        api_ok($rows->fetchAll());
    }

    /**
     * POST /api/v1/irrigation-recommendations/{id}/accept
     * Creates the corresponding irrigation_schedules row.
     */
    public function accept($recId, $userId = null) {
        $rec = $this->fetchRec($recId);
        if (!$rec) {
            api_err('Recommendation not found', 404);
        }
        // idempotent: if already accepted, return it
        if ($rec['status'] === 'accepted') {
            api_ok(['irrigation_recommendation' => $rec, 'duplicate' => true]);
        }
        $this->pdo->prepare("UPDATE irrigation_recommendations SET status='accepted' WHERE id=?")->execute([(int) $recId]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO irrigation_schedules (field_id, schedule_date, liters, status)
             VALUES (?, ?, ?, 'scheduled') RETURNING id"
        );
        $stmt->execute([(int) $rec['field_id'], $rec['recommended_date'], (float) $rec['recommended_liters']]);
        $scheduleId = (int) $stmt->fetchColumn();

        IntelligenceUtils::auditSystem(
            $this->pdo,
            'accept_irrigation_recommendation',
            'irrigation_recommendations',
            (int) $recId,
            ['schedule_id' => $scheduleId, 'field_id' => (int) $rec['field_id']],
            $userId
        );
        api_ok(['irrigation_schedule_id' => $scheduleId, 'irrigation_recommendation_id' => (int) $recId]);
    }

    /**
     * POST /api/v1/irrigation-recommendations/{id}/dismiss
     */
    public function dismiss($recId, $userId = null) {
        $rec = $this->fetchRec($recId);
        if (!$rec) {
            api_err('Recommendation not found', 404);
        }
        $this->pdo->prepare("UPDATE irrigation_recommendations SET status='dismissed' WHERE id=?")->execute([(int) $recId]);
        IntelligenceUtils::auditSystem($this->pdo, 'dismiss_irrigation_recommendation', 'irrigation_recommendations', (int) $recId, ['field_id' => (int) $rec['field_id']], $userId);
        api_ok(['irrigation_recommendation_id' => (int) $recId, 'status' => 'dismissed']);
    }

    // --- internals ---

    private function insertRecommendation($fieldId, $waterNeed, $deficitMm, $fieldSize, $reason) {
        $liters = $this->mmToLiters($fieldSize, $deficitMm);
        $stmt = $this->pdo->prepare(
            "INSERT INTO irrigation_recommendations (field_id, recommended_date, recommended_liters, reason)
             VALUES (?, CURRENT_DATE, ?, ?) RETURNING id"
        );
        $stmt->execute([(int) $fieldId, $liters, $reason]);
        return (int) $stmt->fetchColumn();
    }

    /** Convert rainfall deficit (mm) to liters for a field of given size (hectares assumed). */
    private function mmToLiters($fieldSizeHa, $deficitMm) {
        $ha = (float) $fieldSizeHa > 0 ? (float) $fieldSizeHa : 1.0;
        return round($deficitMm * $ha * 10000, 2); // 1mm over 1ha = 10,000 L
    }

    private function rainfallLast7Days($farmId) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(rainfall_mm), 0) AS total_mm, COUNT(*) AS days_count
             FROM weather_records
             WHERE farm_id = ? AND weather_date >= CURRENT_DATE - INTERVAL '7 days'"
        );
        $stmt->execute([(int) $farmId]);
        return $stmt->fetch();
    }

    private function activeCropForFarm($farmId) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM crops WHERE farm_id = ? ORDER BY planting_date DESC NULLS LAST LIMIT 1"
        );
        $stmt->execute([(int) $farmId]);
        return $stmt->fetch();
    }

    private function fetchField($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM fields WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }

    private function fetchRec($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM irrigation_recommendations WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }
}
