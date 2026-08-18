<?php
class LivestockModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllAnimals() { return $this->pdo->query("SELECT * FROM livestock")->fetchAll(); }
    public function createAnimal($farm_id, $type, $breed, $dob) {
        $stmt = $this->pdo->prepare("INSERT INTO livestock (farm_id, type, breed, dob) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$farm_id, $type, $breed, $dob]);
    }
}
