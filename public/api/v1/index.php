<?php
// /api/v1 front controller.
// Dispatches RESTful routes to the intelligence-layer handlers.
// All endpoints return the standard JSON shape: { "success", "data", "error" }.

require_once __DIR__ . '/bootstrap.php';

// --- Optional: public token endpoint (login -> JWT) for API clients ---
// POST /api/v1/auth/token   body: { username, password }
$fullPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$normPath = str_replace(['/index.php', '/api/v1'], '', $fullPath);
$normPath = trim($normPath, '/');
if (preg_match('#^auth/token$#', $normPath)) {
    // Not behind the auth gate
    tokenEndpoint();
}

$payload = api_require_auth();

// Parse the path relative to /api/v1/
$segments = $normPath === '' ? [''] : explode('/', $normPath);
$method = api_method();

// ---- Feature 1: Reorder ----
if ($segments[0] === 'reorder-rules') {
    require_once __DIR__ . '/../../../src/Intelligence/ReorderEngine.php';
    $engine = new ReorderEngine($pdo);
    return $engine->handleRules($method, isset($segments[1]) ? (int) $segments[1] : null);
}

if ($segments[0] === 'inventory-items' && isset($segments[2]) && $segments[2] === 'balance') {
    require_once __DIR__ . '/../../../src/Intelligence/ReorderEngine.php';
    $engine = new ReorderEngine($pdo);
    return $engine->balance((int) $segments[1]);
}

if ($segments[0] === 'reorder-check' && ($segments[1] ?? null) === 'run') {
    require_once __DIR__ . '/../../../src/Intelligence/ReorderEngine.php';
    $engine = new ReorderEngine($pdo);
    return $engine->runCronLogic();
}

// ---- Feature 2: Irrigation ----
if ($segments[0] === 'irrigation-recommendations' && ($segments[1] ?? null) === 'run') {
    require_once __DIR__ . '/../../../src/Intelligence/IrrigationIntelligence.php';
    $engine = new IrrigationIntelligence($pdo);
    return $engine->runDailyJob();
}

if ($segments[0] === 'irrigation-recommendations' && isset($segments[1])) {
    require_once __DIR__ . '/../../../src/Intelligence/IrrigationIntelligence.php';
    $engine = new IrrigationIntelligence($pdo);
    $id = (int) $segments[1];
    $action = $segments[2] ?? null;
    if ($action === 'accept')   return $engine->accept($id, $payload['sub'] ?? null);
    if ($action === 'dismiss')  return $engine->dismiss($id, $payload['sub'] ?? null);
    api_err('Unknown irrigation-recommendations action', 404);
}

if ($segments[0] === 'fields' && isset($segments[2]) && $segments[2] === 'irrigation-recommendations') {
    require_once __DIR__ . '/../../../src/Intelligence/IrrigationIntelligence.php';
    $engine = new IrrigationIntelligence($pdo);
    return $engine->listForField((int) $segments[1]);
}

// ---- Feature 3: Yield prediction ----
if ($segments[0] === 'plantings' && isset($segments[2])) {
    require_once __DIR__ . '/../../../src/Intelligence/YieldPredictionService.php';
    $svc = new YieldPredictionService($pdo);
    $cropId = (int) $segments[1];
    // /plantings/{id}/yield-prediction          GET
    // /plantings/{id}/yield-prediction/recalculate   POST
    if ($segments[2] === 'yield-prediction' || $segments[2] === 'recalculate') {
        if (($segments[3] ?? null) === 'recalculate' || $segments[2] === 'recalculate') {
            return $svc->recalculate($cropId);
        }
        if ($method === 'get')  return $svc->get($cropId);
        if ($method === 'post') return $svc->recalculate($cropId);
        api_err('Method not allowed', 405);
    }
    api_err('Unknown plantings action', 404);
}

// ---- Feature 4: Sell recommendation ----
if ($segments[0] === 'stored-produce' && isset($segments[2]) && $segments[2] === 'sell-recommendation') {
    require_once __DIR__ . '/../../../src/Intelligence/SellRecommendationService.php';
    return (new SellRecommendationService($pdo))->forProduce((int) $segments[1]);
}

if ($segments[0] === 'sell-recommendations' && ($segments[1] ?? null) === 'run') {
    require_once __DIR__ . '/../../../src/Intelligence/SellRecommendationService.php';
    return (new SellRecommendationService($pdo))->runBatch();
}

// ---- Feature 5: Harvest approval ----
if ($segments[0] === 'harvests') {
    require_once __DIR__ . '/../../../src/Intelligence/HarvestApproval.php';
    $svc = new HarvestApproval($pdo);
    if (isset($segments[1])) {
        $id = (int) $segments[1];
        $action = $segments[2] ?? null;
        if ($action === 'approve') return $svc->approve($id, $payload);
        if ($action === 'reject')  return $svc->reject($id, $payload, api_body());
        api_err('Unknown harvest action', 404);
    }
    // GET /api/v1/harvests?status=submitted
    return $svc->queue(($_GET['status'] ?? 'submitted'));
}

// ---- Phase 6: Control-panel sensors (generic) ----
if ($segments[0] === 'sensors' && isset($segments[2])) {
    require_once __DIR__ . '/../../../src/Intelligence/SensorSimulator.php';
    $sim = new SensorSimulator($pdo);
    $type = urldecode($segments[1]);
    $id = (int) $segments[2];
    // GET /sensors/{type}/{id}            -> tick + snapshot
    // GET /sensors/{type}/{id}/history    -> time series (optional ?sensor=key&range=seconds)
    if (($segments[3] ?? null) === 'history') {
        $sim->tick($type, $id, []);
        $sensorKey = $_GET['sensor'] ?? null;
        $range = isset($_GET['range']) ? (int) $_GET['range'] : null;
        return api_ok($sim->history($type, $id, $sensorKey, $range));
    }
    $sim->tick($type, $id, []);
    return api_ok(['type' => $type, 'id' => $id, 'sensors' => $sim->snapshot($type, $id)]);
}

// ---- Phase 6: Irrigation panel (flagship) ----
if ($segments[0] === 'irrigation-systems' && isset($segments[1])) {
    require_once __DIR__ . '/../../../src/Intelligence/Panels/IrrigationPanelService.php';
    $svc = new IrrigationPanelService($pdo);
    $id = (int) $segments[1];
    $action = $segments[2] ?? null;
    if ($action === 'panel') return api_ok($svc->panel($id));
    if ($action === 'pump') {
        if ($method !== 'post') api_err('Method not allowed', 405);
        $body = api_body();
        return api_ok($svc->setPump($id, !empty($body['on'])));
    }
    if ($action === 'refill') {
        if ($method !== 'post') api_err('Method not allowed', 405);
        return api_ok($svc->refill($id));
    }
    api_err('Unknown irrigation-systems action', 404);
}

// ---- Phase 6: Storage facility panel ----
if ($segments[0] === 'storage-facilities' && ($segments[2] ?? null) === 'panel') {
    require_once __DIR__ . '/../../../src/Intelligence/Panels/StoragePanelService.php';
    return api_ok((new StoragePanelService($pdo))->panel((int) $segments[1]));
}

// ---- Phase 6: Equipment panel ----
if ($segments[0] === 'equipment' && ($segments[2] ?? null) === 'panel') {
    require_once __DIR__ . '/../../../src/Intelligence/Panels/EquipmentPanelService.php';
    return api_ok((new EquipmentPanelService($pdo))->panel((int) $segments[1]));
}
if ($segments[0] === 'equipment' && ($segments[2] ?? null) === 'run') {
    require_once __DIR__ . '/../../../src/Intelligence/Panels/EquipmentPanelService.php';
    if ($method !== 'post') api_err('Method not allowed', 405);
    $body = api_body();
    return api_ok((new EquipmentPanelService($pdo))->setRunning((int) $segments[1], !empty($body['running'])));
}

// ---- Phase 6: Livestock panel (farm-level) ----
if ($segments[0] === 'livestock' && ($segments[1] ?? null) === 'panel') {
    require_once __DIR__ . '/../../../src/Intelligence/Panels/LivestockPanelService.php';
    return api_ok((new LivestockPanelService($pdo))->panel($_GET['farm_id'] ?? null));
}

// ---- Phase 6: Weather panel ----
if ($segments[0] === 'farms' && ($segments[2] ?? null) === 'weather' && ($segments[3] ?? null) === 'panel') {
    require_once __DIR__ . '/../../../src/Intelligence/Panels/WeatherPanelService.php';
    return api_ok((new WeatherPanelService($pdo))->panel((int) $segments[1]));
}

// ---- Phase 6: Financial panel ----
if ($segments[0] === 'financials' && ($segments[1] ?? null) === 'panel') {
    require_once __DIR__ . '/../../../src/Intelligence/Panels/FinancialPanelService.php';
    return api_ok((new FinancialPanelService($pdo))->panel($_GET['farm_id'] ?? null));
}

api_err('Route not found', 404);

// The token endpoint is defined as a function for clarity.
function tokenEndpoint() {
    require_once __DIR__ . '/../../../config/database.php';
    $body = api_body();
    $username = $body['username'] ?? '';
    $password = $body['password'] ?? '';
    $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM users WHERE username = ? AND password_hash IS NOT NULL");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        api_err('Invalid credentials', 401);
    }
    $token = JwtHelper::encode((int) $user['id'], $user['role']);
    api_ok([
        'token'       => $token,
        'token_type'  => 'Bearer',
        'expires_in'  => 3600,
        'user'        => ['id' => (int) $user['id'], 'username' => $user['username'], 'role' => $user['role']],
    ]);
}
