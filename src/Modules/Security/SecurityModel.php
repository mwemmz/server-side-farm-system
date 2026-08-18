<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class SecurityModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'security_logs'); }
}
?>
