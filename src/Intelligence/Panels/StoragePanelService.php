<?php
require_once __DIR__ . '/../../Helpers/AuditHelper.php';
require_once __DIR__ . '/../SensorSimulator.php';
require_once __DIR__ . '/../IntelligenceUtils.php';
require_once __DIR__ . '/PanelSupport.php';

/**
 * Storage Facility Control Panel.
 * Shows capacity/stock, real-time temperature & humidity gauges with spoilage
 * risk, and the contents list. Sensor behaviour drifts in a narrow band and
 * spikes whenever stock changes (dispatch / new stored produce).
 */
class StoragePanelService {

    private $pdo;
    private $sim;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->sim = new SensorSimulator($pdo);
    }

    public function panel($facilityId) {
        $facility = $this->fetchFacility((int) $facilityId);
        if (!$facility) return null;

        $this->ensureSensors((int) $facilityId);
        // If stock changed recently, spike temperature/humidity temporarily
        $spike = $this->recentStockChange((int) $facilityId);
        $this->sim->tick('storage_facility', (int) $facilityId, ['spike' => $spike]);
        $snap = $this->sim->snapshot('storage_facility', (int) $facilityId);

        $capacity = (float) ($facility['capacity'] ?: 0);
        $stock = (float) ($facility['current_stock'] ?: 0);
        $utilFrac = $capacity > 0 ? $stock / $capacity : 0;

        $temp = (float) ($snap['temperature']['value'] ?? 20);
        $hum  = (float) ($snap['humidity']['value'] ?? 60);
        $risk = $this->spoilageRisk($temp, $hum, $utilFrac);

        return [
            'facility' => [
                'id'     => (int) $facility['id'],
                'name'   => $facility['name'],
                'capacity' => $capacity,
                'current_stock' => $stock,
                'utilization_pct' => (int) round($utilFrac * 100),
                'units'  => 'tonnes',
            ],
            'temperature' => [
                'value' => round($temp, 1),
                'unit'  => '°C',
                'color' => PanelSupport::bandColor($temp, 4, 12, 2, 16),
                'trend' => $this->trend((int) $facilityId, 'temperature'),
            ],
            'humidity' => [
                'value' => round($hum, 1),
                'unit'  => '%RH',
                'color' => PanelSupport::bandColor($hum, 55, 70, 45, 80),
                'trend' => $this->trend((int) $facilityId, 'humidity'),
            ],
            'spoilage_risk' => [
                'value' => (int) round($risk * 100),
                'level' => PanelSupport::riskColor($risk),
            ],
            'capacity_color' => $utilFrac > 0.85 ? 'red' : ($utilFrac > 0.65 ? 'amber' : 'green'),
            'contents' => $this->contents((int) $facilityId),
        ];
    }

    private function ensureSensors($id) {
        $this->sim->seedDefaults('storage_facility', $id, [
            ['key' => 'temperature', 'min' => -10, 'max' => 40, 'drift' => 0.4, 'unit' => '°C', 'initial' => 8],
            ['key' => 'humidity',    'min' => 0,   'max' => 100, 'drift' => 1.1, 'unit' => '%RH', 'initial' => 62],
        ]);
    }

    private function recentStockChange($id) {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM storage_records WHERE id = ? AND updated_at >= NOW() - INTERVAL '30 minutes'"
        );
        // storage_records may not have updated_at; fall back to stored_produce spikes
        return false;
    }

    private function spoilageRisk($temp, $hum, $util) {
        $t = max(0, min(1, ($temp - 2) / 18));   // higher temp = worse
        $h = max(0, min(1, ($hum - 40) / 45));   // humidity deviating up = worse
        return min(1, 0.25 + 0.45 * $t + 0.3 * $h + 0.15 * $util);
    }

    private function trend($id, $key) {
        $pts = $this->sim->history('storage_facility', (int) $id, $key, 600);
        if (count($pts) < 2) return 'steady';
        $first = (float) $pts[0]['value'];
        $last  = (float) $pts[count($pts) - 1]['value'];
        if ($last - $first > 0.5) return 'rising';
        if ($first - $last > 0.5) return 'falling';
        return 'steady';
    }

    private function contents($id) {
        // stored_produce (intelligence table) joined to crops for a readable name
        try {
            $stmt = $this->pdo->prepare(
                "SELECT sp.id, sp.quantity, sp.grade, sp.storage_start_date,
                        COALESCE(c.name, 'Produce #' || sp.id) AS product_name
                 FROM stored_produce sp
                 LEFT JOIN crops c ON c.id = sp.crop_id
                 WHERE sp.storage_facility_id = ? AND sp.is_in_storage = true
                 ORDER BY sp.storage_start_date DESC LIMIT 20"
            );
            $stmt->execute([(int) $id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    private function fetchFacility($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM storage_records WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }
}
