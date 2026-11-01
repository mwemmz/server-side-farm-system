<?php
/**
 * Static consistency check for the metadata-driven Ops engine.
 *
 * Runs without a database: it parses the idempotent SQL files under db/ and
 * verifies that every table/column OpsRegistry exposes actually exists in the
 * schema. This catches the most common way the engine can break — a field
 * added to the registry (or a table renamed in SQL) without a matching
 * migration.
 *
 * Usage: php tests/ops_registry_check.php
 * Exit code 0 = consistent, 1 = problems found.
 */

require_once __DIR__ . '/../src/Modules/Ops/OpsRegistry.php';

$dbDir = realpath(__DIR__ . '/../db');
if ($dbDir === false) {
    fwrite(STDERR, "db/ directory not found\n");
    exit(1);
}

/** Normalise an SQL identifier: lower-case, strip quotes/schema prefix. */
function ident(string $name): string
{
    $name = strtolower(trim($name));
    $name = str_replace(['"', '`', '[', ']'], '', $name);
    if (strpos($name, '.') !== false) {
        $parts = explode('.', $name);
        $name = end($parts);
    }
    return $name;
}

/** @return array<string,array<string,bool>> table => set(column) */
function parseSchema(string $dbDir): array
{
    $tables = [];
    $files = glob($dbDir . '/*.sql') ?: [];
    sort($files);

    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) continue;

        // CREATE TABLE [IF NOT EXISTS] name ( ... );
        if (preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?([A-Za-z0-9_."`\[\]]+)\s*\((.*?)\)\s*;/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $table = ident($m[1]);
                if (!isset($tables[$table])) $tables[$table] = [];
                foreach (preg_split('/\r?\n/', $m[2]) as $line) {
                    $line = trim($line);
                    if ($line === '') continue;
                    if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\b/', $line, $col)) continue;
                    $candidate = strtolower($col[1]);
                    // Skip table-level constraints and keywords.
                    if (in_array($candidate, [
                        'constraint', 'primary', 'unique', 'foreign', 'check', 'key',
                        'index', 'exclude', 'like', 'inherits',
                    ], true)) {
                        continue;
                    }
                    $tables[$table][$candidate] = true;
                }
            }
        }

        // ALTER TABLE name ADD COLUMN [IF NOT EXISTS] column ...
        if (preg_match_all(
            '/ALTER\s+TABLE\s+(?:ONLY\s+)?([A-Za-z0-9_."`\[\]]+)\s+ADD\s+COLUMN\s+(?:IF\s+NOT\s+EXISTS\s+)?([A-Za-z_][A-Za-z0-9_]*)/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $table = ident($m[1]);
                if (!isset($tables[$table])) $tables[$table] = [];
                $tables[$table][strtolower($m[2])] = true;
            }
        }
    }

    return $tables;
}

$schema = parseSchema($dbDir);
$problems = [];
$checkedTables = 0;
$checkedColumns = 0;

foreach (OpsRegistry::all() as $key => $def) {
    $table = ident((string) ($def['table'] ?? ''));
    if ($table === '') {
        $problems[] = "Registry '{$key}' has no table.";
        continue;
    }
    if (!isset($schema[$table])) {
        $problems[] = "Registry '{$key}': table '{$table}' not found in db/*.sql.";
        continue;
    }
    $checkedTables++;

    foreach ((array) ($def['fields'] ?? []) as $col => $field) {
        $checkedColumns++;
        if (!isset($schema[$table][ident((string) $col)])) {
            $problems[] = "Registry '{$key}': column '{$table}.{$col}' not found in db/*.sql.";
        }
        if (($field['type'] ?? '') === 'fk') {
            $fkTable = ident((string) ($field['table'] ?? ''));
            if ($fkTable === '' || !isset($schema[$fkTable])) {
                $problems[] = "Registry '{$key}.{$col}': FK table '{$fkTable}' not found in db/*.sql.";
            }
        }
    }
}

if ($problems) {
    fwrite(STDERR, "Ops registry/schema check FAILED:\n");
    foreach ($problems as $p) {
        fwrite(STDERR, "  - {$p}\n");
    }
    fwrite(STDERR, sprintf(
        "\n%d record types, %d columns checked; %d problem(s).\n",
        count(OpsRegistry::all()),
        $checkedColumns,
        count($problems)
    ));
    exit(1);
}

printf(
    "Ops registry/schema check passed: %d record types, %d columns verified against db/*.sql.\n",
    count(OpsRegistry::all()),
    $checkedColumns
);
exit(0);
