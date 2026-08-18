<?php
class EquipmentModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllEquipment() { return $this->pdo->query("SELECT * FROM equipment")->fetchAll(); }
    public function addEquipment($name, $status) {
        $stmt = $this->pdo->prepare("INSERT INTO equipment (name, maintenance_status) VALUES (?, ?)");
        return $stmt->execute([$name, $status]);
    }
}
?>
