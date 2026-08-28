<?php
require_once __DIR__ . '/IntelligenceUtils.php';

/**
 * Phase 6 — Simulated sensor engine.
 *
 * Generates realistic, continuously-changing sensor values without real
 * hardware. Sensors live in `simulated_sensors`; every change is appended to
 * `sensor_history` (for sparklines) and pruned to the newest 500 points.
 *
 * "Live" behaviour is driven by wall-clock time: each tick advances a sensor
 * by `SIM_STEP_SECONDS` of elapsed time (capped), so polling any panel every few
 * seconds makes the numbers move and fills the trend history — no background
 * daemon required. The identical engine can later be swapped for real sensor
 * feeds without touching the frontend.
 */
class SensorSimulator {

    /** Wall-clock seconds each simulated step represents. */
    const SIM_STEP_SECONDS = 3;
    /** Maximum steps advanced per request (cap on catch-up drift). */
    const MAX_STEPS_PER_TICK = 24;
    /** Keep only this many history points per sensor. */
    const HISTORY_LIMIT = 500;

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /** Get the sensor row for a key, or null if it does not exist yet. */
    public function get($type, $entityId, $key) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM simulated_sensors WHERE entity_type = ? AND entity_id = ? AND sensor_key = ?"
        );
        $stmt->execute([$type, (int) $entityId, $key]);
        return $stmt->fetch();
    }

    /**
     * Create (or update metadata of) a sensor. Sets an initial value if the
     * sensor is new and records a seed history point.
     */
    public function ensure($type, $entityId, $key, $min, $max, $drift, $unit, $initial = null) {
        $existing = $this->get($type, $entityId, $key);
        if (!$existing) {
            $value = $initial ?? (($min + $max) / 2);
            $stmt = $this->pdo->prepare(
                "INSERT INTO simulated_sensors
                    (entity_type, entity_id, sensor_key, current_value, min_value, max_value, drift_rate, unit, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW()) RETURNING id"
            );
            $stmt->execute([$type, (int) $entityId, $key, $value, $min, $max, $drift, $unit]);
            $sensorId = (int) $stmt->fetchColumn();
            $this->recordHistory($sensorId, $value);
            return (int) $sensorId;
        }
        // Optionally bump metadata if it changed
        $this->pdo->prepare(
            "UPDATE simulated_sensors SET min_value=?, max_value=?, drift_rate=?, unit=? WHERE id=?"
        )->execute([$min, $max, $drift, $unit, (int) $existing['id']]);
        return (int) $existing['id'];
    }

    /** Directly set a sensor value (used by refill / toggles). Records history. */
    public function set($type, $entityId, $key, $value) {
        $sensor = $this->get($type, $entityId, $key);
        if (!$sensor) {
            return null;
        }
        $min = $sensor['min_value'] === null ? PHP_FLOAT_MIN : (float) $sensor['min_value'];
        $max = $sensor['max_value'] === null ? PHP_FLOAT_MAX : (float) $sensor['max_value'];
        $clamped = max($min, min($max, (float) $value));
        $this->pdo->prepare(
            "UPDATE simulated_sensors SET current_value=?, updated_at=NOW() WHERE id=?"
        )->execute([$clamped, (int) $sensor['id']]);
        $this->recordHistory((int) $sensor['id'], $clamped);
        return $clamped;
    }

    /**
     * Advance every sensor for an entity by elapsed wall-clock time (capped),
     * applying entity-specific behavior via $context flags.
     *
     * @param string $type     entity_type ('irrigation_system'|'storage_facility'|'equipment'|...)
     * @param int    $entityId
     * @param array  $context  e.g. ['pump_on'=>bool, 'refill'=>bool, 'running'=>bool]
     * @return array summary
     */
    public function tick($type, $entityId, $context = []) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM simulated_sensors WHERE entity_type = ? AND entity_id = ?"
        );
        $stmt->execute([$type, (int) $entityId]);
        $sensors = $stmt->fetchAll();
        if (!$sensors) {
            return ['advanced' => 0, 'nudged' => 0];
        }

        $steps = 1;
        $maxUpdated = 0;
        foreach ($sensors as $s) {
            $t = strtotime((string) $s['updated_at']);
            if ($t > $maxUpdated) {
                $maxUpdated = $t;
            }
        }
        if ($maxUpdated > 0) {
            $elapsed = time() - $maxUpdated;
            if ($elapsed > 0) {
                $steps = (int) floor($elapsed / self::SIM_STEP_SECONDS);
            }
        }
        $steps = max(1, min(self::MAX_STEPS_PER_TICK, $steps));

        $nudged = 0;
        foreach ($sensors as $s) {
            $min = $s['min_value'] === null ? PHP_FLOAT_MIN : (float) $s['min_value'];
            $max = $s['max_value'] === null ? PHP_FLOAT_MAX : (float) $s['max_value'];
            $value = (float) $s['current_value'];
            $newValue = $value;
            for ($i = 0; $i < $steps; $i++) {
                $delta = $this->computeDelta($s, $context);
                $newValue = max($min, min($max, $newValue + $delta));
            }
            if (abs($newValue - $value) > 0.0001) {
                $this->pdo->prepare(
                    "UPDATE simulated_sensors SET current_value=?, updated_at=NOW() WHERE id=?"
                )->execute([$newValue, (int) $s['id']]);
                $this->recordHistory((int) $s['id'], $newValue);
                $nudged++;
            }
        }

        $this->pruneHistoryForEntity($type, $entityId);
        return ['advanced_steps' => $steps, 'nudged' => $nudged];
    }

    /** A random walk appropriate to the sensor key and current context. */
    private function computeDelta($sensor, $context) {
        $rate = (float) $sensor['drift_rate'];
        $key = (string) $sensor['sensor_key'];
        $type = (string) $sensor['entity_type'];
        $jitter = (mt_rand(-1000, 1000)) / 1000.0; // -1 .. 1

        $delta = $rate * $jitter;

        if ($type === 'irrigation_system') {
            if ($key === 'reservoir_level') {
                if (!empty($context['pump_on'])) {
                    // Active draining proportional to flow
                    $delta = -abs($rate) * (1.0 + abs($jitter));
                } elseif (!empty($context['refill'])) {
                    // Refill handled via set(); drift back to normal
                    $delta = abs($rate) * 0.5 * (1.0 + abs($jitter));
                } else {
                    // Slow evaporation drift
                    $delta = abs($rate) * 0.05 * $jitter;
                }
            } elseif ($key === 'flow_rate') {
                $delta = empty($context['pump_on']) ? -abs($rate) : +abs($rate);
            } elseif ($key === 'soil_moisture') {
                $delta = empty($context['pump_on']) ? -abs($rate) * 0.3 : +abs($rate);
            }
        } elseif ($type === 'storage_facility') {
            // Narrow-band drift with occasional small spike handled by callers
            $delta = $delta * 0.35;
            if (!empty($context['spike'])) {
                $delta += abs($rate) * 2.0;
            }
        } elseif ($type === 'field' && $key === 'soil_moisture') {
            // Field soil moisture: rises while irrigating, decays slowly after
            $delta = empty($context['pump_on']) ? -abs($rate) * 0.25 : +abs($rate);
        } elseif ($type === 'equipment') {
            if ($key === 'engine_temp') {
                $delta = empty($context['running']) ? -abs($rate) : +abs($rate);
            } elseif ($key === 'fuel_level') {
                $delta = empty($context['running']) ? 0.0 : -abs($rate);
            } elseif ($key === 'hours_today') {
                $delta = empty($context['running']) ? 0.0 : $rate;
            }
        }
        return $delta;
    }

    /** Snapshot of all sensors for an entity (with metadata for gauges). */
    public function snapshot($type, $entityId) {
        $stmt = $this->pdo->prepare(
            "SELECT id, entity_type, entity_id, sensor_key, current_value, min_value, max_value, unit, updated_at
             FROM simulated_sensors WHERE entity_type = ? AND entity_id = ? ORDER BY id"
        );
        $stmt->execute([$type, (int) $entityId]);
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['sensor_key']] = [
                'value'   => (float) $r['current_value'],
                'min'     => $r['min_value'] === null ? null : (float) $r['min_value'],
                'max'     => $r['max_value'] === null ? null : (float) $r['max_value'],
                'unit'    => $r['unit'],
                'updated_at' => $r['updated_at'],
            ];
        }
        return $out;
    }

    /** History points for a sensor (or all sensors), optionally time-windowed. */
    public function history($type, $entityId, $sensorKey = null, $rangeSeconds = null) {
        $sql = "SELECT h.value, h.recorded_at, s.sensor_key
                FROM sensor_history h
                JOIN simulated_sensors s ON s.id = h.sensor_id
                WHERE s.entity_type = ? AND s.entity_id = ?";
        $params = [$type, (int) $entityId];
        if ($sensorKey) {
            $sql .= " AND s.sensor_key = ?";
            $params[] = $sensorKey;
        }
        if ($rangeSeconds) {
            $sql .= " AND h.recorded_at >= NOW() - (? || ' seconds')::interval";
            $params[] = (int) $rangeSeconds;
        }
        $sql .= " ORDER BY h.recorded_at ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Seed default sensors for an entity type/id so panels always render. */
    public function seedDefaults($type, $entityId, $definitions) {
        foreach ($definitions as $def) {
            $this->ensure(
                $type,
                $entityId,
                $def['key'],
                $def['min'],
                $def['max'],
                $def['drift'],
                $def['unit'],
                $def['initial'] ?? null
            );
        }
    }

    // ---- internals ----

    private function recordHistory($sensorId, $value) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO sensor_history (sensor_id, value) VALUES (?, ?)"
        );
        $stmt->execute([(int) $sensorId, $value]);
    }

    private function pruneHistoryForEntity($type, $entityId) {
        $this->pdo->prepare(
            "DELETE FROM sensor_history h
             USING simulated_sensors s
             WHERE h.sensor_id = s.id
               AND s.entity_type = ? AND s.entity_id = ?
               AND h.id NOT IN (
                   SELECT id FROM (
                       SELECT h2.id,
                              ROW_NUMBER() OVER (PARTITION BY s2.sensor_key ORDER BY h2.recorded_at DESC, h2.id DESC) AS rn
                       FROM sensor_history h2
                       JOIN simulated_sensors s2 ON s2.id = h2.sensor_id
                       WHERE s2.entity_type = ? AND s2.entity_id = ?
                   ) x WHERE rn <= 500
               )"
        )->execute([$type, (int) $entityId, $type, (int) $entityId]);
    }
}
