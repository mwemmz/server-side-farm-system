<?php
require_once __DIR__ . '/../../Helpers/AuditHelper.php';
require_once __DIR__ . '/../SensorSimulator.php';
require_once __DIR__ . '/../IntelligenceUtils.php';
require_once __DIR__ . '/PanelSupport.php';

/**
 * Equipment Control Panel.
 * Live fuel, engine temperature and running-hours gauges for each machine.
 * Engine temp rises and fuel drains to zero while "running"; both ease back
 * toward baseline when parked. Maintenance status drives the alert banner.
 */
class EquipmentPanelService {

    private $pdo;
    private $sim;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->sim = new SensorSimulator($pdo);
    }

    public function panel($equipmentId) {
        $eq = $this->fetchEquipment((int) $equipmentId);
        if (!$eq) return null;

        // Running state is stored as a simulated sensor (0/1), default parked.
        $this->ensureSensors((int) $equipmentId);
        $running = $this->isRunning((int) $equipmentId);
        $this->sim->tick('equipment', (int) $equipmentId, ['running' => $running]);
        $snap = $this->sim->snapshot('equipment', (int) $equipmentId);

        $fuel     = (float) ($snap['fuel_level']['value'] ?? 100);
        $temp     = (float) ($snap['engine_temp']['value'] ?? 40);
        $hoursNow = (float) ($snap['hours_today']['value'] ?? 0);
        $status   = strtolower((string) ($eq['maintenance_status'] ?? 'Operational'));

        $maintenanceNeeded = in_array($status, ['maintenance', 'maintenance required', 'fault', 'broken', 'repair'], true);

        return [
            'equipment' => [
                'id'    => (int) $eq['id'],
                'name'  => $eq['name'],
                'status'=> $eq['maintenance_status'],
                'state' => $running ? 'running' : 'parked',
            ],
            'fuel' => [
                'value' => round($fuel, 1),
                'unit'  => '%',
                'color' => PanelSupport::levelColor($fuel / 100),
            ],
            'engine_temp' => [
                'value' => round($temp, 1),
                'unit'  => '°C',
                'color' => PanelSupport::bandColor($temp, 60, 95, 95, 110),
            ],
            'hours_today' => [
                'value' => round($hoursNow, 2),
                'unit'  => 'h',
            ],
            'alert' => $maintenanceNeeded ? [
                'level' => 'red',
                'message' => "Maintenance required for \"{$eq['name']}\" — service before next use.",
            ] : null,
            'service_due' => $maintenanceNeeded,
        ];
    }

    /** Toggle run/park state (used by the panel control if needed). */
    public function setRunning($equipmentId, $on) {
        $eq = $this->fetchEquipment((int) $equipmentId);
        if (!$eq) return null;
        $this->ensureSensors((int) $equipmentId);
        $this->sim->set('equipment', (int) $equipmentId, 'running', $on ? 1 : 0);
        AuditHelper::system($this->pdo, 'equipment_state_change', 'equipment', (int) $equipmentId, ['running' => (bool) $on]);
        return ['id' => (int) $equipmentId, 'running' => (bool) $on];
    }

    private function ensureSensors($id) {
        $this->sim->seedDefaults('equipment', $id, [
            ['key' => 'fuel_level',   'min' => 0, 'max' => 100, 'drift' => 0.8, 'unit' => '%', 'initial' => mt_rand(40, 100)],
            ['key' => 'engine_temp',  'min' => 0, 'max' => 130, 'drift' => 1.2, 'unit' => '°C', 'initial' => 40],
            ['key' => 'hours_today',  'min' => 0, 'max' => 24,  'drift' => 0.05, 'unit' => 'h', 'initial' => 0],
            ['key' => 'running',      'min' => 0, 'max' => 1,   'drift' => 0,    'unit' => '', 'initial' => 0],
        ]);
    }

    private function isRunning($id) {
        $s = $this->sim->get('equipment', (int) $id, 'running');
        return $s && (float) $s['current_value'] >= 0.5;
    }

    private function fetchEquipment($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM equipment WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }
}
