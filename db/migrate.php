<?php
// Auto-migration script to ensure tables exist
//
// Running the full migration (dozens of schema files, seeds and backfills) on
// every single request was the dominant cause of slow page loads, especially
// against the hosted database. Gate it behind a version marker stored in the
// database itself: once the current version has been applied, subsequent
// requests skip straight through.
$migrationVersion = '16';

try {
    $applied = null;
    try {
        $applied = $pdo->query("SELECT value FROM app_meta WHERE key = 'migration_version'")->fetchColumn();
    } catch (PDOException $e) {
        // app_meta does not exist yet (fresh database) — proceed with migration.
        $applied = null;
    }
    if ($applied === $migrationVersion) {
        return;
    }
} catch (Throwable $e) {
    // Fall through and attempt the migration anyway.
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_meta (key VARCHAR(64) PRIMARY KEY, value TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (key VARCHAR(64) PRIMARY KEY, value TEXT, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema !== false) {
        // Execute the entire schema file
        $pdo->exec($schema);
    }

    $featureSchema = file_get_contents(__DIR__ . '/features_schema.sql');
    if ($featureSchema !== false) {
        $pdo->exec($featureSchema);
    }
    $standoutSchema = file_get_contents(__DIR__ . '/standout_schema.sql');
    if ($standoutSchema !== false) {
        $pdo->exec($standoutSchema);
    }

    // Incremental migrations for existing tables
    $pdo->exec("ALTER TABLE farms ADD COLUMN IF NOT EXISTS latitude DOUBLE PRECISION DEFAULT -15.3875");
    $pdo->exec("ALTER TABLE farms ADD COLUMN IF NOT EXISTS longitude DOUBLE PRECISION DEFAULT 28.3228");

    // Phase 5 Intelligence-layer schema (idempotent)
    $intelSchema = file_get_contents(__DIR__ . '/intelligence_schema.sql');
    if ($intelSchema !== false) {
        $pdo->exec($intelSchema);
    }

    // Employees can be linked to a login so workers get a self-service slice of
    // the HR module (their own leave requests only). One login per employee.
    $pdo->exec("ALTER TABLE employees ADD COLUMN IF NOT EXISTS user_id INTEGER REFERENCES users(id) ON DELETE SET NULL");
    $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_employees_user_id ON employees(user_id) WHERE user_id IS NOT NULL");

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

    // Operations expansion: fields, crops, livestock, equipment, labour,
    // harvest, market and supplier depth (idempotent, additive only).
    $expansionSchema = file_get_contents(__DIR__ . '/expansion_schema.sql');
    if ($expansionSchema !== false) {
        $pdo->exec($expansionSchema);
    }

    // Animal identification: tag numbers, names, sex, status (idempotent)
    $animalsSchema = file_get_contents(__DIR__ . '/animals_schema.sql');
    if ($animalsSchema !== false) {
        $pdo->exec($animalsSchema);
    }

    // Inventory: allow fractional quantities (idempotent)
    $invNum = file_get_contents(__DIR__ . '/inventory_numeric.sql');
    if ($invNum !== false) {
        $pdo->exec($invNum);
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

    // Phase 3 (B4) — one-off, idempotent copy of legacy pest_records into
    // health_incidents. Guarded per-row so re-running never duplicates.
    try {
        $legacy = $pdo->query("SELECT * FROM pest_records")->fetchAll();
        if ($legacy) {
            $copy = $pdo->prepare(
                "INSERT INTO health_incidents
                    (farm_id, subject_type, subject_id, issue_name, issue_kind, severity, detected_date, status, treatment_notes, crop_id, livestock_id)
                 SELECT ?, ?, ?, ?, 'pest', ?, ?, 'open', ?, ?, ?
                 WHERE NOT EXISTS (
                    SELECT 1 FROM health_incidents h
                    WHERE LOWER(h.issue_name) = LOWER(?)
                      AND h.detected_date IS NOT DISTINCT FROM ?
                      AND h.crop_id IS NOT DISTINCT FROM ?
                      AND h.livestock_id IS NOT DISTINCT FROM ?
                 )"
            );
            foreach ($legacy as $r) {
                $cropId = !empty($r['crop_id']) ? (int) $r['crop_id'] : null;
                $animalId = !empty($r['livestock_id']) ? (int) $r['livestock_id'] : null;
                $subjectType = $animalId ? 'livestock' : 'crop';
                $subjectId = $animalId ?: $cropId;
                $copy->execute([
                    !empty($r['farm_id']) ? (int) $r['farm_id'] : null,
                    $subjectType,
                    $subjectId,
                    $r['pest_name'],
                    $r['severity'] ?? null,
                    $r['detected_date'] ?? null,
                    $r['action_taken'] ?? null,
                    $cropId,
                    $animalId,
                    $r['pest_name'],
                    $r['detected_date'] ?? null,
                    $cropId,
                    $animalId,
                ]);
            }
        }
    } catch (PDOException $e) {
        error_log('Health incident migration failed: ' . $e->getMessage());
    }

    // Seed default admin + mock data (idempotent)
    // Run new schemas BEFORE seed so seed.php can populate farm_members etc.
    $connSchema = file_get_contents(__DIR__ . '/connections_schema.sql');
    if ($connSchema !== false) { $pdo->exec($connSchema); }
    $commsSchema = file_get_contents(__DIR__ . '/comms_schema.sql');
    if ($commsSchema !== false) { $pdo->exec($commsSchema); }
    $cpSchema = file_get_contents(__DIR__ . '/credit_passport_schema.sql');
    if ($cpSchema !== false) { $pdo->exec($cpSchema); }
    $trSchema = file_get_contents(__DIR__ . '/traceability_schema.sql');
    if ($trSchema !== false) { $pdo->exec($trSchema); }
    $mpSchema = file_get_contents(__DIR__ . '/marketplace_schema.sql');
    if ($mpSchema !== false) { $pdo->exec($mpSchema); }

    // Trust scores + (re-ensure) app_settings
    $trustSchema = file_get_contents(__DIR__ . '/trust_schema.sql');
    if ($trustSchema !== false) { $pdo->exec($trustSchema); }

    // Widen app_settings key column to 80 chars (was 64)
    try { $pdo->exec("ALTER TABLE app_settings ALTER COLUMN key TYPE VARCHAR(80)"); } catch (\Throwable $e) {}

    require_once __DIR__ . '/seed.php';

    // Seed shared lookup reference data (idempotent)
    require_once __DIR__ . '/seed_lookups.php';

    // (schemas above already applied)

    // Record that this migration version has been applied so subsequent
    // requests can skip the (expensive) migration step entirely.
    $up = $pdo->prepare("INSERT INTO app_meta (key, value) VALUES ('migration_version', ?) ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value");
    $up->execute([$migrationVersion]);
} catch (PDOException $e) {
    // Log the error, but don't block the application if it's already set up
    error_log("Migration failed: " . $e->getMessage());
}
?>
