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

// ---- AI/BI: Insights recommendations ----
if ($segments[0] === 'insights' && ($segments[1] ?? null) === 'recommendations') {
    require_once __DIR__ . '/../../../src/Intelligence/InsightsEngine.php';
    $engine = new InsightsEngine($pdo);
    $module = $_GET['module'] ?? null;
    return api_ok([
        'recommendations' => $engine->all($module),
        'stats'           => $engine->stats(),
    ]);
}

// ---- AI/BI: Assistant ----
if ($segments[0] === 'assistant' && ($segments[1] ?? null) === 'chat') {
    require_once __DIR__ . '/../../../src/Intelligence/Assistant.php';
    if ($method !== 'post') api_err('Method not allowed', 405);
    $body = api_body();
    $question = $body['message'] ?? '';
    return api_ok((new Assistant($pdo))->answer($question));
}

if (($segments[0] ?? null) === 'assistant' && ($segments[1] ?? null) === 'sessions') {
    require_once __DIR__ . '/../../../src/Intelligence/ChatMemory.php';
    return api_ok((new ChatMemory($pdo))->sessions($payload['sub'] ?? 0));
}

if (($segments[0] ?? null) === 'assistant' && ($segments[1] ?? null) === 'history') {
    require_once __DIR__ . '/../../../src/Intelligence/ChatMemory.php';
    if (!isset($_GET['session_id'])) api_err('session_id required', 400);
    $hist = (new ChatMemory($pdo))->history((int) $_GET['session_id'], $payload['sub'] ?? 0);
    if (!$hist) api_err('Session not found', 404);
    return api_ok($hist);
}

if ($segments[0] === 'assistant' && ($segments[1] ?? null) === 'summary') {
    require_once __DIR__ . '/../../../src/Intelligence/InsightsEngine.php';
    $engine = new InsightsEngine($pdo);
    return api_ok([
        'stats'  => $engine->stats(),
        'top'    => $engine->prioritized(5),
        'tip'    => 'Try POST /api/v1/assistant/chat with {"message":"What should I do this week?"}',
    ]);
}

// ---- Analytics: per-field / per-animal profit (Wave 1A) ----
if ($segments[0] === 'analytics' && ($segments[1] ?? null) === 'profit' && ($segments[2] ?? null) === 'by-field') {
    require_once __DIR__ . '/../../../src/Intelligence/AnalyticsService.php';
    $svc = new AnalyticsService($pdo);
    return api_ok(['items' => $svc->profitByField(), 'range' => $svc->range()]);
}
if ($segments[0] === 'analytics' && ($segments[1] ?? null) === 'profit' && ($segments[2] ?? null) === 'by-animal') {
    require_once __DIR__ . '/../../../src/Intelligence/AnalyticsService.php';
    $svc = new AnalyticsService($pdo);
    return api_ok(['items' => $svc->profitByAnimal(), 'range' => $svc->range()]);
}

// ---- Wave 1B: Farm connections (mutual-accept graph) ----
if ($segments[0] === 'extranet' && $segments[1] === 'farms' && ($segments[2] ?? null) === 'search') {
    require_once __DIR__ . '/../../../src/Services/FarmConnectionService.php';
    $svc = new FarmConnectionService($pdo);
    return api_ok(['farms' => $svc->searchFarms($payload['sub'] ?? 0, $_GET['district'] ?? '', $_GET['q'] ?? '')]);
}
if ($segments[0] === 'extranet' && $segments[1] === 'connections') {
    require_once __DIR__ . '/../../../src/Services/FarmConnectionService.php';
    $svc = new FarmConnectionService($pdo);
    $role = $payload['role'] ?? 'worker';
    if ($method === 'get') {
        return api_ok(['connections' => $svc->listConnections($payload['sub'] ?? 0)]);
    }
    if ($method === 'post' && !isset($segments[2])) {
        $body = api_body();
        try {
            $id = $svc->requestConnection($payload['sub'] ?? 0, (int)($body['target_farm_id'] ?? 0), $role);
            return api_ok(['connection_id' => $id]);
        } catch (Throwable $e) {
            api_err($e->getMessage(), 403);
        }
    }
    if ($method === 'post' && isset($segments[2]) && isset($segments[3])) {
        $connId = (int)$segments[2];
        $action = $segments[3];
        if ($action === 'accept') {
            try { $svc->decide($payload['sub'] ?? 0, $connId, 'accepted', $role); return api_ok(['status'=>'accepted']); }
            catch (Throwable $e) { api_err($e->getMessage(), 403); }
        }
        if ($action === 'decline') {
            try { $svc->decide($payload['sub'] ?? 0, $connId, 'declined', $role); return api_ok(['status'=>'declined']); }
            catch (Throwable $e) { api_err($e->getMessage(), 403); }
        }
    }
    api_err('Unknown connections action', 404);
}

// ---- Wave 1C: Communication Hub - Intranet ----
if ($segments[0] === 'intranet' && $segments[1] === 'conversations') {
    require_once __DIR__ . '/../../../src/Services/CommsService.php';
    $svc = new CommsService($pdo);
    $uid = (int)($payload['sub'] ?? 0);
    if ($method === 'get' && !isset($segments[2])) {
        return api_ok(['conversations' => $svc->listConversations($uid, 'intranet')]);
    }
    if ($method === 'post' && !isset($segments[2])) {
        $body = api_body();
        $id = $svc->createConversation($uid, 'intranet', (string)($body['title'] ?? ''), $body['members'] ?? [], $body['kind'] ?? 'group');
        return api_ok(['conversation_id' => $id]);
    }
    if (isset($segments[2])) {
        $cid = (int)$segments[2];
        if (($segments[3] ?? null) === 'messages') {
            if ($method === 'get') return api_ok(['messages' => $svc->listMessages($uid, $cid, $_GET['since'] ?? null)]);
            if ($method === 'post') {
                $body = api_body();
                $mid = $svc->postMessage($uid, $cid, (string)($body['body'] ?? ''));
                return api_ok(['message_id' => $mid]);
            }
        }
        if (($segments[3] ?? null) === 'read' && $method === 'post') {
            $body = api_body();
            $svc->markRead($uid, $cid, (int)($body['last_message_id'] ?? 0));
            return api_ok(['ok' => true]);
        }
    }
    api_err('Unknown intranet action', 404);
}
if ($segments[0] === 'intranet' && $segments[1] === 'broadcast' && $method === 'post') {
    require_once __DIR__ . '/../../../src/Services/CommsService.php';
    $svc = new CommsService($pdo);
    $body = api_body();
    try {
        $id = $svc->broadcast($payload['sub'] ?? 0, $payload['role'] ?? 'worker', (string)($body['title'] ?? 'Broadcast'), (string)($body['body'] ?? ''));
        return api_ok(['conversation_id' => $id]);
    } catch (Throwable $e) { api_err($e->getMessage(), 403); }
}

// ---- Wave 2A: Extranet comms ----
if ($segments[0] === 'extranet' && $segments[1] === 'conversations') {
    require_once __DIR__ . '/../../../src/Services/CommsService.php';
    $svc = new CommsService($pdo);
    $uid = (int)($payload['sub'] ?? 0);
    if ($method === 'get' && !isset($segments[2])) {
        return api_ok(['conversations' => $svc->listConversations($uid, 'extranet')]);
    }
    if ($method === 'post' && !isset($segments[2])) {
        $body = api_body();
        try {
            $id = $svc->createConversation($uid, 'extranet', (string)($body['title'] ?? ''), $body['members'] ?? [], $body['kind'] ?? 'group');
            return api_ok(['conversation_id' => $id]);
        } catch (Throwable $e) { api_err($e->getMessage(), 403); }
    }
    if (isset($segments[2])) {
        $cid = (int)$segments[2];
        if (($segments[3] ?? null) === 'messages') {
            if ($method === 'get') return api_ok(['messages' => $svc->listMessages($uid, $cid, $_GET['since'] ?? null)]);
            if ($method === 'post') {
                $body = api_body();
                $mid = $svc->postMessage($uid, $cid, (string)($body['body'] ?? ''));
                return api_ok(['message_id' => $mid]);
            }
        }
    }
    api_err('Unknown extranet action', 404);
}

// ---- Wave 2A: message reports / admin remove ----
if ($segments[0] === 'messages' && isset($segments[1]) && ($segments[2] ?? null) === 'report' && $method === 'post') {
    require_once __DIR__ . '/../../../src/Services/CommsService.php';
    $svc = new CommsService($pdo);
    $body = api_body();
    $id = $svc->reportMessage($payload['sub'] ?? 0, (int)$segments[1], (string)($body['reason'] ?? ''));
    return api_ok(['report_id' => $id]);
}
if ($segments[0] === 'admin' && $segments[1] === 'messages' && isset($segments[2]) && ($segments[3] ?? null) === 'remove' && $method === 'post') {
    if (($payload['role'] ?? '') !== 'admin') api_err('Admin only', 403);
    require_once __DIR__ . '/../../../src/Services/CommsService.php';
    $svc = new CommsService($pdo);
    $svc->removeMessage($payload['sub'] ?? 0, (int)$segments[2]);
    return api_ok(['removed' => true]);
}

// ---- Wave 2B: Farm Credit Passport ----
if ($segments[0] === 'credit-passport' && ($segments[1] ?? null) === 'me' && $method === 'get') {
    require_once __DIR__ . '/../../../src/Services/PracticeScoringService.php';
    $svc = new PracticeScoringService($pdo);
    return api_ok($svc->compute($payload['sub'] ?? 0));
}
if ($segments[0] === 'credit-passport' && ($segments[1] ?? null) === 'share' && $method === 'post' && !isset($segments[2])) {
    require_once __DIR__ . '/../../../src/Services/PracticeScoringService.php';
    $svc = new PracticeScoringService($pdo);
    $body = api_body();
    $res = $svc->createShare($payload['sub'] ?? 0, $body['scope'] ?? [], (int)($body['expires_days'] ?? 30));
    return api_ok($res);
}
if ($segments[0] === 'credit-passport' && ($segments[1] ?? null) === 'share' && isset($segments[2]) && ($segments[3] ?? null) === 'revoke' && $method === 'post') {
    require_once __DIR__ . '/../../../src/Services/PracticeScoringService.php';
    $svc = new PracticeScoringService($pdo);
    $svc->revokeShare($payload['sub'] ?? 0, (int)$segments[2]);
    return api_ok(['revoked' => true]);
}
if ($segments[0] === 'credit-passport' && ($segments[1] ?? null) === 'history' && $method === 'get') {
    require_once __DIR__ . '/../../../src/Services/PracticeScoringService.php';
    $svc = new PracticeScoringService($pdo);
    return api_ok(['history' => $svc->history($payload['sub'] ?? 0, (int)($_GET['days'] ?? 90))]);
}

// ---- Wave 3A: Farm-to-market traceability ----
if ($segments[0] === 'traceability' && $segments[1] === 'batches') {
    require_once __DIR__ . '/../../../src/Services/TraceabilityService.php';
    $svc = new TraceabilityService($pdo);
    if ($method === 'post' && !isset($segments[2])) {
        $body = api_body();
        try {
            $res = $svc->createBatch($body, $payload['sub'] ?? 0);
            return api_ok($res);
        } catch (Throwable $e) { api_err($e->getMessage(), 422); }
    }
    if (isset($segments[2]) && ($segments[3] ?? null) === 'events' && $method === 'post') {
        $body = api_body();
        try {
            $id = $svc->addEvent((int)$segments[2], $body, $payload['sub'] ?? 0);
            return api_ok(['event_id' => $id]);
        } catch (Throwable $e) { api_err($e->getMessage(), 422); }
    }
    if (isset($segments[2]) && !isset($segments[3])) {
        $b = $svc->getBatch((int)$segments[2]);
        if (!$b) api_err('Not found', 404);
        return api_ok($b);
    }
    api_err('Unknown traceability action', 404);
}

// ---- Wave 3B: AI summarise / draft reply ----
if ($segments[0] === 'assistant' && ($segments[1] ?? null) === 'summarise' && isset($segments[2]) && $method === 'post') {
    require_once __DIR__ . '/../../../src/Intelligence/Assistant.php';
    return api_ok((new Assistant($pdo))->summarizeConversation((int)$segments[2], $payload['sub'] ?? 0));
}
if ($segments[0] === 'assistant' && ($segments[1] ?? null) === 'draft-reply' && isset($segments[2]) && $method === 'post') {
    require_once __DIR__ . '/../../../src/Intelligence/Assistant.php';
    return api_ok((new Assistant($pdo))->draftReply((int)$segments[2], $payload['sub'] ?? 0));
}

// ---- Wave 4B: planting what-if ----
if ($segments[0] === 'planner' && ($segments[1] ?? null) === 'planting-what-if' && $method === 'post') {
    require_once __DIR__ . '/../../../src/Services/PlantingPlannerService.php';
    try {
        return api_ok((new PlantingPlannerService($pdo))->plantingWhatIf(api_body()));
    } catch (Throwable $e) { api_err($e->getMessage(), 422); }
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
