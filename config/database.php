<?php
require_once __DIR__ . '/env.php';

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
     error_log("DB Connection Error: " . $e->getMessage());
     die("Database connection failed. Please check environment configuration.");
}
?>