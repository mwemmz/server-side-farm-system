<?php
class IrrigationModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllSystems() { return $this->pdo->query("SELECT * FROM irrigation_systems")->fetchAll(); }
    public function lastId() { return (int) $this->pdo->lastInsertId(); }
    public function createSystem($farm_id, $type, $status, $water_source = null) {
        $stmt = $this->pdo->prepare("INSERT INTO irrigation_systems (farm_id, type, status, water_source) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$farm_id, $type, $status, ($water_source !== '' ? $water_source : null)]);
    }
}
