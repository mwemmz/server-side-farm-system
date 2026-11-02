<?php

class PlantingPlannerService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function plantingWhatIf(array $input): array
    {
        $cropId = filter_var($input['crop_id'] ?? null, FILTER_VALIDATE_INT);
        $fieldId = filter_var($input['field_id'] ?? null, FILTER_VALIDATE_INT);
        $month = filter_var($input['planting_month'] ?? null, FILTER_VALIDATE_INT);
        $area = filter_var($input['area_ha'] ?? null, FILTER_VALIDATE_FLOAT);
        $inputCost = filter_var($input['input_cost_estimate'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($cropId === false || $cropId <= 0 || $month === false || $month < 1 || $month > 12 || $area === false || $area <= 0 || $inputCost === false || $inputCost < 0) {
            throw new InvalidArgumentException('crop_id, planting_month, area_ha, and input_cost_estimate are required.');
        }
        // expected yield from crop (if recorded) else 0
        $cy = $this->pdo->prepare("SELECT name, expected_yield FROM crops WHERE id = ?");
        $cy->execute([$cropId]);
        $crop = $cy->fetch() ?: [];
        $yield = (float) ($crop['expected_yield'] ?? 0);
        if ($yield <= 0) $yield = 1.0; // fallback assumption
        // price forecast from market_data latest for crop name
        $cropName = (string) ($crop['name'] ?? '');
        $mp = $this->pdo->prepare("SELECT price FROM market_data WHERE crop_name = ? ORDER BY market_date DESC LIMIT 1");
        $mp->execute([$cropName]);
        $price = (float) ($mp->fetchColumn() ?: 0);
        if ($price <= 0) $price = 1.0;
        // weather
        $wq = $this->pdo->prepare("SELECT AVG(rainfall_mm) AS rainfall, AVG(temperature) AS temperature FROM weather_records WHERE EXTRACT(MONTH FROM weather_date)=?");
        $wq->execute([$month]);
        $w = $wq->fetch() ?: [];
        $revenue = $area * $yield * $price;
        $cost = $area * $inputCost;
        $riskNotes = [];
        if ((float)($w['rainfall'] ?? 0) < 20) $riskNotes[] = 'Higher water risk; plan irrigation.';
        if ($price <= 1.0) $riskNotes[] = 'No market price history; price assumption is placeholder.';
        if ($yield <= 1.0 && empty($crop['expected_yield'])) $riskNotes[] = 'No recorded expected yield for this crop; using 1.0 unit/ha assumption.';
        if (empty($riskNotes)) $riskNotes[] = 'Weather and market indicators are within available historical range.';
        return [
            'assumptions' => ['crop' => $cropName, 'yield_per_ha' => $yield, 'price' => $price, 'month' => $month, 'weather' => $w, 'field_id' => $fieldId],
            'expected_revenue' => round($revenue, 2),
            'expected_cost' => round($cost, 2),
            'expected_profit' => round($revenue - $cost, 2),
            'risk_notes' => $riskNotes,
        ];
    }
    public function plan(array $input): array
    {
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
