<?php
require_once __DIR__ . '/IntelligenceUtils.php';

/**
 * Feature 4 — Sell-Now-vs-Wait Recommendation.
 *
 * Combines stored-produce age (spoilage risk), current price and price-trend
 * direction into a single explainable decision. All inputs are returned so the
 * recommendation is transparent.
 */
class SellRecommendationService {

    private $pdo;

    // Reasonable max-storage-days defaults per crop. Keyed case-insensitively
    // on crop name; falls back to a generic default.
    private static $maxStorageDaysByCrop = [
        'maize'       => 180,
        'soybeans'    => 240,
        'wheat'       => 365,
        'sunflower'   => 365,
        'groundnuts'  => 270,
        'rice'        => 365,
    ];
    private static $defaultMaxStorageDays = 90;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * GET /api/v1/stored-produce/{id}/sell-recommendation
     */
    public function forProduce($produceId) {
        $produce = $this->fetchProduce($produceId);
        if (!$produce) {
            api_err('Stored produce not found', 404);
        }
        $result = $this->buildRecommendation($produce);
        api_ok($result);
    }

    /**
     * POST /api/v1/sell-recommendations/run
     * Batch recompute for all stored produce still in storage.
     */
    public function runBatch() {
        $summary = ['produce_evaluated' => 0, 'recommendations_created' => 0, 'checked' => []];
        $produceRows = $this->pdo->query(
            "SELECT sp.*, c.name AS crop_name FROM stored_produce sp
             LEFT JOIN crops c ON c.id = sp.crop_id
             WHERE sp.is_in_storage = true
             ORDER BY sp.id"
        )->fetchAll();

        foreach ($produceRows as $produce) {
            $summary['produce_evaluated']++;
            $r = $this->buildRecommendation($produce, false);
            $summary['checked'][] = $r;
            if ($r['created']) {
                $summary['recommendations_created']++;
            }
        }
        IntelligenceUtils::auditSystem($this->pdo, 'sell_recommendation_batch_run', 'sell_recommendations', null, $summary);
        api_ok($summary);
    }

    /** Compute, persist (unless readOnly), and return the recommendation. */
    private function buildRecommendation($produce, $readOnly = false) {
        $inputs = $this->collectInputs($produce);
        $decision = $this->decide($inputs);

        $rec = [
            'stored_produce_id'      => (int) $produce['id'],
            'crop_name'              => $inputs['crop_name'],
            'recommendation'         => $decision['recommendation'],
            'expected_price_now'     => $inputs['price_now'],
            'expected_price_future'  => $inputs['price_future'],
            'spoilage_risk_pct'      => round($inputs['spoilage_risk_pct'], 2),
            'reasoning'              => $decision['reasoning'],
            'inputs'                 => $inputs,
        ];

        if ($readOnly) {
            $rec['created'] = false;
            return $rec;
        }

        // Persist the recommendation
        $stmt = $this->pdo->prepare(
            "INSERT INTO sell_recommendations
                (stored_produce_id, recommendation, expected_price_now, expected_price_future, spoilage_risk_pct, reasoning)
             VALUES (?, ?, ?, ?, ?, ?) RETURNING id"
        );
        $stmt->execute([
            (int) $produce['id'],
            $decision['recommendation'],
            $inputs['price_now'],
            $inputs['price_future'],
            round($inputs['spoilage_risk_pct'], 2),
            $decision['reasoning'],
        ]);
        $rec['recommendation_id'] = (int) $stmt->fetchColumn();
        $rec['created'] = true;
        return $rec;
    }

    private function collectInputs($produce) {
        $cropName = $produce['crop_name'] ?: 'unknown';
        $daysInStorage = $this->daysInStorage($produce);

        // Latest price from market_data for this crop
        $priceNow = $this->latestPrice($cropName);
        // Trend from the last market_data points
        list($trend, $priceFuture) = $this->priceTrend($cropName, $priceNow);

        $maxDays = $this->maxStorageDays($cropName);
        $spoilageRisk = $maxDays > 0 ? min(100.0, ($daysInStorage / $maxDays) * 100) : 40.0;

        return [
            'crop_name'             => $cropName,
            'days_in_storage'       => $daysInStorage,
            'max_storage_days'      => $maxDays,
            'price_now'             => $priceNow,
            'price_trend'           => $trend,
            'price_future'          => $priceFuture,
            'spoilage_risk_pct'     => $spoilageRisk,
            'quantity'              => (float) $produce['quantity'],
            'grade'                 => $produce['grade'],
        ];
    }

    private function decide($inputs) {
        $risk = (float) $inputs['spoilage_risk_pct'];
        $trend = $inputs['price_trend'];

        if ($risk > 70) {
            $rec = 'sell_now';
            $why = "Spoilage risk is {$risk}%, above the 70% safety threshold.";
        } elseif ($trend === 'falling') {
            $rec = 'sell_now';
            $why = "Price trend is falling (latest K{$inputs['price_now']} vs future K{$inputs['price_future']}).";
        } elseif ($trend === 'rising' && $risk < 40) {
            $rec = 'wait';
            $why = "Price trend is rising and spoilage risk is only {$risk}% (below 40%), so waiting may fetch a better price.";
        } else {
            $rec = 'sell_now';
            $why = "Defaulting to the safer option given current conditions (trend {$trend}, spoilage {$risk}%).";
        }
        return ['recommendation' => $rec, 'reasoning' => "Recommendation: {$rec}. " . $why];
    }

    // ---- helpers ----

    private function daysInStorage($produce) {
        $start = $produce['storage_start_date'];
        if (!$start) return 0;
        $d1 = new DateTime($start);
        $d2 = new DateTime();
        return max(0, (int) $d1->diff($d2)->days);
    }

    private function latestPrice($cropName) {
        $stmt = $this->pdo->prepare(
            "SELECT price FROM market_data WHERE crop_name = ? ORDER BY market_date DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$cropName]);
        $row = $stmt->fetch();
        return $row ? (float) $row['price'] : 0.0;
    }

    private function priceTrend($cropName, $priceNow) {
        $stmt = $this->pdo->prepare(
            "SELECT price FROM market_data WHERE crop_name = ? ORDER BY market_date DESC, id DESC LIMIT 3"
        );
        $stmt->execute([$cropName]);
        $prices = array_map(fn($r) => (float) $r['price'], $stmt->fetchAll());

        if (count($prices) < 2) {
            return ['flat', $priceNow];
        }
        // Check if prices are rising or falling across the points
        $rising = $prices[0] > $prices[count($prices) - 1];
        $flat   = abs($prices[0] - $prices[count($prices) - 1]) < 0.001;
        $trend = $flat ? 'flat' : ($rising ? 'rising' : 'falling');

        // Expected future price = latest price adjusted by an assumed trend factor
        $factor = 1.0;
        if ($trend === 'rising')     $factor = 1.05;
        elseif ($trend === 'falling') $factor = 0.95;
        $future = round($prices[0] * $factor, 2);

        return [$trend, $future];
    }

    private function maxStorageDays($cropName) {
        $key = strtolower((string) $cropName);
        return self::$maxStorageDaysByCrop[$key] ?? self::$defaultMaxStorageDays;
    }

    private function fetchProduce($id) {
        $stmt = $this->pdo->prepare(
            "SELECT sp.*, c.name AS crop_name FROM stored_produce sp
             LEFT JOIN crops c ON c.id = sp.crop_id WHERE sp.id = ?"
        );
        $stmt->execute([(int) $id]);
        return $stmt->fetch();
    }
}
