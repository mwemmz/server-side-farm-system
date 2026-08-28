<?php
require_once __DIR__ . '/IntelligenceUtils.php';

/**
 * Feature 3 — Real Yield Prediction (calculated, not typed).
 *
 * predicted_yield = avg_historical_yield_for_crop * weather_factor * (1 - pest_penalty)
 *   weather_factor = this season's rainfall / historical avg rainfall for the crop,
 *                    clamped to [0.5, 1.2]
 *   pest_penalty   scales with average scouting severity (0 to 0.3)
 *
 * All inputs are stored in yield_predictions.inputs_json and returned with the
 * result so every prediction is auditable.
 */
class YieldPredictionService {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * POST /api/v1/plantings/{cropId}/yield-prediction/recalculate
     * Recomputes the prediction for a crop/planting and stores inputs_json.
     */
    public function recalculate($cropId) {
        api_ok($this->recalculateData($cropId));
    }

    /**
     * Compute + persist a yield prediction for a crop; returns the result payload
     * WITHOUT emitting an HTTP response. Used by the endpoint and by the Feature 5
     * cascade. Returns null if the crop doesn't exist.
     */
    public function recalculateData($cropId) {
        $crop = $this->fetchCrop($cropId);
        if (!$crop) {
            return null;
        }

        $inputs = $this->buildInputs($crop);
        $prediction = $this->compute($inputs);

        // Upsert into yield_predictions for this crop
        $existing = $this->pdo->prepare("SELECT id FROM yield_predictions WHERE crop_id = ?");
        $existing->execute([(int) $cropId]);
        $existingRow = $existing->fetch();

        if ($existingRow) {
            $this->pdo->prepare(
                "UPDATE yield_predictions SET predicted_yield = ?, inputs_json = ? WHERE id = ?"
            )->execute([$prediction['predicted_yield'], json_encode($inputs), (int) $existingRow['id']]);
            $predId = (int) $existingRow['id'];
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO yield_predictions (crop_id, predicted_yield, inputs_json)
                 VALUES (?, ?, ?) RETURNING id"
            );
            $stmt->execute([(int) $cropId, $prediction['predicted_yield'], json_encode($inputs)]);
            $predId = (int) $stmt->fetchColumn();
        }

        IntelligenceUtils::auditSystem($this->pdo, 'yield_prediction_recalculated', 'yield_predictions', $predId, [
            'crop_id' => (int) $cropId,
            'predicted_yield' => $prediction['predicted_yield'],
        ]);

        return [
            'prediction_id'   => $predId,
            'crop_id'         => (int) $cropId,
            'crop_name'       => $crop['name'],
            'predicted_yield' => $prediction['predicted_yield'],
            'inputs'          => $inputs,
        ];
    }

    /**
     * GET /api/v1/plantings/{cropId}/yield-prediction
     * Returns the stored (or freshly computed) prediction + inputs.
     */
    public function get($cropId) {
        $crop = $this->fetchCrop($cropId);
        if (!$crop) {
            api_err('Planting (crop) not found', 404);
        }
        $row = null;
        $stmt = $this->pdo->prepare("SELECT * FROM yield_predictions WHERE crop_id = ? ORDER BY predicted_at DESC LIMIT 1");
        $stmt->execute([(int) $cropId]);
        $row = $stmt->fetch();

        if (!$row) {
            // No stored prediction: compute on the fly and return without persisting
            $inputs = $this->buildInputs($crop);
            $prediction = $this->compute($inputs);
            api_ok([
                'prediction_id'   => null,
                'crop_id'         => (int) $cropId,
                'crop_name'       => $crop['name'],
                'predicted_yield' => $prediction['predicted_yield'],
                'inputs'          => $inputs,
                'note'            => 'No stored prediction; computed on the fly.',
            ]);
        }

        api_ok([
            'prediction_id'   => (int) $row['id'],
            'crop_id'         => (int) $cropId,
            'crop_name'       => $crop['name'],
            'predicted_yield' => (float) $row['predicted_yield'],
            'inputs'          => json_decode($row['inputs_json'], true),
        ]);
    }

    // ---- internals ----

    private function buildInputs($crop) {
        $cropName = $crop['name'];

        // Historical yields for the same crop name (all seasons)
        $hist = $this->pdo->prepare(
            "SELECT hr.quantity
             FROM harvest_records hr
             JOIN crops c ON c.id = hr.crop_id
             WHERE c.name = ?
             ORDER BY hr.harvest_date"
        );
        $hist->execute([$cropName]);
        $histYields = array_map(fn($r) => (float) $r['quantity'], $hist->fetchAll());
        $avgHistorical = $histYields ? array_sum($histYields) / count($histYields) : 0.0;

        // This season's weather (rainfall, avg temp) since planting date
        $farmId   = (int) $crop['farm_id'];
        $planting = $crop['planting_date'] ?: '1970-01-01';
        $weather = $this->pdo->prepare(
            "SELECT COALESCE(SUM(rainfall_mm),0) AS rainfall, COALESCE(AVG(temperature),0) AS avg_temp
             FROM weather_records
             WHERE farm_id = ? AND weather_date >= ?"
        );
        $weather->execute([$farmId, $planting]);
        $w = $weather->fetch();
        $seasonRainfall = (float) $w['rainfall'];
        $seasonAvgTemp  = (float) $w['avg_temp'];

        // Historical average rainfall for this crop across past harvests
        $histRainfall = $this->histRainfallForCrop($cropName, $farmId);

        // Pest pressure: average scouting severity for the crop (0..1)
        $pest = $this->pdo->prepare(
            "SELECT COALESCE(AVG(severity), 0) AS severity, COUNT(*) AS reports
             FROM scouting_reports WHERE crop_id = ?"
        );
        $pest->execute([(int) $crop['id']]);
        $pRow = $pest->fetch();
        $avgSeverity = (float) $pRow['severity'];

        return [
            'crop_name'                  => $cropName,
            'avg_historical_yield'       => round($avgHistorical, 2),
            'historical_yield_count'     => count($histYields),
            'season_rainfall_mm'         => round($seasonRainfall, 2),
            'season_avg_temp_c'          => round($seasonAvgTemp, 2),
            'historical_avg_rainfall_mm' => round($histRainfall, 2),
            'avg_scouting_severity'      => round($avgSeverity, 2),
            'formula'                    => 'avg_historical_yield * weather_factor * (1 - pest_penalty)',
        ];
    }

    private function compute($inputs) {
        $avgHistorical = (float) $inputs['avg_historical_yield'];
        $seasonRain    = (float) $inputs['season_rainfall_mm'];
        $histRain      = (float) $inputs['historical_avg_rainfall_mm'];

        // weather_factor clamped to [0.5, 1.2]
        $weatherFactor = $histRain > 0 ? $seasonRain / $histRain : 1.0;
        $weatherFactor = max(0.5, min(1.2, $weatherFactor));

        // pest_penalty: severity (0..1) scaled into 0..0.3
        $severity = (float) $inputs['avg_scouting_severity'];
        $pestPenalty = max(0.0, min(0.3, $severity * 0.3));

        $predicted = $avgHistorical * $weatherFactor * (1 - $pestPenalty);

        return [
            'predicted_yield' => round($predicted, 2),
            'weather_factor'  => round($weatherFactor, 3),
            'pest_penalty'    => round($pestPenalty, 3),
        ];
    }

    private function histRainfallForCrop($cropName, $farmId) {
        // Approximate historical rainfall as the average rainfall of past harvest
        // seasons (all recorded weather for the farm up to now).
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(AVG(daily_rain), 0) AS avg_rain FROM (
                 SELECT SUM(rainfall_mm) AS daily_rain
                 FROM weather_records WHERE farm_id = ? GROUP BY weather_date::date
             ) t"
        );
        $stmt->execute([$farmId]);
        $row = $stmt->fetch();
        return (float) $row['avg_rain'];
    }

    private function fetchCrop($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM crops WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }
}
