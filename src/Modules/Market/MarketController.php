<?php
require_once __DIR__ . '/MarketModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class MarketController {
    private $model;
    public function __construct($pdo) { $this->model = new MarketModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $fields = ['crop_name', 'price', 'market_date'];
            $errors = ValidationHelper::validateRequired($fields, $data);

            if (!isset($errors['market_date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'market_date', 'Market date');
                if ($e) $errors['market_date'] = $e;
            }
            if (!isset($errors['price'])) {
                $e = ValidationHelper::number($data, 'price', ['label' => 'Price', 'min' => 0]);
                if ($e) $errors['price'] = $e;
            }

            if (empty($errors)) {
                $row = array_intersect_key($data, array_flip(['crop_name', 'price', 'market_date']));
                try {
                    if ($this->model->create($row)) {
                        SessionHelper::setFlash('success', 'Market data added successfully!');
                        SessionHelper::flashCreated('Market', $this->model->lastId(), trim($data['crop_name']), ['Price (K/kg)' => $data['price']]);
                        header('Location: index.php?module=Market&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add market data.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save market data. Please try again.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }
}
?>
