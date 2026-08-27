<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class SecurityModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'security_logs'); }
    public function getUserByUsername($username) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
}
?>
