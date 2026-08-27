<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class WeatherModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'weather_records'); }
}
?>
