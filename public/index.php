<?php
require_once __DIR__ . '/../config/database.php';

if ($pdo) {
    echo "System is operational! Database connected.";
} else {
    echo "System is operational! Database connection failed.";
}
?>
