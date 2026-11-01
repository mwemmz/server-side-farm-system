<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class SecurityModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'security_logs'); }
    public function getUserByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    public function logEvent($userId, $action) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO security_logs (user_id, action) VALUES (?, ?)");
            return $stmt->execute([$userId ?: null, $action]);
        } catch (PDOException $e) {
            error_log('security log failed: ' . $e->getMessage());
            return false;
        }
    }
    public function recentLogs($limit = 200) {
        $limit = (int) $limit;
        return $this->pdo->query(
            "SELECT sl.*, u.username FROM security_logs sl
             LEFT JOIN users u ON u.id = sl.user_id
             ORDER BY sl.log_time DESC LIMIT $limit"
        )->fetchAll();
    }
    public function users() {
        return $this->pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY id")->fetchAll();
    }
    public function createUser($username, $email, $password, $role) {
        $st = $this->pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)");
        return $st->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    }
    public function updatePassword($id, $password) {
        $st = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        return $st->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }
    public function auditLogs($limit = 200) {
        $limit = (int) $limit;
        return $this->pdo->query(
            "SELECT al.*, u.username FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC LIMIT $limit"
        )->fetchAll();
    }
}
?>
