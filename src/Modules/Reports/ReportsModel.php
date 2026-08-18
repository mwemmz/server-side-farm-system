<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class ReportsModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'reports'); }
}
?>
