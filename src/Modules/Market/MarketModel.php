<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class MarketModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'market_data'); }
}
?>
