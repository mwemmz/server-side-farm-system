<?php
function getEnvVar($key, $default = null) {
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
     die("Database connection failed: " . $e->getMessage());
}
?>
