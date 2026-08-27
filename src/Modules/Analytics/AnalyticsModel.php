<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class AnalyticsModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'analytics_data'); }

    public function getTotalProcurementCost() {
        return $this->pdo->query("SELECT COALESCE(SUM(cost), 0) FROM procurement_records")->fetchColumn();
    }

    public function getTotalSalesAmount() {
        return $this->pdo->query("SELECT COALESCE(SUM(amount), 0) FROM sales_records")->fetchColumn();
    }

    public function getTotalInventoryCount() {
        return $this->pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM inventory")->fetchColumn();
    }
}
?>
