<?php
// Simple Base Model for remaining modules
class BaseModuleModel {
    protected $pdo;
    protected $table;
    public function __construct($pdo, $table) { $this->pdo = $pdo; $this->table = $table; }
    public function lastId() { return (int) $this->pdo->lastInsertId(); }
    public function getAll() { return $this->pdo->query("SELECT * FROM {$this->table}")->fetchAll(); }
    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("{$this->table}: getById failed: " . $e->getMessage());
            return null;
        }
    }
    public function create($data) {
        try {
            $keys = array_keys($data);
            $fields = implode(', ', $keys);
            $placeholders = implode(', ', array_fill(0, count($keys), '?'));
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table} ($fields) VALUES ($placeholders)");
            return $stmt->execute(array_values($data));
        } catch (PDOException $e) {
            error_log("{$this->table}: create failed: " . $e->getMessage());
            return false;
        }
    }
    public function update($id, $data) {
        try {
            $fields = '';
            foreach ($data as $key => $value) {
                $fields .= "$key = ?, ";
            }
            $fields = rtrim($fields, ', ');
            $stmt = $this->pdo->prepare("UPDATE {$this->table} SET $fields WHERE id = ?");
            $values = array_values($data);
            $values[] = $id;
            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("{$this->table}: update failed: " . $e->getMessage());
            return false;
        }
    }
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            error_log("{$this->table}: delete failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
