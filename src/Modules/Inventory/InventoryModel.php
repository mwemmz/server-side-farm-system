<?php
class InventoryModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllItems() { return $this->pdo->query("SELECT * FROM inventory")->fetchAll(); }
    public function lastId() { return (int) $this->pdo->lastInsertId(); }
    public function addItem($name, $quantity, $unit, $category = null) {
        $stmt = $this->pdo->prepare("INSERT INTO inventory (name, quantity, unit, category) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$name, $quantity, $unit, ($category !== '' ? $category : null)]);
    }
}
?>
