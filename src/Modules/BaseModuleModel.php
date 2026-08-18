<?php
// Simple Base Model for remaining modules
class BaseModuleModel {
    protected $pdo;
    protected $table;
    public function __construct($pdo, $table) { $this->pdo = $pdo; $this->table = $table; }
    public function getAll() { return $this->pdo->query("SELECT * FROM {$this->table}")->fetchAll(); }
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    public function create($data) {
        $keys = array_keys($data);
        $fields = implode(', ', $keys);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO {$this->table} ($fields) VALUES ($placeholders)");
        return $stmt->execute(array_values($data));
    }
    public function update($id, $data) {
        $fields = '';
        foreach ($data as $key => $value) {
            $fields .= "$key = ?, ";
        }
        $fields = rtrim($fields, ', ');
        $stmt = $this->pdo->prepare("UPDATE {$this->table} SET $fields WHERE id = ?");
        $values = array_values($data);
        $values[] = $id;
        return $stmt->execute($values);
    }
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>
