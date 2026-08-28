<?php
require_once __DIR__ . '/../../Helpers/AuditHelper.php';
require_once __DIR__ . '/../SensorSimulator.php';
require_once __DIR__ . '/../IntelligenceUtils.php';
require_once __DIR__ . '/PanelSupport.php';

/**
 * Phase 6 — Flagship: Irrigation Control Panel.
 *
 * Aggregates everything the irrigation panel needs into a single JSON payload:
 * reservoir gauge, pump status + toggle, live flow rate, today's usage counter,
 * per-zone soil moisture strip, next schedule slot (timeline) and the active
 * irrigation recommendation banner. Actions (pump on/off, refill) mutate the
 * simulated sensor model and audit themselves.
 */
class IrrigationPanelService {

    /** Default reservoir capacity (L) when no config is stored on the sensor. */
    const RESERVOIR_CAPACITY = 100000.0;

    private $pdo;
    private $sim;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->sim = new SensorSimulator($pdo);
    }

    /** Full aggregated panel payload. Returns null if the system is missing. */
    public function panel($systemId) {
        $sys = $this->fetchSystem($systemId);
        if (!$sys) return null;

        $this->ensureSensors($sys);
        $pumpOn = $this->readPump((int) $systemId);

        // Advance sensors by elapsed wall-clock time (live feel).
        $this->sim->tick('irrigation_system', (int) $systemId, ['pump_on' => $pumpOn]);
        $snap = $this->sim->snapshot('irrigation_system', (int) $systemId);

        $reservoir = $snap['reservoir_level'] ?? ['value' => self::RESERVOIR_CAPACITY, 'min' => 0, 'max' => self::RESERVOIR_CAPACITY, 'unit' => 'L'];
        $capacity = (float) ($reservoir['max'] ?: self::RESERVOIR_CAPACITY);
        $level = (float) ($reservoir['value'] ?? 0);
        $levelFrac = $capacity > 0 ? $level / $capacity : 0;

        // Per-zone soil moisture (one bar per field the system's farm covers).
        $fields = $this->fieldsForFarm((int) $sys['farm_id']);
        $zones = [];
        foreach ($fields as $f) {
            $this->sim->ensure('field', (int) $f['id'], 'soil_moisture', 0, 100, 1.2, '%', 40);
            $this->sim->tick('field', (int) $f['id'], ['pump_on' => $pumpOn]);
            $fsnap = $this->sim->snapshot('field', (int) $f['id']);
            $m = (float) ($fsnap['soil_moisture']['value'] ?? 0);
            $zones[] = [
                'field_id'   => (int) $f['id'],
                'name'       => $f['name'],
                'size_ha'    => $f['size'] !== null ? (float) $f['size'] : null,
                'moisture'   => $m,
                'color'      => PanelSupport::riskColor((100 - $m) / 100),
            ];
        }

        // Today's usage counter (accumulated while pump runs).
        $usage = $this->accumulateUsage((int) $systemId, $snap, $pumpOn);

        // Next schedule slot + active recommendation.
        $nextSchedule = $this->nextSchedule((int) $sys['farm_id'], (int) $sys['id']);
        $recommendation = $this->activeRecommendation((int) $sys['farm_id']);

        $flow = (float) ($snap['flow_rate']['value'] ?? 0);

        return [
            'system' => [
                'id'     => (int) $sys['id'],
                'farm_id'=> (int) $sys['farm_id'],
                'type'   => $sys['type'],
                'status' => $sys['status'],
            ],
            'reservoir' => [
                'current' => round($level, 1),
                'capacity'=> round($capacity, 1),
                'unit'    => 'L',
                'pct'     => (int) round($levelFrac * 100),
                'color'   => PanelSupport::levelColor($levelFrac),
            ],
            'pump' => [
                'on'    => $pumpOn,
                'state' => $pumpOn ? 'on' : 'off',
            ],
            'flow_rate' => [
                'value' => round($flow, 1),
                'unit'  => 'L/min',
            ],
            'usage_today' => [
                'value' => round($usage, 1),
                'unit'  => 'L',
            ],
            'moisture' => $zones,
            'next_schedule' => $nextSchedule,
            'recommendation' => $recommendation,
            'inputs' => [
                'system_id' => (int) $systemId,
                'farm_id'   => (int) $sys['farm_id'],
                'pump_on'   => $pumpOn,
            ],
        ];
    }

    /** Toggle the pump on/off. Returns the new pump state. */
    public function setPump($systemId, $on) {
        $sys = $this->fetchSystem($systemId);
        if (!$sys) return null;
        $this->ensureSensors($sys);
        $this->sim->set('irrigation_system', (int) $systemId, 'pump_status', $on ? 1 : 0);
        AuditHelper::system($this->pdo, 'irrigation_pump_toggle', 'irrigation_systems', (int) $systemId, ['on' => (bool) $on]);
        IntelligenceUtils::notify(
            $this->pdo,
            "Irrigation pump for {$sys['type']} system #{$systemId} turned " . ($on ? 'ON' : 'OFF') . "."
        );
        $snapshot = $this->sim->snapshot('irrigation_system', (int) $systemId);
        $usage = $this->accumulateUsage((int) $systemId, $snapshot, (bool) $on);
        return [
            'id'    => (int) $systemId,
            'on'    => (bool) $on,
            'state' => $on ? 'on' : 'off',
            'usage_today' => round($usage, 1),
        ];
    }

    /** Manually refill the reservoir (jumps level toward ~90% of capacity). */
    public function refill($systemId) {
        $sys = $this->fetchSystem($systemId);
        if (!$sys) return null;
        $this->ensureSensors($sys);
        $res = $this->sim->get('irrigation_system', (int) $systemId, 'reservoir_level');
        $capacity = $res && $res['max_value'] ? (float) $res['max_value'] : self::RESERVOIR_CAPACITY;
        $newLevel = round($capacity * 0.9, 1);
        $this->sim->set('irrigation_system', (int) $systemId, 'reservoir_level', $newLevel);
        AuditHelper::system($this->pdo, 'irrigation_reservoir_refill', 'irrigation_systems', (int) $systemId, ['filled_to' => $newLevel]);
        IntelligenceUtils::notify($this->pdo, "Reservoir for irrigation system #{$systemId} refilled to " . number_format($newLevel) . " L.");
        return ['id' => (int) $systemId, 'current' => $newLevel, 'capacity' => $capacity, 'unit' => 'L'];
    }

    // ---- internals ----

    private function ensureSensors($sys) {
        $id = (int) $sys['id'];
        $this->sim->seedDefaults('irrigation_system', $id, [
            // key,     min,   max,                 drift,   unit,    initial
            ['key' => 'reservoir_level', 'min' => 0, 'max' => self::RESERVOIR_CAPACITY, 'drift' => 8.0, 'unit' => 'L', 'initial' => self::RESERVOIR_CAPACITY * 0.8],
            ['key' => 'flow_rate',       'min' => 0, 'max' => 800, 'drift' => 2.5, 'unit' => 'L/min', 'initial' => 0],
            ['key' => 'pump_status',     'min' => 0, 'max' => 1,  'drift' => 0,   'unit' => '', 'initial' => 0],
        ]);
        // ensure usage_today exists
        $this->sim->ensure('irrigation_system', $id, 'usage_today', 0, 9999999999, 0, 'L', 0);
    }

    private function readPump($systemId) {
        $s = $this->sim->get('irrigation_system', $systemId, 'pump_status');
        return $s && (float) $s['current_value'] >= 0.5;
    }

    private function accumulateUsage($systemId, $snap, $pumpOn) {
        $sensor = $this->sim->get('irrigation_system', $systemId, 'usage_today');
        if (!$sensor) return 0;
        $current = (float) $sensor['current_value'];

        // reset at day boundary
        $updatedDay = date('Y-m-d', strtotime((string) $sensor['updated_at']));
        if ($updatedDay !== date('Y-m-d')) {
            $this->sim->set('irrigation_system', $systemId, 'usage_today', 0);
            $current = 0;
        }

        if ($pumpOn) {
            $flow = (float) ($snap['flow_rate']['value'] ?? 0);
            $elapsedSec = time() - strtotime((string) $sensor['updated_at']);
            $minutes = max(0, $elapsedSec / 60.0);
            if ($minutes > 0.05 && $flow > 0) {
                $current += $flow * $minutes;
                $this->sim->set('irrigation_system', $systemId, 'usage_today', $current);
            }
        }
        return $current;
    }

    private function fetchSystem($systemId) {
        $stmt = $this->pdo->prepare("SELECT * FROM irrigation_systems WHERE id = ?");
        $stmt->execute([(int) $systemId]);
        return $stmt->fetch();
    }

    private function fieldsForFarm($farmId) {
        $stmt = $this->pdo->prepare("SELECT id, name, size, soil_type FROM fields WHERE farm_id = ? ORDER BY id");
        $stmt->execute([(int) $farmId]);
        return $stmt->fetchAll();
    }

    private function nextSchedule($farmId, $systemId) {
        $stmt = $this->pdo->prepare(
            "SELECT s.id, s.field_id, f.name AS field_name, s.schedule_date, s.liters, s.status
             FROM irrigation_schedules s
             LEFT JOIN fields f ON f.id = s.field_id
             WHERE s.schedule_date >= CURRENT_DATE AND s.status = 'scheduled'
             ORDER BY s.schedule_date ASC, s.id ASC LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function activeRecommendation($farmId) {
        $stmt = $this->pdo->prepare(
            "SELECT ir.id, ir.field_id, f.name AS field_name, ir.recommended_date, ir.recommended_liters, ir.reason, ir.created_at
             FROM irrigation_recommendations ir
             LEFT JOIN fields f ON f.id = ir.field_id
             WHERE ir.status = 'pending'
             ORDER BY ir.created_at DESC LIMIT 1"
        );
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }
}
