<?php
require_once __DIR__ . '/NotificationsModel.php';
class NotificationsController {
    private $model;
    public function __construct($pdo) { $this->model = new NotificationsModel($pdo); }
    public function index() { return $this->model->getAll(); }
}
?>
