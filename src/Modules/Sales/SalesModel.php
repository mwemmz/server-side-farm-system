<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class SalesModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'sales_records'); }
    public function createSale(array $data): int {
        $stmt = $this->pdo->prepare("INSERT INTO sales_records (customer_name, amount, sale_date, inventory_id, livestock_id, field_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$data['customer_name'], $data['amount'], $data['sale_date'], $data['inventory_id'], $data['livestock_id'], $data['field_id']]);
        return (int) $this->pdo->lastInsertId();
    }
}
?>
