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

    public function lastId() { return (int) $this->pdo->lastInsertId(); }

    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM farms WHERE id = ?");
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }

    public function update($id, $data) {
        $stmt = $this->pdo->prepare("UPDATE farms SET name=?, location=? WHERE id=?");
        return $stmt->execute([$data['name'], $data['location'], (int) $id]);
    }

    public function getPdo() { return $this->pdo; }

    public function createFarm($name, $location) {
        $stmt = $this->pdo->prepare("INSERT INTO farms (name, location) VALUES (?, ?)");
        return $stmt->execute([$name, $location]);
    }
}
?>
