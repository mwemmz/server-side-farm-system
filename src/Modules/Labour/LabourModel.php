<?php
require_once __DIR__ . '/../BaseModuleModel.php';

// Example for remaining modules
class LabourModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'labour'); }
}
?>
