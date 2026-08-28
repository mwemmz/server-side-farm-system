<?php
// Scheduled-job entry point for the intelligence layer.
//
// Runs every hour / daily:
//   Feature 1 — reorder check (hourly)
//   Feature 2 — irrigation recommendations (daily)
//   Feature 3 — yield-prediction recalc for all active plantings (daily)
//
// Invocation:
//   HTTP : POST/GET to /api/v1/cron.php?token=<CRON_SECRET>  (or header X-Cron-Token)
//   CLI  : php public/api/v1/cron.php <CRON_SECRET>
//
// Protected by CRON_SECRET (JWT-independent) so it can run unattended in cron
// without holding a user token.

require_once __DIR__ . '/../../../config/database.php';   // defines $pdo + getEnvVar
require_once __DIR__ . '/../../../db/migrate.php';        // ensures schema exists

$secret = getEnvVar('CRON_SECRET', getEnvVar('JWT_SECRET', 'ffms_change_me_secret'));

// Resolve token from CLI arg, query string, or header.
$token = null;
if (PHP_SAPI === 'cli') {
    $token = $argv[1] ?? null;
} else {
    $token = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? null;
}

if (!$token || !hash_equals($secret, $token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid cron token']);
    exit;
}

header('Content-Type: application/json');

$results = [];

try {
    // Feature 1: reorder check (hourly)
    require_once __DIR__ . '/../../../src/Intelligence/ReorderEngine.php';
    $reorder = new ReorderEngine($GLOBALS['pdo']);
    // Capture pass/fail; runCronLogic() emits api_ok (exits) when called via API,
    // so we call the underlying logic inline instead.
    $results['reorder'] = runReorder($GLOBALS['pdo']);

    // Feature 2: irrigation recommendations (daily)
    require_once __DIR__ . '/../../../src/Intelligence/IrrigationIntelligence.php';
    $results['irrigation'] = runIrrigation($GLOBALS['pdo']);

    // Feature 3: yield predictions for all active plantings (daily)
    require_once __DIR__ . '/../../../src/Intelligence/YieldPredictionService.php';
    $results['yield'] = runYieldPredictions($GLOBALS['pdo']);

    echo json_encode(['success' => true, 'data' => $results]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ---- non-exiting cron bodies ----

function runReorder($pdo) {
    $pdo->beginTransaction();
    try {
        $engine = new ReorderEngine($pdo);
        $summary = ['below_threshold' => 0, 'pos_created' => 0];
        $rules = $pdo->query("SELECT * FROM reorder_rules")->fetchAll();
        foreach ($rules as $rule) {
            $item = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
            $item->execute([(int) $rule['item_id']]);
            $itemRow = $item->fetch();
            if (!$itemRow) continue;
            $balance = reorderBalance($pdo, (int) $rule['item_id'], (float) $itemRow['quantity']);
            if ($balance < (int) $rule['threshold_qty']) {
                $summary['below_threshold']++;
                insertNotification($pdo, "Low stock alert: '{$itemRow['name']}' balance {$balance} below threshold.");
                if (!empty($rule['auto_create_po'])) {
                    $ins = $pdo->prepare("INSERT INTO purchase_orders (item_id, supplier_id, quantity, status) VALUES (?,?,?,'draft') RETURNING id");
                    $ins->execute([(int) $rule['item_id'], (int) $rule['preferred_supplier_id'], (int) $rule['reorder_qty']]);
                    $summary['pos_created']++;
                    require_once __DIR__ . '/../../../src/Helpers/AuditHelper.php';
                    AuditHelper::system($pdo, 'auto_create_po', 'purchase_orders', (int) $ins->fetchColumn(), ['via' => 'cron']);
                }
            }
        }
        $pdo->commit();
        return $summary;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        return ['error' => $e->getMessage()];
    }
}

function reorderBalance($pdo, $itemId, $opening) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity ELSE 0 END),0) AS ti,
                                  COALESCE(SUM(CASE WHEN direction='out' THEN quantity ELSE 0 END),0) AS to,
                                  COUNT(*) AS c FROM stock_movements WHERE item_id = ?");
    $stmt->execute([(int) $itemId]);
    $m = $stmt->fetch();
    return (int) $m['c'] > 0 ? (int) $m['ti'] - (int) $m['to'] : (int) $opening;
}

function insertNotification($pdo, $message) {
    $pdo->prepare("INSERT INTO notifications (message) VALUES (?)")->execute([$message]);
}

function runIrrigation($pdo) {
    $engine = new IrrigationIntelligence($pdo);
    $summary = ['evaluated' => 0, 'created' => 0];
    $fields = $pdo->query("SELECT DISTINCT f.* FROM fields f JOIN irrigation_systems irs ON irs.farm_id = f.farm_id ORDER BY f.id")->fetchAll();
    foreach ($fields as $field) {
        $summary['evaluated']++;
        $crop = $pdo->prepare("SELECT * FROM crops WHERE farm_id = ? ORDER BY planting_date DESC NULLS LAST LIMIT 1");
        $crop->execute([(int) $field['farm_id']]);
        $cropRow = $crop->fetch();
        $need = $cropRow ? (float) $cropRow['water_need_mm_per_week'] : 35.0;
        $rainStmt = $pdo->prepare("SELECT COALESCE(SUM(rainfall_mm),0) AS r FROM weather_records WHERE farm_id=? AND weather_date >= CURRENT_DATE - INTERVAL '7 days'");
        $rainStmt->execute([(int) $field['farm_id']]);
        $rain = (float) $rainStmt->fetch()['r'];
        $deficit = max(0.0, $need - $rain);
        if ($deficit > 0) {
            $liters = round($deficit * ((float) $field['size'] > 0 ? (float) $field['size'] : 1) * 10000, 2);
            $reason = sprintf('Rainfall %.1fmm is %.1fmm below the %.1fmm weekly need for %s. Apply %.0f L.', $rain, $deficit, $need, $cropRow['name'] ?? 'crop', $liters);
            $ins = $pdo->prepare("INSERT INTO irrigation_recommendations (field_id, recommended_date, recommended_liters, reason) VALUES (?, CURRENT_DATE, ?, ?)");
            $ins->execute([(int) $field['id'], $liters, $reason]);
            $summary['created']++;
        }
    }
    return $summary;
}

function runYieldPredictions($pdo) {
    $svc = new YieldPredictionService($pdo);
    $summary = ['predicted' => 0];
    $rows = $pdo->query("SELECT id FROM crops ORDER BY id")->fetchAll();
    foreach ($rows as $row) {
        if ($svc->recalculateData((int) $row['id']) !== null) {
            $summary['predicted']++;
        }
    }
    return $summary;
}
