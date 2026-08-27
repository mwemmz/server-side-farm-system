<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class StorageModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'storage_records'); }
}
?>
