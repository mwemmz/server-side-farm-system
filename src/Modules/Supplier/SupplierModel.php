<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class SupplierModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'suppliers'); }
}
?>
