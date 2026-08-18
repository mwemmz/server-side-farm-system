<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class AnalyticsModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'analytics_data'); }
}
?>
