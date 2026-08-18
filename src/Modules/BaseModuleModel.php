<?php
// Simple Base Model for remaining modules
class BaseModuleModel {
    protected $pdo;
    protected $table;
    public function __construct($pdo, $table) { $this->pdo = $pdo; $this->table = $table; }
    public function getAll() { return $this->pdo->query("SELECT * FROM {$this->table}")->fetchAll(); }
    // Add create/update/delete here as needed
}
?>
