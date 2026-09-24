<?php
class LivestockModel {
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }
    public function getAllAnimals() { return $this->pdo->query("SELECT * FROM livestock")->fetchAll(); }
    public function createAnimal($farm_id, $type, $breed, $dob) {
        $stmt = $this->pdo->prepare("INSERT INTO livestock (farm_id, type, breed, dob) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$farm_id, $type, $breed, $dob]);
    }
    public function getVaccinations() {
        return $this->pdo->query(
            "SELECT v.*, l.type AS animal_type, l.breed AS animal_breed
             FROM vaccinations v
             JOIN livestock l ON l.id = v.livestock_id
             ORDER BY v.vaccination_date DESC"
        )->fetchAll();
    }
    public function addVaccination($livestock_id, $vaccine_name, $vaccination_date, $next_due_date) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vaccinations (livestock_id, vaccine_name, vaccination_date, next_due_date)
             VALUES (?, ?, ?, ?)"
        );
        return $stmt->execute([$livestock_id, $vaccine_name, $vaccination_date, $next_due_date]);
    }
}