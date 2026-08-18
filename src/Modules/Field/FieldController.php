<?php
require_once __DIR__ . '/FieldModel.php';
class FieldController {
    private $model;
    public function __construct($pdo) { $this->model = new FieldModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
