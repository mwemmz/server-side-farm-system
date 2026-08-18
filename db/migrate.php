<?php
// Auto-migration script to ensure tables exist
try {
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema !== false) {
        // Execute the entire schema file
        $pdo->exec($schema);
    }
} catch (PDOException $e) {
    // Log the error, but don't block the application if it's already set up
    error_log("Migration failed: " . $e->getMessage());
}
?>
