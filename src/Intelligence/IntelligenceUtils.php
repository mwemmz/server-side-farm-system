<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Helpers/AuditHelper.php';

/**
 * Shared helpers for the intelligence layer: notifications by role, and small
 * DB utility used across the five features.
 */
class IntelligenceUtils {

    /**
     * Insert a notification row. Returns the new id (or null on failure).
     */
    public static function notify($pdo, $message, $role = null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO notifications (message) VALUES (?)");
            $stmt->execute([$message]);
            return (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("IntelligenceUtils::notify failed: " . $e->getMessage());
            return null;
        }
    }

    /** Audit a system-automated action. */
    public static function auditSystem($pdo, $action, $entityType = null, $entityId = null, $details = null) {
        AuditHelper::system($pdo, $action, $entityType, $entityId, $details);
    }

    /** Round-trip a numeric database value safely. @return float */
    public static function num($val, $default = 0.0) {
        $v = (float) $val;
        return is_nan($v) ? (float) $default : $v;
    }
}
