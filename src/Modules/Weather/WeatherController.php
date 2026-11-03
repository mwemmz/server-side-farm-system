<?php
require_once __DIR__ . '/WeatherModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';
require_once __DIR__ . '/../../Services/WeatherForecastService.php';

class WeatherController {
    private WeatherModel $model;
    private $pdo;
    private WeatherForecastService $forecast;

    public function __construct($pdo) {
        $this->pdo      = $pdo;
        $this->model    = new WeatherModel($pdo);
        $this->forecast = new WeatherForecastService($pdo);
    }

    /** Landing page — all farms overview + today's forecast. */
    public function index(): array {
        $farms = $this->pdo->query("SELECT id, name FROM farms ORDER BY name")->fetchAll();
        $allForecasts = $this->forecast->getAllFarmForecasts();
        return ['farms' => $farms, 'allForecasts' => $allForecasts];
    }

    /** Single-farm detailed 7-day forecast. */
    public function forecast(): array {
        $farmId = (int) ($_GET['farm_id'] ?? 0);
        // If no farm specified, use the first one
        if (!$farmId) {
            try {
                $farmId = (int) $this->pdo->query("SELECT id FROM farms ORDER BY id LIMIT 1")->fetchColumn();
            } catch (\Throwable $e) {}
        }

        $farms = $this->pdo->query("SELECT id, name FROM farms ORDER BY name")->fetchAll();
        $data  = $farmId ? $this->forecast->getForecastForFarm($farmId) : [];
        $farm  = null;
        foreach ($farms as $f) {
            if ((int)$f['id'] === $farmId) { $farm = $f; break; }
        }
        return ['forecast' => $data, 'farms' => $farms, 'farm' => $farm, 'farmId' => $farmId];
    }

    /** JSON endpoint — used by the forecast view's JS to refresh without page reload. */
    public function forecastJson(): void {
        header('Content-Type: application/json; charset=UTF-8');
        $farmId = (int) ($_GET['farm_id'] ?? 0);
        $data   = $farmId ? $this->forecast->getForecastForFarm($farmId) : [];
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    /** Legacy: manual weather record entry. */
    public function add(): array {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data   = $_POST;
            $errors = ValidationHelper::validateRequired(['farm_id', 'temperature', 'humidity'], $data);

            if (!isset($errors['farm_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'farms', $data['farm_id'], 'farm');
                if ($e) $errors['farm_id'] = $e;
            }
            if (!isset($errors['temperature'])) {
                $e = ValidationHelper::number($data, 'temperature', ['label' => 'Temperature', 'min' => -50, 'max' => 60]);
                if ($e) $errors['temperature'] = $e;
            }
            if (!isset($errors['humidity'])) {
                $e = ValidationHelper::number($data, 'humidity', ['label' => 'Humidity', 'min' => 0, 'max' => 100]);
                if ($e) $errors['humidity'] = $e;
            }

            if (empty($errors)) {
                $row = [
                    'farm_id'     => (int) $data['farm_id'],
                    'temperature' => $data['temperature'],
                    'humidity'    => $data['humidity'],
                ];
                try {
                    if ($this->model->create($row)) {
                        SessionHelper::setFlash('success', 'Weather record added successfully!');
                        header('Location: index.php?module=Weather&action=forecast&farm_id=' . $row['farm_id']);
                        exit;
                    }
                    $errors['general'] = "Failed to add weather record.";
                } catch (\PDOException $e) {
                    $errors['general'] = "Could not save weather record.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
