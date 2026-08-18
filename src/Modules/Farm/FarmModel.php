<?php
class FarmModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getAllFarms() {
        $stmt = $this->pdo->query("SELECT * FROM farms");
        return $stmt->fetchAll();
    }

    public function createFarm($name, $location) {
        $stmt = $this->pdo->prepare("INSERT INTO farms (name, location) VALUES (?, ?)");
        return $stmt->execute([$name, $location]);
    }
}
?>
