<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class MarketModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'market_data'); }

    public function getAll() {
        return $this->pdo->query("SELECT * FROM market_data ORDER BY market_date DESC, id DESC LIMIT 100")->fetchAll();
    }
}
?>
