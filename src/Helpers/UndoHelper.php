<?php
class UndoHelper {
    public static function stash($pdo, $table, $row, $module, $redirect) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        unset($row['id']);
        $_SESSION['last_delete'] = [
            'table' => $table,
            'row' => $row,
            'module' => $module,
            'redirect' => $redirect,
            'ts' => time(),
        ];
    }
    public static function available() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $u = $_SESSION['last_delete'] ?? null;
        if (!is_array($u)) return false;
        if (time() - (int)($u['ts'] ?? 0) > 300) return false;
        return true;
    }
    public static function restore($pdo) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $u = $_SESSION['last_delete'] ?? null;
        if (!is_array($u) || time() - (int)($u['ts'] ?? 0) > 300) return [false, 'Nothing to undo (or window expired).'];
        $table = preg_replace('/[^a-z_]/', '', (string)$u['table']);
        $row = (array)($u['row'] ?? []);
        if (empty($row)) return [false, 'Nothing to restore.'];
        try {
            $cols = array_keys($row);
            $sql = "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")";
            $st = $pdo->prepare($sql);
            $st->execute(array_values($row));
            unset($_SESSION['last_delete']);
            require_once __DIR__ . '/AuditHelper.php';
            AuditHelper::log($pdo, 'human', 'generic_restore', $table, null, ['module' => $u['module'] ?? ''], (int)($_SESSION['user_id'] ?? 0));
            return [true, 'Record restored.'];
        } catch (PDOException $e) {
            error_log('Undo restore failed: ' . $e->getMessage());
            return [false, 'Could not restore record.'];
        }
    }
    public static function clear() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        unset($_SESSION['last_delete']);
    }
}
?>