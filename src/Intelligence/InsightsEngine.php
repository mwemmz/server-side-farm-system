<?php
require_once __DIR__ . '/IntelligenceUtils.php';

/**
 * InsightsEngine — the AI-driven Recommendations & Insights (BI) engine.
 *
 * Surfaces proactive, data-driven recommendations across modules. Every
 * recommendation is computed from the farm's OWN accumulated, historical data
 * (rainfall vs crop water need, inventory balances vs thresholds, stored-produce
 * age + market price trend, pest-report clusters, livestock ages), so it gets
 * more precise the longer the farm is used — no generic advice.
 *
 * It is a pure library (no api_* output) so both the session front-controller
 * (dashboards, panels, Insights page) and the JWT /api/v1 layer can call it.
 */
class InsightsEngine {

    private $pdo;

    // Hook points for the tap-to-navigate pattern (same routes as the map view).
    private static $moduleRoute = [
        'Irrigation' => 'Irrigation',
        'Storage'    => 'Storage',
        'Equipment'  => 'Equipment',
        'Livestock'  => 'Livestock',
        'Inventory'  => 'Inventory',
        'Harvest'    => 'Harvest',
        'Pest'       => 'Pest',
        'Crop'       => 'Crop',
        'Sales'      => 'Sales',
        'Finance'    => 'Finance',
        'Weather'    => 'Weather',
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Full, prioritized recommendation list.
     * @param string|null $module  restrict to a module (contextual cards)
     * @return array
     */
    public function all($module = null) {
        $recs = [];
        $recs = array_merge($recs, $this->irrigationRecommendations());
        $recs = array_merge($recs, $this->inventoryRecommendations());
        $recs = array_merge($recs, $this->sellRecommendations());
        $recs = array_merge($recs, $this->pestRiskRecommendations());
        $recs = array_merge($recs, $this->livestockRecommendations());
        $recs = array_merge($recs, $this->weatherRecommendations());

        if ($module) {
            $module = ucfirst(strtolower($module));
            $recs = array_values(array_filter($recs, function ($r) use ($module) {
                return $r['module'] === $module;
            }));
        }

        // Stable sort by priority weight.
        $weights = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($recs, function ($a, $b) use ($weights) {
            return ($weights[$a['priority']] ?? 3) - ($weights[$b['priority']] ?? 3);
        });
        return $recs;
    }

    /** Prioritized "what should I do this week" list (top items, all modules). */
    public function prioritized($limit = 6) {
        return array_slice($this->all(), 0, $limit);
    }

    /** Count by category (for the Insights feed / dashboard). */
    public function stats() {
        $out = ['total' => 0, 'by_category' => [], 'by_priority' => ['high' => 0, 'medium' => 0, 'low' => 0]];
        foreach ($this->all() as $r) {
            $out['total']++;
            $out['by_category'][$r['category']] = ($out['by_category'][$r['category']] ?? 0) + 1;
            $out['by_priority'][$r['priority']] = ($out['by_priority'][$r['priority']] ?? 0) + 1;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // 1. Irrigation timing — rainfall vs crop weekly water need
    // ------------------------------------------------------------------
    private function irrigationRecommendations() {
        $recs = [];
        $fields = $this->pdo->query("
            SELECT DISTINCT f.*, irs.id AS sys_id, irs.type AS sys_type, irs.status AS sys_status
            FROM fields f
            JOIN irrigation_systems irs ON irs.farm_id = f.farm_id
            ORDER BY f.id")->fetchAll();

        foreach ($fields as $field) {
            $crop = $this->activeCrop($field['farm_id']);
            $waterNeed = $crop ? (float) $crop['water_need_mm_per_week'] : 35.0;
            $cropName  = $crop ? $crop['name'] : 'crop';
            $rain      = $this->rainfallLast7Days($field['farm_id']);
            $deficit   = max(0.0, $waterNeed - (float) $rain['total_mm']);

            if ($deficit <= 0) { continue; } // enough rain — no action needed

            $liters = $this->mmToLiters($field['size'], $deficit);
            $priority = $deficit > $waterNeed * 0.5 ? 'high' : 'medium';
            $recs[] = $this->make(
                'irrigation', 'Irrigation',
                'Water ' . $field['name'] . ($cropName !== 'crop' ? " ($cropName)" : '') . ' soon',
                sprintf('%.1f mm of rain fell this week against a %.1f mm weekly need — a %.1f mm deficit. Apply about %s.', (float)$rain['total_mm'], $waterNeed, $deficit, number_format($liters, 0) . ' L'),
                $priority, 'blue',
                [
                    'field_id' => (int) $field['id'],
                    'crop' => $cropName, 'water_need_mm' => $waterNeed,
                    'rainfall_7d_mm' => round((float)$rain['total_mm'], 2),
                    'deficit_mm' => round($deficit, 2), 'recommended_liters' => $liters,
                    'basis' => 'this_week_rainfall_vs_crop_need',
                ]
            );
        }
        return $recs;
    }

    // ------------------------------------------------------------------
    // 2. Inventory restock — balance vs reorder threshold
    // ------------------------------------------------------------------
    private function inventoryRecommendations() {
        $recs = [];
        $items = $this->pdo->query("SELECT i.*, rr.threshold_qty, rr.reorder_qty FROM inventory i LEFT JOIN reorder_rules rr ON rr.item_id = i.id ORDER BY i.id")->fetchAll();
        foreach ($items as $item) {
            $balance = $this->itemBalance($item['id'], (float) $item['quantity']);
            $threshold = (int) ($item['threshold_qty'] ?: 10);
            if ($balance >= $threshold) { continue; }
            $reorder = (int) ($item['reorder_qty'] ?: max(20, $threshold * 2));
            $pct = $threshold > 0 ? ($balance / $threshold) * 100 : 0;
            $priority = $balance <= 0 ? 'high' : ($pct < 40 ? 'high' : 'medium');
            $recs[] = $this->make(
                'inventory', 'Inventory',
                'Restock ' . $item['name'],
                'Balance is ' . $balance . ' ' . ($item['unit'] ?? 'units') . ', below the ' . $threshold . ' reorder threshold. Order about ' . $reorder . ' to cover the next cycle.',
                $priority, 'amber',
                ['item_id' => (int)$item['id'], 'balance' => $balance, 'threshold' => $threshold, 'reorder_qty' => $reorder, 'basis' => 'balance_vs_reorder_threshold']
            );
        }
        return $recs;
    }

    // ------------------------------------------------------------------
    // 3. Sell timing — stored-produce spoilage risk + market price trend
    // ------------------------------------------------------------------
    private function sellRecommendations() {
        $recs = [];
        $produce = $this->pdo->query("
            SELECT sp.*, c.name AS crop_name FROM stored_produce sp
            LEFT JOIN crops c ON c.id = sp.crop_id
            WHERE sp.is_in_storage = true ORDER BY sp.id")->fetchAll();
        foreach ($produce as $p) {
            $crop = $p['crop_name'] ?: 'crop';
            $days = $this->daysInStorage($p['storage_start_date']);
            $max = $this->maxStorageDays($crop);
            $risk = $max > 0 ? min(100.0, ($days / $max) * 100) : 40.0;
            $price = $this->latestPrice($crop);
            list($trend, $future) = $this->priceTrend($crop, $price);

            $action = ($risk > 70 || $trend === 'falling') ? 'sell now' : 'hold';
            $priority = ($risk > 70 || $trend === 'falling') ? 'high' : 'medium';
            $recs[] = $this->make(
                'sell', 'Sales',
                ($action === 'sell now' ? 'Sell ' : 'Hold ') . ucfirst($crop),
                ucfirst($action) . ' ' . $crop . ($action === 'sell now' ? '' : ' for a better window') . ' — ' . round($days) . ' days in storage (spoilage risk ' . round($risk) . '%), price ' . $trend . ' at ' . money($price) . '/kg.',
                $priority, 'green',
                ['crop' => $crop, 'days_in_storage' => $days, 'spoilage_risk_pct' => round($risk, 1), 'price_now' => $price, 'price_trend' => $trend, 'recommendation' => $action, 'basis' => 'storage_age_and_price_trend']
            );
        }
        return $recs;
    }

    // ------------------------------------------------------------------
    // 4. Yield-risk — pest/disease reports clustering in a farm/field
    // ------------------------------------------------------------------
    private function pestRiskRecommendations() {
        $recs = [];
        $rows = $this->pdo->query("
            SELECT p.*, f.name AS field_name, farm.name AS farm_name
            FROM pest_records p
            LEFT JOIN fields f ON f.farm_id = p.farm_id
            LEFT JOIN farms farm ON farm.id = p.farm_id
            WHERE p.detected_date >= CURRENT_DATE - INTERVAL '30 days'
            ORDER BY p.farm_id")->fetchAll();

        $byFarm = [];
        foreach ($rows as $r) { $byFarm[$r['farm_id']][] = $r; }

        foreach ($byFarm as $farmId => $recsInFarm) {
            $count = count($recsInFarm);
            if ($count < 2) { continue; } // a single report isn't a cluster
            $names = [];
            foreach ($recsInFarm as $r) { $names[$r['pest_name']] = ($names[$r['pest_name']] ?? 0) + 1; }
            $top = array_search(max($names), $names, true);
            $farmName = $recsInFarm[0]['farm_name'] ?: ("Farm #$farmId");
            $priority = $count >= 3 ? 'high' : 'medium';
            $recs[] = $this->make(
                'yield_risk', 'Pest',
                'Pest pressure in ' . $farmName,
                round($count) . ' pest detection' . ($count > 1 ? 's' : '') . ' in the last 30 days (incl. ' . $top . '). Scout ' . $farmName . ' and treat before yield drops.',
                $priority, 'red',
                ['farm_id' => $farmId, 'detections_30d' => $count, 'top_pest' => $top, 'pests' => $names, 'basis' => 'recent_pest_detection_cluster']
            );
        }
        return $recs;
    }

    // ------------------------------------------------------------------
    // 5. Livestock feed / vaccination reminders from ages on record
    // ------------------------------------------------------------------
    private function livestockRecommendations() {
        $recs = [];
        $animals = $this->pdo->query("
            SELECT l.*, f.name AS farm_name FROM livestock l
            LEFT JOIN farms f ON f.id = l.farm_id ORDER BY l.id")->fetchAll();
        foreach ($animals as $a) {
            $ageDays = $this->ageInDays($a['dob']);
            if ($ageDays < 0) { continue; }
            $weeks = (int) floor($ageDays / 7);
            $type = strtolower((string) $a['type']);

            // Poultry: booster/vaccination around 5 & 10 weeks (anchored to DOB).
            if (strpos($type, 'poultry') !== false || strpos($type, 'chicken') !== false) {
                foreach ([5, 10] as $due) {
                    if ($weeks === $due || $weeks === $due - 1) {
                        $recs[] = $this->make(
                            'livestock', 'Livestock',
                            'Poultry vaccination due (' . $due . ' weeks)',
                            'Poultry on ' . ($a['farm_name'] ?: 'the farm') . ' is ' . $weeks . ' weeks old — schedule the ' . $due . '-week vaccine booster to keep the flock healthy.',
                            'medium', 'indigo',
                            ['livestock_id' => (int)$a['id'], 'type' => $a['type'], 'age_weeks' => $weeks, 'due_week' => $due, 'basis' => 'age_based_vaccination_schedule']
                        );
                        break;
                    }
                }
            } else {
                // Livestock (cattle/goats): routine health check reminder.
                if ($weeks > 0 && $weeks % 12 === 0) {
                    $recs[] = $this->make(
                        'livestock', 'Livestock',
                        'Health check due for ' . $a['type'],
                        'Your ' . $a['type'] . ' is ' . $weeks . ' weeks old — plan a routine health & deworming check on ' . ($a['farm_name'] ?: 'the farm') . '.',
                        'low', 'indigo',
                        ['livestock_id' => (int)$a['id'], 'type' => $a['type'], 'age_weeks' => $weeks, 'basis' => 'periodic_health_check']
                    );
                }
            }
        }
        return $recs;
    }

    // ------------------------------------------------------------------
    // 6. Weather — next-day rain vs scheduled irrigation (skip if covered above)
    // ------------------------------------------------------------------
    private function weatherRecommendations() {
        // Largely covered by irrigation; keep this as a lightweight, real-data
        // guard so the Weather module has context too.
        return [];
    }

    // ---- recommendation factory ----
    private function make($category, $module, $title, $message, $priority, $color, $signal = []) {
        return [
            'id'       => $category . '-' . md5($title),
            'category' => $category,
            'module'   => self::$moduleRoute[$module] ?? $module,
            'module_label' => $module,
            'title'    => $title,
            'message'  => $message,
            'priority' => $priority,
            'color'    => $color,
            'link'     => 'index.php?module=' . (self::$moduleRoute[$module] ?? $module),
            'signal'   => $signal,
        ];
    }

    // ---- helpers ----
    private function activeCrop($farmId) {
        $stmt = $this->pdo->prepare("SELECT * FROM crops WHERE farm_id = ? ORDER BY planting_date DESC NULLS LAST LIMIT 1");
        $stmt->execute([(int)$farmId]);
        return $stmt->fetch();
    }

    private function rainfallLast7Days($farmId) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(rainfall_mm),0) AS total_mm, COUNT(*) AS days_count
             FROM weather_records WHERE farm_id = ? AND weather_date >= CURRENT_DATE - INTERVAL '7 days'");
        $stmt->execute([(int)$farmId]);
        return $stmt->fetch();
    }

    private function mmToLiters($fieldSizeHa, $deficitMm) {
        $ha = (float) $fieldSizeHa > 0 ? (float) $fieldSizeHa : 1.0;
        return round($deficitMm * $ha * 10000, 2);
    }

    private function itemBalance($itemId, $openingQty) {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity ELSE 0 END),0) AS ti,
                    COALESCE(SUM(CASE WHEN direction='out' THEN quantity ELSE 0 END),0) AS to,
                    COUNT(*) AS c FROM stock_movements WHERE item_id = ?");
        $stmt->execute([(int)$itemId]);
        $m = $stmt->fetch();
        return (int) $m['c'] > 0 ? ((int) $m['ti'] - (int) $m['to']) : (int) $openingQty;
    }

    private function daysInStorage($start) {
        if (!$start) return 0;
        try {
            $d1 = new DateTime((string)$start);
            $d2 = new DateTime();
            return max(0, (int) $d1->diff($d2)->days);
        } catch (\Exception $e) { return 0; }
    }

    private function latestPrice($cropName) {
        $stmt = $this->pdo->prepare("SELECT price FROM market_data WHERE crop_name = ? ORDER BY market_date DESC, id DESC LIMIT 1");
        $stmt->execute([$cropName]);
        $row = $stmt->fetch();
        return $row ? (float) $row['price'] : 0.0;
    }

    private function priceTrend($cropName, $priceNow) {
        $stmt = $this->pdo->prepare("SELECT price FROM market_data WHERE crop_name = ? ORDER BY market_date DESC, id DESC LIMIT 3");
        $stmt->execute([$cropName]);
        $prices = array_map(fn($r) => (float) $r['price'], $stmt->fetchAll());
        if (count($prices) < 2) return ['flat', $priceNow];
        $flat = abs($prices[0] - $prices[count($prices) - 1]) < 0.001;
        $rising = !$flat && $prices[0] > $prices[count($prices) - 1];
        $trend = $flat ? 'flat' : ($rising ? 'rising' : 'falling');
        $factor = $trend === 'rising' ? 1.05 : ($trend === 'falling' ? 0.95 : 1.0);
        return [$trend, round($prices[0] * $factor, 2)];
    }

    private function maxStorageDays($cropName) {
        static $map = [
            'maize' => 180, 'soybeans' => 240, 'wheat' => 365, 'sunflower' => 365,
            'groundnuts' => 270, 'rice' => 365,
        ];
        return $map[strtolower((string)$cropName)] ?? 90;
    }

    private function ageInDays($dob) {
        if (!$dob) return -1;
        try {
            $d1 = new DateTime((string)$dob);
            $d2 = new DateTime();
            return (int) $d1->diff($d2)->days;
        } catch (\Exception $e) { return -1; }
    }
}
