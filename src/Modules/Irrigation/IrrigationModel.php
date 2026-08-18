<?php
class IrrigationModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllSystems() { return $this->pdo->query("SELECT * FROM irrigation_systems")->fetchAll(); }
    public function createSystem($farm_id, $type, $status) {
        $stmt = $this->pdo->prepare("INSERT INTO irrigation_systems (farm_id, type, status) VALUES (?, ?, ?)");
        return $stmt->execute([$farm_id, $type, $status]);
    }
}
