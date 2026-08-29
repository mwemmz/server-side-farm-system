<?php
/**
 * MarketAnalysis — Farm Market Analysis & Price Prediction.
 *
 * Explains, in plain terms, what a farmer can expect to earn if they plant a
 * chosen crop in a chosen month, BEFORE they commit land and inputs. It reads
 * only data the system already tracks elsewhere (market_data price history,
 * sales_records demand, crops planting/harvest dates, harvest_records) — no
 * extra manual entry required.
 *
 * The prediction method is deliberately simple and explainable (no black-box):
 *   - A monthly price history is built from market_data over ~2 years.
 *   - A SEASONAL factor = median/mean of the same calendar month across years.
 *   - A short-run TREND = linear regression over the most recent months
 *     (a moving-average style slope).
 *   - These are blended into an expected harvest-month price, with a confidence
 *     range derived from price volatility and data coverage.
 *
 * It is a pure library (no JSON output) so both the session landscape and any
 * API layer can call it.
 */
require_once __DIR__ . '/IntelligenceUtils.php';

class MarketAnalysis {

    private $pdo;
    public $monthNames = [1=>'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    // Days from planting to first harvest, per crop (reference agronomy figures).
    // Used to translate a chosen planting month into an expected harvest window.
    private $daysToHarvest = [
        'tomato'    => [70, 90],   // first pick ~10-12 weeks, season Apr-Jun
        'maize'     => [110, 130], // green/dry maize
        'soybeans'  => [110, 130],
        'wheat'     => [120, 140],
        'groundnut' => [120, 140],
        'rice'      => [120, 150],
        'cabbage'   => [80, 100],
        'onion'     => [110, 130],
        'sunflower' => [100, 120],
        'beans'     => [75, 90],
        'pepper'    => [85, 105],
        'potato'    => [90, 110],
    ];

    // Assumed variable cost-to-produce per kg (ZMW), used only as a break-even
    // anchor for the recommendation. Falls back to ~62% of the 2-year mean
    // price when no anchor is known for a crop.
    private $breakEven = [
        'tomato' => 3.4, 'maize' => 2.4, 'soybeans' => 5.0, 'wheat' => 3.2,
        'groundnut' => 9.0, 'rice' => 3.4, 'cabbage' => 2.0, 'onion' => 3.0,
        'sunflower' => 4.2, 'beans' => 6.0, 'pepper' => 6.5, 'potato' => 3.6,
    ];

    public function __construct($pdo) { $this->pdo = $pdo; }

    // ---- crop catalogue -------------------------------------------------
    /** Distinct crops seen in market data, seeded to always include tomato. */
    public function crops() {
        $list = [];
        $rows = $this->pdo->query("SELECT DISTINCT crop_name FROM market_data ORDER BY crop_name")->fetchAll();
        foreach ($rows as $r) { $list[] = $r['crop_name']; }
        // Ensure the UAT reference crop (and a few common ones) always appear.
        foreach (['Tomato', 'Maize', 'Cabbage', 'Onion'] as $c) {
            $hit = false;
            foreach ($list as $l) { if (strcasecmp($l, $c) === 0) { $hit = true; break; } }
            if (!$hit) { $list[] = $c; }
        }
        sort($list);
        return $list;
    }

    // ---- 1. Historical price analysis ------------------------------------
    /**
     * Monthly average price series for a crop over the last $years years.
     * @return array of ['ym','year','month','price','count'] ascending by month.
     */
    public function priceHistory($crop, $years = 2) {
        $stmt = $this->pdo->prepare(
            "SELECT EXTRACT(YEAR FROM market_date) AS y,
                    EXTRACT(MONTH FROM market_date) AS m,
                    AVG(price) AS p, COUNT(*) AS n
             FROM market_data
             WHERE LOWER(crop_name) = LOWER(?)
               AND market_date >= (CURRENT_DATE - (INTERVAL '$years years'))
             GROUP BY y, m ORDER BY y, m");
        $stmt->execute([$crop]);
        $out = [];
        foreach ($stmt->fetchAll() as $r) {
            $out[] = [
                'ym'    => (int)$r['y'] . '-' . str_pad((int)$r['m'], 2, '0', STR_PAD_LEFT),
                'year'  => (int)$r['y'],
                'month' => (int)$r['m'],
                'price' => round((float)$r['p'], 2),
                'count' => (int)$r['n'],
            ];
        }
        return $out;
    }

    /** Seasonal profile: average price per calendar month across the window.*/
    public function seasonalProfile($crop, $years = 2) {
        $hist = $this->priceHistory($crop, $years);
        $byMonth = [];
        foreach ($hist as $h) { $byMonth[$h['month']][] = $h['price']; }
        $profile = [];
        foreach (range(1, 12) as $m) {
            if (empty($byMonth[$m])) { $profile[$m] = null; continue; }
            $profile[$m] = round(array_sum($byMonth[$m]) / count($byMonth[$m]), 2);
        }
        return $profile;
    }

    /** Human-readable seasonal pattern + key drivers for a crop. */
    public function seasonalAnalysis($crop, $years = 2) {
        $hist = $this->priceHistory($crop, $years);
        if (!$hist) return ['available' => false];

        // Monthly averages (across all years) -> peak & trough.
        $profile = $this->seasonalProfile($crop, $years);
        $peaks = []; $troughs = [];
        $total = 0; $cnt = 0;
        foreach ($profile as $m => $v) { if ($v === null) continue; $total += $v; $cnt++; }
        $overallMean = $cnt ? $total / $cnt : 0;
        $mean = $overallMean ?: 1;

        // Variance / volatility for confidence bands.
        $ss = 0; $n = 0;
        foreach ($profile as $v) { if ($v === null) continue; $ss += ($v - $mean) ** 2; $n++; }
        $std = $n > 1 ? sqrt($ss / $n) : $mean * 0.2;
        $volatility = $mean ? min(1.2, $std / $mean) : 0.25;

        // Peak = months >= mean*1.12 ; trough = months <= mean*0.85.
        foreach ($profile as $m => $v) {
            if ($v === null) continue;
            if ($v >= $mean * 1.12) $peaks[] = $m;
            elseif ($v <= $mean * 0.85) $troughs[] = $m;
        }
        $peakLabel  = empty($peaks)  ? '—' : implode(' / ', array_map(fn($m) => $this->monthNames[$m], $peaks));
        $troughLabel= empty($troughs)? '—' : implode(' / ', array_map(fn($m) => $this->monthNames[$m], $troughs));

        // Influencing factors (deterministic flags from the calendar + data).
        $factors = [];
        $glutMonths = array_values(array_filter(array_keys($profile),
            fn($m) => ($profile[$m] ?? 0) <= ($mean * 0.80) && $profile[$m] !== null));
        if ($glutMonths) {
            $factors[] = 'Likely harvest glut / oversupply in ' .
                implode(', ', array_map(fn($m) => $this->monthNames[$m], $glutMonths)) . ' (prices dip the most here).';
        }
        // Rainy season (Zambia: roughly Nov–Apr) can suppress supply early and
        // glut on harvest.
        $factors[] = 'Zambia rainy season (~Nov–Apr) lowers fresh-market supply early season; expect firmer prices before the main harvest and softer prices at harvest peaks.';
        if ($cnt < 6) {
            $factors[] = 'Limited historical market entries (' . $cnt . ' months of data) — treat the range as indicative rather than precise.';
        }
        return [
            'available' => true,
            'mean'       => round($mean, 2),
            'volatility' => round($volatility * 100, 0),
            'peak_months' => $peakLabel,
            'trough_months' => $troughLabel,
            'factors'    => $factors,
            'profile'    => $profile,
        ];
    }

    // ---- 2. Price prediction ---------------------------------------------
    /**
     * Predict the likely harvest-month price using seasonal averaging + a short
     * trend. Explainable: returns the method name and a confidence range.
     * @param int $plantMonth  1..12
     */
    public function predict($crop, $plantMonth) {
        list($harvestStart, $harvestEnd) = $this->harvestWindow($crop, $plantMonth);
        $windows = $this->distinctMonths($harvestStart, $harvestEnd);
        $hist = $this->priceHistory($crop, 2);
        $recent = array_values(array_filter($hist, fn($h) => $h['ym'] >= date('Y-m', strtotime('-11 months'))));

        // Base seasonal estimate for the harvest window.
        $seasonPrices = [];
        foreach ($windows as $m) {
            $pts = array_values(array_filter($hist, fn($h) => $h['month'] === $m));
            if ($pts) {
                $vals = array_map(fn($p) => $p['price'], $pts);
                sort($vals);
                $seasonPrices[] = $this->median($vals);
            }
        }
        $seasonal = $seasonPrices ? array_sum($seasonPrices) / count($seasonPrices) : null;

        // Recent overall level (sparse data fallback).
        $recentVals = array_map(fn($h) => $h['price'], $recent);
        $recentMean = $recentVals ? array_sum($recentVals) / count($recentVals) : null;

        // Short trend via linear regression on the last 6 monthly points.
        $trend = 0.0;
        $pts = array_slice($recent, -6);
        if (count($pts) >= 3) {
            $xs = range(0, count($pts) - 1);
            $ys = array_map(fn($p) => $p['price'], $pts);
            $trend = $this->regressionSlope($xs, $ys);
            $trend = is_finite($trend) ? $trend : 0.0;
        }

        // Blend: seasonal weighs higher when present; else recent level.
        if ($seasonal !== null && $recentMean !== null) {
            $base = 0.65 * $seasonal + 0.35 * $recentMean;
            $method = 'Seasonal moving average (median price in your harvest months across years) blended 65/35 with the recent 12-month average';
        } elseif ($recentMean !== null) {
            $base = $recentMean;
            $method = 'Recent 12-month average (insufficient same-month history for a full seasonal factor)';
        } elseif ($seasonal !== null) {
            $base = $seasonal;
            $method = 'Seasonal median for your harvest months';
        } else {
            $base = $this->defaultPrice($crop);
            $method = 'No market history found — using a reference market price';
        }

        // Months until the start of harvest (to project the trend forward).
        $now = new DateTime('first day of this month');
        $harvestDt = new DateTime(sprintf('%04d-%02d-01', (int)$now->format('Y'), $harvestStart));
        if ($harvestDt < $now) { $harvestDt->modify('+1 year'); }
        $monthsAhead = $now->diff($harvestDt)->m + $now->diff($harvestDt)->y * 12;

        // Apply the short-run trend, but keep it bounded and near the seasonal
        // base so oscillating monthly data can't drive the forecast to absurd
        // levels several months out. Trend may shift the estimate by up to ~25%.
        if ($base > 0) {
            $frac = $trend * max(0, $monthsAhead) / $base;
            $cap = 0.25;
            $projected = $base * (1 + max(-$cap, min($cap, $frac)));
        } else {
            $projected = $base;
        }
        $projected = max(0.3 * $base, max(0.01, $projected));

        // Confidence range from volatility + data coverage.
        $season = $this->seasonalAnalysis($crop, 2);
        $vol = $season['volatility'] / 100; // fraction
        $coverage = count($hist);
        $dataConf = min(1.0, $coverage / 24);           // 24 months = full 2yr
        $band = 0.14 + $vol * 0.5 * (1.0 + (1 - $dataConf)); // widen when data is thin
        $band = min(0.55, $band);
        $low  = round(max(0.01, $projected * (1 - $band)), 2);
        $high = round($projected * (1 + $band), 2);
        $confidence = (int) round(min(95, 38 + $coverage * 2.4) * max(0.5, $dataConf));

        return [
            'plant_month'   => (int)$plantMonth,
            'harvest_start' => $harvestStart,
            'harvest_end'   => $harvestEnd,
            'harvest_label' => $this->monthNames[$harvestStart] . '–' . $this->monthNames[$harvestEnd],
            'point'  => round($projected, 2),
            'low'    => $low,
            'high'   => $high,
            'confidence' => min(95, $confidence),
            'method' => $method,
            'trend_per_month' => round($trend, 2),
            'volatility_pct'  => $season['volatility'],
            'data_months'     => $coverage,
        ];
    }

    // ---- 3. Demand estimation --------------------------------------------
    /**
     * Historical demand for a harvest period, proxied from sales activity
     * (monthly value sold + number of buyers) across a ~2-year window.
     */
    public function demand($crop, $harvestStart, $harvestEnd) {
        $windows = $this->distinctMonths($harvestStart, $harvestEnd);
        $stmt = $this->pdo->query(
            "SELECT EXTRACT(MONTH FROM sale_date) AS m,
                    SUM(amount) AS sales, COUNT(DISTINCT customer_name) AS buyers
             FROM sales_records
             WHERE sale_date >= (CURRENT_DATE - INTERVAL '2 years')
             GROUP BY m ORDER BY m");
        $byMonth = [];
        foreach ($stmt->fetchAll() as $r) { $byMonth[(int)$r['m']] = $r; }

        $monthly = [];
        $totalSales = 0; $n = 0;
        foreach ($windows as $m) {
            $s = $byMonth[$m] ?? ['sales' => 0, 'buyers' => 0];
            $monthly[$m] = ['month' => $this->monthNames[$m], 'sales' => round((float)$s['sales'], 2), 'buyers' => (int)$s['buyers']];
            $totalSales += (float)$s['sales']; $n++;
        }
        $avg = $n ? $totalSales / $n : 0;

        // Simple, readable labelling by the farm's typical monthly sales value.
        $level = 'Limited data';
        if ($avg >= 150000)      $level = 'High';
        elseif ($avg >= 50000)   $level = 'Moderate';
        elseif ($avg > 0)        $level = 'Low';

        return [
            'harvest_months' => $monthly,
            'avg_monthly_sales' => round($avg, 2),
            'level' => $level,
            'buyers_total' => array_sum(array_column($monthly, 'buyers')),
            'note' => 'Demand is proxied from recorded sales value and buyer activity across the farm; with limited sales history treat this as directional.',
        ];
    }

    // ---- 4. Decision support ---------------------------------------------
    /** Full potent/profitability report for a crop planted in a given month. */
    public function decisionReport($crop, $plantMonth) {
        $pred = $this->predict($crop, (int)$plantMonth);
        $dem  = $this->demand($crop, $pred['harvest_start'], $pred['harvest_end']);
        $season = $this->seasonalAnalysis($crop, 2);

        // Break-even anchor.
        $be = $this->breakEven[strtolower($crop)] ?? null;
        if ($be === null) { $be = round(($season['mean'] ?? $pred['point']) * 0.62, 2); }

        // Risks.
        $risks = [];
        $crashRisk = 'Low';
        // Glut: if predicted point is below the 2-year mean, seasonal oversupply is likely.
        if ($season['mean'] && $pred['point'] < $season['mean'] * 0.9) {
            $risks[] = 'Above-average chance of seasonal oversupply: your expected harvest price (K' . $pred['point'] . ') is below the crop\'s ' . round($season['mean'], 2) . ' historical average.';
            $crashRisk = 'High';
        } else {
            $risks[] = 'Seasonal oversupply risk is moderate — prices in ' . $pred['harvest_label'] . ' sit near/above the historical average.';
        }
        if ($pred['volatility_pct'] >= 35) {
            $risks[] = 'High price volatility (' . $pred['volatility_pct'] . '%) — the market swings enough to shift profitability either way.';
        }
        if ($pred['low'] > 0 && $be > 0 && $pred['low'] < $be) {
            $risks[] = 'In a downside scenario the price could fall below your estimated break-even of K' . $be . '/kg.';
        }
        $risks[] = 'Weather risk: Zambia\'s rainy season drives both yields and market supply; a good national season usually means lower prices at harvest, a poor one higher prices.';
        if (!$season['available']) {
            $risks[] = 'Very little market history for this crop is recorded — add market entries to sharpen the forecast.';
        }

        // Recommendation. Profitable needs the whole band comfortably above
        // break-even; marginal if the upside can at least cover costs.
        $low = $pred['low']; $high = $pred['high'];
        if ($low >= $be * 1.12)        { $verdict = 'profitable';   $verdictLabel = 'Profitable';   $color = 'green'; }
        elseif ($high >= $be * 1.05)   { $verdict = 'marginal';     $verdictLabel = 'Marginal';     $color = 'amber'; }
        else                           { $verdict = 'not_recommended'; $verdictLabel = 'Not recommended'; $color = 'red'; }

        $reason = 'At your expected harvest price range of K' . $low . '–K' . $high . '/kg against an estimated cost-to-produce of around K' . $be . '/kg, planting ' . ucfirst($crop) . ' in ' . $this->monthNames[$plantMonth] .
            ' for a ' . $pred['harvest_label'] . ' harvest looks ' . ($verdict === 'profitable' ? 'worthwhile' : ($verdict === 'marginal' ? 'borderline — watch costs and lock in buyers early' : 'hard to justify at current seasonal prices')) . '.';

        return [
            'crop' => ucfirst($crop),
            'plant_month' => (int)$plantMonth,
            'plant_month_label' => $this->monthNames[$plantMonth],
            'harvest_label' => $pred['harvest_label'],
            'prediction' => $pred,
            'demand' => $dem,
            'seasonal' => $season,
            'break_even' => round($be, 2),
            'risks' => $risks,
            'crash_risk' => $crashRisk,
            'verdict' => $verdict,
            'verdict_label' => $verdictLabel,
            'color' => $color,
            'reasoning' => $reason,
        ];
    }

    // ---- helpers -----------------------------------------------------------
    /** harvest start/end months (1-12) from plant month + crop days-to-mature. */
    public function harvestWindow($crop, $plantMonth) {
        $lc = strtolower((string)$crop);
        $mature = $this->daysToHarvest[$lc] ?? [95, 120];
        $start = ($plantMonth + (int)round($mature[0] / 30.44)) % 12; if ($start <= 0) $start += 12;
        $end   = ($plantMonth + (int)round($mature[1] / 30.44)) % 12; if ($end <= 0) $end += 12;
        if ($end == $start) { $end = ($start % 12) + 1; }
        return [$start, $end];
    }

    private function distinctMonths($a, $b) {
        $out = [];
        $c = $a;
        while (true) { $out[] = $c; if ($c === $b) break; $c = ($c % 12) + 1; if (count($out) > 13) break; }
        return $out;
    }

    private function median($arr) {
        if (!$arr) return 0.0;
        sort($arr);
        $m = count($arr);
        $mid = (int)floor($m / 2);
        return ($m % 2) ? $arr[$mid] : (($arr[$mid - 1] + $arr[$mid]) / 2);
    }

    private function regressionSlope($xs, $ys) {
        $n = count($xs); if ($n < 2) return 0.0;
        $mx = array_sum($xs) / $n; $my = array_sum($ys) / $n;
        $num = 0.0; $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $num += ($xs[$i] - $mx) * ($ys[$i] - $my);
            $den += ($xs[$i] - $mx) ** 2;
        }
        return $den ? $num / $den : 0.0;
    }

    private function defaultPrice($crop) {
        $map = ['tomato' => 6.0, 'maize' => 3.5, 'soybeans' => 7.2, 'wheat' => 4.8,
                'groundnut' => 12.0, 'cabbage' => 2.5, 'onion' => 3.5, 'rice' => 4.0,
                'sunflower' => 5.5, 'beans' => 8.0, 'pepper' => 7.0, 'potato' => 4.5];
        return $map[strtolower((string)$crop)] ?? 5.0;
    }
}
