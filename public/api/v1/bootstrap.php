<?php
// API bootstrap for the /api/v1 layer.
// Responsibilities: JSON response shape {success, data, error}, CORS, JWT auth,
// request body parsing, and a standard error helper.
// This file is intended to be required by the front controller, not executed directly.

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../db/migrate.php';       // ensure schema + seed exist
require_once __DIR__ . '/../../../src/Helpers/JwtHelper.php';
require_once __DIR__ . '/../../../src/Helpers/AuditHelper.php';

// CORS for API clients
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/** Send a JSON response in the standard shape and stop. */
function api_send($success, $data = null, $error = null, $status = 200) {
    http_response_code($status);
    echo json_encode(['success' => $success, 'data' => $data, 'error' => $error]);
    exit;
}

/** Shortcut for success responses. */
function api_ok($data = null, $status = 200) {
    api_send(true, $data, null, $status);
}

/** Shortcut for error responses. */
function api_err($error, $status = 400) {
    api_send(false, null, $error, $status);
}

/**
 * Require a valid JWT. Redirects nothing — on failure returns a 401 JSON error.
 * @return array The verified payload {sub, role}.
 */
function api_require_auth() {
    $token = JwtHelper::tokenFromHeader();
    $payload = $token ? JwtHelper::verify($token) : null;
    if (!$payload) {
        api_err('Unauthorized: missing or invalid token', 401);
    }
    return $payload;
}

/** Parse the JSON request body into an associative array. @return array */
function api_body() {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

/** Read the HTTP method (safe lowercase). @return string */
function api_method() {
    return strtolower($_SERVER['REQUEST_METHOD'] ?? 'get');
}
