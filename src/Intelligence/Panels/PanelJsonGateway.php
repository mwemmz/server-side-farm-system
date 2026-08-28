<?php

/**
 * Session-authenticated JSON gateway for control panels.
 *
 * The browser can't easily hold a JWT, so panel data + actions use this
 * lightweight session-based JSON endpoint instead of the JWT /api/v1 layer.
 * Both this gateway and the JWT /api/v1 routes call the exact same
 * PanelService classes, guaranteeing the two surfaces never drift apart.
 *
 * Invoked from the front controller:
 *   GET  index.php?module=X&action=panel_json&id=N[&farm_id=]   -> poll data
 *   POST index.php?module=X&action=panel_act&act=pump&id=N&on=1 -> perform action
 */
class PanelJsonGateway {

    public static function dispatch($pdo, $module, $query) {
        header('Content-Type: application/json; charset=UTF-8');

        $id = isset($query['id']) ? (int) $query['id'] : 0;
        $farmId = isset($query['farm_id']) ? (int) $query['farm_id'] : null;
        $act = $query['act'] ?? null;

        try {
            switch ($module) {
                case 'Irrigation':
                    require_once __DIR__ . '/IrrigationPanelService.php';
                    $svc = new IrrigationPanelService($pdo);
                    if ($act === 'pump')             $svc->setPump($id, !empty($query['on']));
                    elseif ($act === 'refill')       $svc->refill($id);
                    $data = $svc->panel($id ?: null);
                    break;
                case 'Storage':
                    require_once __DIR__ . '/StoragePanelService.php';
                    $data = (new StoragePanelService($pdo))->panel($id);
                    break;
                case 'Equipment':
                    require_once __DIR__ . '/EquipmentPanelService.php';
                    $svc = new EquipmentPanelService($pdo);
                    if ($act === 'run' || $act === 'park') {
                        $svc->setRunning($id, !empty($query['running']));
                    }
                    $data = $svc->panel($id);
                    break;
                case 'Livestock':
                    require_once __DIR__ . '/LivestockPanelService.php';
                    $data = (new LivestockPanelService($pdo))->panel($farmId);
                    break;
                case 'Weather':
                    require_once __DIR__ . '/WeatherPanelService.php';
                    $data = (new WeatherPanelService($pdo))->panel($farmId);
                    break;
                case 'Finance':
                    require_once __DIR__ . '/FinancialPanelService.php';
                    $data = (new FinancialPanelService($pdo))->panel($farmId);
                    break;
                default:
                    self::out(false, null, 'No panel for module');
                    exit;
            }

            if ($data === null) {
                self::out(false, null, 'Record not found');
                exit;
            }
            self::out(true, $data, null);
        } catch (Exception $e) {
            self::out(false, null, 'Panel error: ' . $e->getMessage());
        }
        exit;
    }

    private static function out($success, $data, $error) {
        echo json_encode(['success' => $success, 'data' => $data, 'error' => $error]);
    }
}
