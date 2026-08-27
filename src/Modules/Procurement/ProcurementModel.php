<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class ProcurementModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'procurement_records'); }
}
?>
