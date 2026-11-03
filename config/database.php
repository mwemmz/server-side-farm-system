<?php
require_once __DIR__ . '/env.php';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Prefer a full DATABASE_URL connection string (e.g. from Neon, Supabase, Railway).
    // Falls back to individual DB_HOST / DB_NAME / DB_USER / DB_PASS env vars.
    $databaseUrl = getEnvVar('DATABASE_URL');

    if ($databaseUrl) {
        $parts = parse_url($databaseUrl);
        $host  = $parts['host'] ?? 'localhost';
        $port  = $parts['port'] ?? 5432;
        $db    = ltrim($parts['path'] ?? '/farm_system', '/');
        $user  = rawurldecode($parts['user'] ?? 'postgres');
        $pass  = rawurldecode($parts['pass'] ?? '');

        // Parse query string params — only pass ones PDO actually understands.
        // channel_binding is a Neon-specific libpq param that PHP's PDO does
        // not support and will cause the connection to fail if included.
        $query = $parts['query'] ?? '';
        parse_str($query, $qParams);
        $sslmode = $qParams['sslmode'] ?? 'require';

        $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=$sslmode";
        $pdo = new PDO($dsn, $user, $pass, $options);
    } else {
        $host = getEnvVar('DB_HOST', 'localhost');
        $db   = getEnvVar('DB_NAME', 'farm_system');
        $user = getEnvVar('DB_USER', 'postgres');
        $pass = getEnvVar('DB_PASS', 'password');
        $port = getEnvVar('DB_PORT', '5432');

        $dsn = "pgsql:host=$host;port=$port;dbname=$db;";
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
} catch (\PDOException $e) {
    error_log("DB Connection Error: " . $e->getMessage());
    die("Database connection failed. Please check environment configuration.");
}
?>