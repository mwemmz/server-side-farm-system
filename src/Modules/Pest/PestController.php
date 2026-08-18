<?php
require_once __DIR__ . '/PestModel.php';
class PestController {
    private $model;
    public function __construct($pdo) { $this->model = new PestModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
