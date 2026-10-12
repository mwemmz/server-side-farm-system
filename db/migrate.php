<?php
// Auto-migration script to ensure tables exist
try {
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema !== false) {
        // Execute the entire schema file
        $pdo->exec($schema);
    }

    // Incremental migrations for existing tables
    $pdo->exec("ALTER TABLE farms ADD COLUMN IF NOT EXISTS latitude DOUBLE PRECISION DEFAULT -15.3875");
    $pdo->exec("ALTER TABLE farms ADD COLUMN IF NOT EXISTS longitude DOUBLE PRECISION DEFAULT 28.3228");

    // Phase 5 Intelligence-layer schema (idempotent)
    $intelSchema = file_get_contents(__DIR__ . '/intelligence_schema.sql');
    if ($intelSchema !== false) {
        $pdo->exec($intelSchema);
    }

    // Phase 6 Control-panel schema (idempotent)
    $panelSchema = file_get_contents(__DIR__ . '/panels_schema.sql');
    if ($panelSchema !== false) {
        $pdo->exec($panelSchema);
    }

    // Smart Upgrade reference-data schema (idempotent)
    $smartSchema = file_get_contents(__DIR__ . '/smart_schema.sql');
    if ($smartSchema !== false) {
        $pdo->exec($smartSchema);
    }

    // Phase 3 Operations-depth schema (idempotent)
    $phase3Schema = file_get_contents(__DIR__ . '/phase3_schema.sql');
    if ($phase3Schema !== false) {
        $pdo->exec($phase3Schema);
    }

    // Phase 3 (B2) — one-off, idempotent backfill of blank finance categories.
    // Never overwrites a category the user has already set.
    try {
        $blank = $pdo->query("SELECT id, description FROM finance_records WHERE category IS NULL OR category = ''")->fetchAll();
        if ($blank) {
            $keywords = [
                'seed'      => 'Seeds',
                'fertili'   => 'Fertiliser',
                'labour'    => 'Labour & wages',
                'wage'      => 'Labour & wages',
                'salary'    => 'Labour & wages',
                'fuel'      => 'Fuel',
                'diesel'    => 'Fuel',
                'petrol'    => 'Fuel',
                'vet'       => 'Veterinary & medicines',
                'medicine'  => 'Veterinary & medicines',
                'drug'      => 'Veterinary & medicines',
                'feed'      => 'Animal feed',
            ];
            $upd = $pdo->prepare("UPDATE finance_records SET category = ? WHERE id = ? AND (category IS NULL OR category = '')");
            foreach ($blank as $row) {
                $desc = strtolower((string) $row['description']);
                $cat = 'Uncategorised';
                foreach ($keywords as $needle => $label) {
                    if (strpos($desc, $needle) !== false) { $cat = $label; break; }
                }
                $upd->execute([$cat, (int) $row['id']]);
            }
        }
    } catch (PDOException $e) {
        error_log('Finance category backfill failed: ' . $e->getMessage());
    }

    // Seed default admin + mock data (idempotent)
    require_once __DIR__ . '/seed.php';

    // Seed shared lookup reference data (idempotent)
    require_once __DIR__ . '/seed_lookups.php';
} catch (PDOException $e) {
    // Log the error, but don't block the application if it's already set up
    error_log("Migration failed: " . $e->getMessage());
}
?>