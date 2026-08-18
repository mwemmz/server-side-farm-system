<?php
class CropModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAllCrops() {
        $stmt = $this->pdo->query("SELECT * FROM crops");
        return $stmt->fetchAll();
    }

    public function createCrop($farm_id, $name, $variety, $planting_date, $expected_harvest_date) {
        $stmt = $this->pdo->prepare("INSERT INTO crops (farm_id, name, variety, planting_date, expected_harvest_date) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$farm_id, $name, $variety, $planting_date, $expected_harvest_date]);
    }
}
?>
