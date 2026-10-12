<?php
class EquipmentModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllEquipment() { return $this->pdo->query("SELECT * FROM equipment")->fetchAll(); }
    public function lastId() { return (int) $this->pdo->lastInsertId(); }
    public function addEquipment($name, $status, $type = null, $farm_id = null, $purchase_date = null) {
        $stmt = $this->pdo->prepare("INSERT INTO equipment (name, maintenance_status, equipment_type, farm_id, purchase_date) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$name, $status, ($type !== '' ? $type : null), ($farm_id ? (int) $farm_id : null), ($purchase_date !== '' ? $purchase_date : null)]);
    }
}
?>
