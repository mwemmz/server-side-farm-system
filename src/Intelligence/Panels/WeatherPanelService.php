<?php
require_once __DIR__ . '/PanelSupport.php';

/**
 * Farm Weather Panel.
 * Unlike the simulated sensors, weather reads the real stored weather_records.
 * Shows the current conditions, a short-term trend, rainfall and a comfort /
 * irrigation-suitability index. If no farm_id is supplied we fall back to the
 * most recent record across all farms.
 */
class WeatherPanelService {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function panel($farmId = null) {
        $current = $this->current($farmId);
        if (!$current) {
            $farmId = null;
            $current = $this->current(null);
        }
        if (!$current) {
            return ['empty' => true, 'message' => 'No weather records yet — add a weather reading.'];
        }

        $history = $this->history($current['farm_id'] ?? $farmId);
        $trend = $this->trend($history);

        $temp = (float) $current['temperature'];
        $hum  = (float) $current['humidity'];
        $rain = (float) ($current['rainfall_mm'] ?? 0);

        return [
            'farm_id' => (int) ($current['farm_id'] ?? $farmId),
            'current' => [
                'temperature' => ['value' => round($temp, 1), 'unit' => '°C', 'color' => PanelSupport::bandColor($temp, 15, 32, 5, 40)],
                'humidity'    => ['value' => round($hum, 1), 'unit' => '%RH', 'color' => PanelSupport::bandColor($hum, 40, 75, 20, 90)],
                'rainfall_mm' => ['value' => round($rain, 1), 'unit' => 'mm'],
                'recorded_at' => $current['weather_date'],
            ],
            'trend' => $trend,
            'comfort_index' => $this->comfortIndex($temp, $hum),
            'irrigation_suitability' => $this->irrigationSuitability($trend, $rain),
            'history' => $history,
        ];
    }

    private function current($farmId) {
        $sql = "SELECT * FROM weather_records";
        $params = [];
        if ($farmId) {
            $sql .= " WHERE farm_id = ?";
            $params[] = (int) $farmId;
        }
        $sql .= " ORDER BY weather_date DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    private function history($farmId) {
        if (!$farmId) return [];
        $stmt = $this->pdo->prepare(
            "SELECT temperature, humidity, rainfall_mm, weather_date
             FROM weather_records WHERE farm_id = ?
             ORDER BY weather_date DESC LIMIT 12"
        );
        $stmt->execute([(int) $farmId]);
        return array_reverse($stmt->fetchAll());
    }

    private function trend($history) {
        if (count($history) < 2) return 'steady';
        $first = (float) $history[0]['temperature'];
        $last  = (float) $history[count($history) - 1]['temperature'];
        if ($last - $first > 1.5) return 'warming';
        if ($first - $last > 1.5) return 'cooling';
        return 'steady';
    }

    private function comfortIndex($temp, $hum) {
        $score = 100.0;
        $score -= abs($temp - 24) * 2.5;
        $score -= max(0, $hum - 70) * 0.8;
        $score -= max(0, 30 - $hum) * 0.5;
        $score = max(0, min(100, $score));
        return ['value' => (int) round($score), 'color' => PanelSupport::riskColor((100 - $score) / 100)];
    }

    private function irrigationSuitability($trend, $rain) {
        if ($trend === 'cooling' || $rain > 10) {
            return ['label' => 'Good', 'value' => 'Irrigation not urgent — recent rain / cooler conditions.', 'color' => 'green'];
        }
        if ($trend === 'warming') {
            return ['label' => 'Dry', 'value' => 'Warming trend & low recent rain — consider irrigating soon.', 'color' => 'amber'];
        }
        return ['label' => 'Moderate', 'value' => 'Conditions stable — schedule irrigation as needed.', 'color' => 'green'];
    }
}
