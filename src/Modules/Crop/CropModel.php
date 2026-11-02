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

    public function lastId() { return (int) $this->pdo->lastInsertId(); }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM crops WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }

    public function updateCrop($id, $farmId, $name, $variety, $plantingDate, $harvestDate) {
        $stmt = $this->pdo->prepare(
            "UPDATE crops SET farm_id=?, name=?, variety=?, planting_date=?, expected_harvest_date=? WHERE id=?"
        );
        return $stmt->execute([$farmId, $name, $variety, $plantingDate, $harvestDate, (int) $id]);
    }

    public function createCrop($farm_id, $name, $variety, $planting_date, $expected_harvest_date) {
        $stmt = $this->pdo->prepare("INSERT INTO crops (farm_id, name, variety, planting_date, expected_harvest_date) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$farm_id, $name, $variety, $planting_date, $expected_harvest_date]);
    }
}
?>
