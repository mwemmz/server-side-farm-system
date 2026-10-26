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

    // Animal identification: tag numbers, names, sex, status (idempotent)
    $animalsSchema = file_get_contents(__DIR__ . '/animals_schema.sql');
    if ($animalsSchema !== false) {
        $pdo->exec($animalsSchema);
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
    require_once __DIR__ . '/seed.php';

    // Seed shared lookup reference data (idempotent)
    require_once __DIR__ . '/seed_lookups.php';
} catch (PDOException $e) {
    // Log the error, but don't block the application if it's already set up
    error_log("Migration failed: " . $e->getMessage());
}
?>