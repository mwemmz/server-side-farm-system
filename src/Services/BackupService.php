<?php

class BackupService
{
    public static function exportJson(PDO $pdo): string
    {
        $tables = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
        $backup = ['format' => 'ffms-json', 'created_at' => gmdate('c'), 'tables' => []];
        foreach ($tables as $table) {
            if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table)) {
                continue;
            }
            $backup['tables'][$table] = $pdo->query('SELECT * FROM "' . $table . '"')->fetchAll();
        }
        return json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
