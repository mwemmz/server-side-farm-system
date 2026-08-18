<?php
// Function to get config from System Environment Variables (set in Render Dashboard)
// or fallback to a local .env file for development
function getEnvVar($key, $default = null) {
    // 1. Try system environment variable first
    $value = getenv($key);
    if ($value !== false) return $value;

    // 2. Fallback to local .env file (for local development)
    static $env = null;
    if ($env === null) {
        $path = __DIR__ . '/../.env';
        if (file_exists($path)) {
            $env = parse_ini_file($path);
        } else {
            $env = [];
        }
    }
    return $env[$key] ?? $default;
}

$host = getEnvVar('DB_HOST', 'localhost');
$db   = getEnvVar('DB_NAME', 'farm_system');
$user = getEnvVar('DB_USER', 'postgres');
$pass = getEnvVar('DB_PASS', 'password');
$port = getEnvVar('DB_PORT', '5432');

$dsn = "pgsql:host=$host;port=$port;dbname=$db;";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Log the error internally and die with a simple message
     error_log("DB Connection Error: " . $e->getMessage());
     die("Database connection failed. Please check environment configuration.");
}
?>
