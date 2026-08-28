<?php
require_once __DIR__ . '/../../config/database.php';

/**
 * Audit logging helper. Writes human ("human") and system-automated ("system")
 * decisions to the audit_logs table so they are distinguishable.
 */
class AuditHelper {

    /**
     * Log an audit entry.
     * @param PDO $pdo
     * @param string $source 'human' or 'system'
     * @param string $action e.g. 'auto_create_po'
     * @param string|null $entityType
     * @param int|null $entityId
     * @param mixed $details Serializable detail (array/string) stored as JSON.
     * @param int|null $userId
     * @return void
     */
    public static function log($pdo, $source, $action, $entityType = null, $entityId = null, $details = null, $userId = null) {
        try {
            $detailsJson = is_string($details) ? $details : json_encode($details);
            $stmt = $pdo->prepare(
                "INSERT INTO audit_logs (source, action, entity_type, entity_id, details, user_id)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$source, $action, $entityType, $entityId, $detailsJson, $userId]);
        } catch (PDOException $e) {
            error_log("AuditHelper failed: " . $e->getMessage());
        }
    }

    /** Log an automated (system-initiated) action. */
    public static function system($pdo, $action, $entityType = null, $entityId = null, $details = null) {
        self::log($pdo, 'system', $action, $entityType, $entityId, $details, null);
    }
}
