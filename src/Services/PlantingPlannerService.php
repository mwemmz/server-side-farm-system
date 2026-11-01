<?php

class PlantingPlannerService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function plan(array $input): array
    {
        $area = filter_var($input['area'] ?? null, FILTER_VALIDATE_FLOAT);
        $inputCost = filter_var($input['input_cost'] ?? null, FILTER_VALIDATE_FLOAT);
        $forecast = filter_var($input['price_forecast'] ?? null, FILTER_VALIDATE_FLOAT);
        $yield = filter_var($input['expected_yield'] ?? null, FILTER_VALIDATE_FLOAT);
        $month = filter_var($input['plant_month'] ?? null, FILTER_VALIDATE_INT);
        if ($area === false || $area <= 0 || $inputCost === false || $inputCost < 0 || $forecast === false || $forecast < 0 || $yield === false || $yield < 0 || $month < 1 || $month > 12) {
            throw new InvalidArgumentException('Area, costs, yield, price, and month must be valid non-negative values.');
        }
        $weather = $this->pdo->prepare("SELECT AVG(rainfall_mm) AS rainfall, AVG(temperature) AS temperature FROM weather_records WHERE EXTRACT(MONTH FROM weather_date)=?");
        $weather->execute([$month]);
        $w = $weather->fetch() ?: [];
        $revenue = $area * $yield * $forecast;
        $cost = $area * $inputCost;
        $risk = ((float) ($w['rainfall'] ?? 0) < 20 ? 'Higher water risk; plan irrigation.' : 'Weather indicators are within the available historical range.');
        return ['assumptions' => ['area' => $area, 'yield' => $yield, 'price' => $forecast, 'month' => $month, 'weather' => $w],
            'expected_revenue' => round($revenue, 2), 'expected_cost' => round($cost, 2),
            'expected_profit' => round($revenue - $cost, 2),
            'risk_notes' => $risk];
    }
}
