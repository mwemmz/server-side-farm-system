<?php
require_once __DIR__ . '/SecurityModel.php';
class SecurityController {
    private $model;
    public function __construct($pdo) { $this->model = new SecurityModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
