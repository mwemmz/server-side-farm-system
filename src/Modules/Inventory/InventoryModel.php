<?php
class InventoryModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllItems() { return $this->pdo->query("SELECT * FROM inventory")->fetchAll(); }
    public function addItem($name, $quantity, $unit) {
        $stmt = $this->pdo->prepare("INSERT INTO inventory (name, quantity, unit) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $quantity, $unit]);
    }
}
?>
