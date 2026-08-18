<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class SalesModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'sales_records'); }
}
?>
