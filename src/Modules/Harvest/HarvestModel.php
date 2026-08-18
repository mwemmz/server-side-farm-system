<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class HarvestModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'harvest_records'); }
}
?>
