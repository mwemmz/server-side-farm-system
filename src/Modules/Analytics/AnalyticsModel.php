<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class AnalyticsModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'analytics_data'); }

    public function getTotalProcurementCost() {
        return $this->pdo->query("SELECT SUM(cost) FROM procurement_records")->fetchColumn();
    }

    public function getTotalSalesAmount() {
        return $this->pdo->query("SELECT SUM(amount) FROM sales_records")->fetchColumn();
    }

    public function getTotalInventoryCount() {
        return $this->pdo->query("SELECT SUM(quantity) FROM inventory")->fetchColumn();
    }
}
?>
