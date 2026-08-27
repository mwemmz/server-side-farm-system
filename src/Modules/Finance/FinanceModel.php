<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class FinanceModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'finance_records'); }
}
?>
