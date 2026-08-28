<?php
require_once __DIR__ . '/../../Helpers/AuditHelper.php';
require_once __DIR__ . '/../SensorSimulator.php';
require_once __DIR__ . '/../IntelligenceUtils.php';
require_once __DIR__ . '/PanelSupport.php';

/**
 * Livestock Control Panel (farm-level aggregate).
 * Summarises the herd per type plus live barn-environment gauges (temperature,
 * ventilation, feed & water levels) simulated per farm. Uses farm_id you pass.
 */
class LivestockPanelService {

    private $pdo;
    private $sim;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->sim = new SensorSimulator($pdo);
    }

    public function panel($farmId = null) {
        $farmId = (int) ($farmId ?: 0);

        $herd = $this->herdSummary($farmId);
        $env = $this->environment($farmId, $herd['total']);

        $alert = null;
        if ($env['feed']['value'] < 15) $alert = ['level' => 'red', 'message' => 'Feed levels critically low — restock required.'];
        elseif ($env['water']['value'] < 15) $alert = ['level' => 'red', 'message' => 'Water levels dangerously low.'];
        elseif ($env['temperature']['value'] > 30) $alert = ['level' => 'amber', 'message' => 'Barn temperature high — check ventilation.'];

        return [
            'farm_id' => $farmId,
            'summary' => [
                'total'   => $herd['total'],
                'by_type'=> $herd['byType'],
                'avg_age_days' => $herd['avgAgeDays'],
            ],
            'environment' => [
                'temperature' => ['value' => round($env['temperature']['value'], 1), 'unit' => '°C', 'color' => PanelSupport::bandColor($env['temperature']['value'], 15, 28, 10, 32)],
                'humidity'    => ['value' => round($env['humidity']['value'], 1), 'unit' => '%RH', 'color' => PanelSupport::bandColor($env['humidity']['value'], 40, 75, 30, 85)],
                'feed'        => ['value' => round($env['feed']['value'], 1), 'unit' => '%', 'color' => PanelSupport::levelColor($env['feed']['value'] / 100)],
                'water'       => ['value' => round($env['water']['value'], 1), 'unit' => '%', 'color' => PanelSupport::levelColor($env['water']['value'] / 100)],
            ],
            'alert' => $alert,
            'health_index' => $this->healthIndex($env),
        ];
    }

    private function herdSummary($farmId) {
        if ($farmId) {
            $stmt = $this->pdo->prepare("SELECT * FROM livestock WHERE farm_id = ?");
            $stmt->execute([$farmId]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM livestock");
        }
        $rows = $stmt->fetchAll();
        $byType = [];
        $ageSum = 0;
        $ageCount = 0;
        foreach ($rows as $r) {
            $type = $r['type'] ?: 'Unknown';
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            if (!empty($r['dob'])) {
                $ageSum += (int) ((time() - strtotime((string) $r['dob'])) / 86400);
                $ageCount++;
            }
        }
        arsort($byType);
        return [
            'total' => count($rows),
            'byType' => $byType,
            'avgAgeDays' => $ageCount ? (int) round($ageSum / $ageCount) : 0,
        ];
    }

    private function environment($farmId, $herdSize) {
        // Simulate barn environmental sensors for this farm entity.
        $this->sim->seedDefaults('livestock_group', $farmId, [
            ['key' => 'temperature', 'min' => 0, 'max' => 45, 'drift' => 0.6, 'unit' => '°C', 'initial' => 22],
            ['key' => 'humidity',    'min' => 0, 'max' => 100, 'drift' => 1.3, 'unit' => '%RH', 'initial' => 60],
            ['key' => 'feed',        'min' => 0, 'max' => 100, 'drift' => 0.5, 'unit' => '%', 'initial' => 80],
            ['key' => 'water',       'min' => 0, 'max' => 100, 'drift' => 0.6, 'unit' => '%', 'initial' => 85],
        ]);
        $this->sim->tick('livestock_group', $farmId, []);
        return $this->sim->snapshot('livestock_group', $farmId);
    }

    private function healthIndex($env) {
        $score = 100.0;
        $score -= max(0, abs($env['temperature']['value'] - 22) - 3) * 2;
        $score -= max(0, abs($env['humidity']['value'] - 60) - 15) * 0.5;
        $score -= (100 - $env['feed']['value']) * 0.1;
        $score -= (100 - $env['water']['value']) * 0.1;
        $score = max(0, min(100, $score));
        return [
            'value' => (int) round($score),
            'color' => PanelSupport::riskColor((100 - $score) / 100),
        ];
    }
}
