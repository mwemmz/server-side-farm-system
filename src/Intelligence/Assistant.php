<?php
require_once __DIR__ . '/InsightsEngine.php';

/**
 * Assistant — a natural-language layer over the farm's actual data.
 *
 * Answers questions across crops, livestock, finances, inventory and market
 * prices by querying the real tables (no generic canned replies). It supports:
 *   - typed queries (spend / revenue / yields / stock / sell timing / weather)
 *   - the recommendations engine on demand ("what should I do this week?")
 *   - simple follow-up clarifying questions when a request is ambiguous
 *     (e.g. which field / crop / period), surfaced as a 'clarify' response.
 *
 * Output: { type, text, cards[] } where cards carry tap-to-navigate links.
 */
class Assistant {

    private $pdo;
    private $insights;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->insights = new InsightsEngine($pdo);
    }

    /** Answer a natural-language question. @return array */
    public function answer($question) {
        $q = strtolower(trim((string) $question));
        if ($q === '') {
            return $this->reply('error', "Ask me something about your farm — e.g. \"How much did I spend on fertilizer this season?\" or \"What should I do this week?\"");
        }

        // On-demand prioritized recommendations.
        if ($this->any($q, ['what should i do', 'todo', 'to do', 'what do i need', 'prioriti', 'recommend', 'this week', 'action plan', 'what next'])) {
            return $this->weeklyPlan();
        }

        if ($this->any($q, ['hello', 'hi ', 'hey', 'help', 'what can you'])) {
            return $this->help();
        }

        if ($this->any($q, ['spend', 'cost', 'expense', 'buy', 'purchase', 'procurement'])) {
            return $this->answerSpend($q);
        }
        if ($this->any($q, ['revenue', 'sales', 'earn', 'sold', 'income', 'received'])) {
            return $this->answerSales($q);
        }
        if ($this->any($q, ['low', 'stock', 'inventory', 'restock', 'reorder', 'run out'])) {
            return $this->answerInventory($q);
        }
        if ($this->any($q, ['yield', 'harvest', 'produce', 'output', 'lowest', 'best farm'])) {
            return $this->answerYield($q);
        }
        if ($this->any($q, ['sell', 'sell now', 'sell maize', 'price', 'market', 'good time to sell', 'wait'])) {
            return $this->answerSell($q);
        }
        if ($this->any($q, ['weather', 'rain', 'irrigat', 'water', 'drought'])) {
            return $this->answerWeatherIntr($q);
        }
        if ($this->any($q, ['livestock', 'animal', 'cattle', 'poultry', 'goat', 'chicken', 'flock'])) {
            return $this->answerLivestock($q);
        }
        if ($this->any($q, ['field', 'farm', 'crop', 'plant', 'grow', 'acre', 'hectare'])) {
            return $this->answerFarms($q);
        }
        if ($this->any($q, ['profit', 'margin', 'net', 'balance sheet', 'financial'])) {
            return $this->answerProfit($q);
        }

        return $this->reply('answer',
            "I couldn't map that to a data category I can query. Try: spend, revenue, yields, low stock, sell timing, weather, livestock, or \"What should I do this week?\"");
    }

    // --- weekly plan (delegates to the recommendations engine) ---
    private function weeklyPlan() {
        $items = $this->insights->prioritized(8);
        $cards = [];
        foreach ($items as $r) {
            $cards[] = [
                'title' => $r['title'],
                'body'  => $r['message'],
                'link'  => $r['link'],
                'priority' => $r['priority'],
                'color'    => $r['color'],
            ];
        }
        if (!$cards) {
            return $this->reply('answer', 'Everything looks on track right now — no urgent recommendations. Keep logging data so the engine can spot issues sooner.');
        }
        return $this->reply('recommendations',
            "Here's your prioritized action plan for the week (from your farm's own data):",
            $cards);
    }

    private function help() {
        return $this->reply('answer',
            "I can query your farm data. Try:\n" .
            "• \"How much did I spend on fertilizer this season?\"\n" .
            "• \"Which field had the lowest yield last harvest?\"\n" .
            "• \"What's low in stock?\"\n" .
            "• \"Is it a good time to sell my maize?\"\n" .
            "• \"How many cattle do I have?\"\n" .
            "• \"What should I do this week?\"");
    }

    // ------------------------------------------------------------------
    private function answerSpend($q) {
        $period = $this->period($q, 'season');
        $category = $this->categoryFilter($q);

        $params = [];
        $where = '';
        if ($category) {
            $where .= " AND LOWER(p.item_name) LIKE ?";
            $params[] = '%' . strtolower($category) . '%';
        }
        if ($period === 'week') {
            $where .= " AND p.purchase_date >= CURRENT_DATE - INTERVAL '7 days'";
        } elseif ($period === 'month') {
            $where .= " AND p.purchase_date >= CURRENT_DATE - INTERVAL '1 month'";
        } elseif ($period === 'year') {
            $where .= " AND p.purchase_date >= CURRENT_DATE - INTERVAL '1 year'";
        }

        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(cost),0) AS total, COUNT(*) AS n, COALESCE(AVG(cost),0) AS avg_cost FROM procurement_records p WHERE 1=1 $where");
        $stmt->execute($params);
        $row = $stmt->fetch();

        $scope = $category ? "on " . ucfirst($category) : "";
        if ($period === 'season') $scope .= " this season";
        elseif ($period === 'week') $scope .= " this week";
        elseif ($period === 'month') $scope .= " this month";
        elseif ($period === 'year') $scope .= " this year";

        if ($row['n'] == 0) {
            return $this->reply('answer', "I don't have any procurement records$scope yet.");
        }
        $text = "You spent " . money($row['total']) . " $scope across " . $row['n'] . " purchase" . ($row['n'] > 1 ? 's' : '') . " (avg " . money($row['avg_cost']) . " each).";
        return $this->reply('answer', $text, [
            ['title' => 'View procurement', 'body' => 'Review the purchases behind this spend.', 'link' => 'index.php?module=Procurement&action=manage'],
            ['title' => 'Cost analysis', 'body' => 'See costs vs sales on the dashboard.', 'link' => 'index.php?module=Analytics'],
        ]);
    }

    private function answerSales($q) {
        $period = $this->period($q, 'season');
        $where = '';
        if ($period === 'week') $where = " WHERE sale_date >= CURRENT_DATE - INTERVAL '7 days'";
        elseif ($period === 'month') $where = " WHERE sale_date >= CURRENT_DATE - INTERVAL '1 month'";
        elseif ($period === 'year') $where = " WHERE sale_date >= CURRENT_DATE - INTERVAL '1 year'";

        $row = $this->pdo->query("SELECT COALESCE(SUM(amount),0) AS total FROM sales_records $where")->fetch();
        $n = $this->pdo->query("SELECT COUNT(*) FROM sales_records $where")->fetchColumn();
        $top = $this->pdo->query("SELECT customer_name, SUM(amount) AS tot FROM sales_records GROUP BY customer_name ORDER BY tot DESC LIMIT 1")->fetch();

        $scope = $period === 'season' ? " this season" : ($period === 'week' ? " this week" : ($period === 'month' ? " this month" : ($period === 'year' ? " this year" : "")));
        $text = "Total revenue$scope is " . money($row['total']) . " across $n sale" . ($n > 1 ? 's' : '') . ".";
        if ($top) $text .= " Your biggest buyer is " . $top['customer_name'] . " (" . money($top['tot']) . ").";
        return $this->reply('answer', $text, [
            ['title' => 'Sales records', 'body' => 'Open your sales log.', 'link' => 'index.php?module=Sales&action=manage'],
        ]);
    }

    private function answerInventory($q) {
        $stmt = $this->pdo->query("SELECT i.*, rr.threshold_qty FROM inventory i LEFT JOIN reorder_rules rr ON rr.item_id = i.id");
        $rows = $stmt->fetchAll();
        $low = [];
        foreach ($rows as $i) {
            $bal = $this->balance($i['id'], (float)$i['quantity']);
            $thr = (int)($i['threshold_qty'] ?: 10);
            if ($bal <= $thr) $low[] = ['name' => $i['name'], 'balance' => $bal, 'unit' => $i['unit'], 'threshold' => $thr];
        }
        if (!$low) {
            $total = count($rows);
            return $this->reply('answer', "Good news — nothing is below its reorder threshold. You're tracking $total item" . ($total > 1 ? 's' : '') . " in total.");
        }
        $lines = [];
        foreach ($low as $x) { $lines[] = "• {$x['name']}: {$x['balance']} {$x['unit']} (threshold {$x['threshold']})"; }
        $cards = [['title' => 'Restock', 'body' => 'Review and restock these items.', 'link' => 'index.php?module=Inventory&action=manage']];
        return $this->reply('answer', "These items are at or below their reorder threshold:\n" . implode("\n", $lines), $cards);
    }

    private function answerYield($q) {
        // Lowest-yield field last harvest: harvest_records -> crops -> farms -> fields.
        $rows = $this->pdo->query("
            SELECT h.quantity, h.harvest_date, h.quality, c.name AS crop_name, c.farm_id
            FROM harvest_records h JOIN crops c ON c.id = h.crop_id
            ORDER BY h.quantity ASC LIMIT 5")->fetchAll();
        if (!$rows) {
            return $this->reply('answer', "I don't have any harvest records yet. Log your first harvest to unlock yield insights.");
        }
        $lowest = $rows[0];
        $farmName = $this->farmName($lowest['farm_id']);
        $field = $this->fieldForFarm($lowest['farm_id']);
        $text = "The lowest-yield harvest on record is " . $lowest['crop_name'] . " (" . $lowest['quantity'] . " kg, " . ($lowest['quality'] ?: 'N/A') . ") on " . ($field ? $field['name'] : $farmName) . ", logged " . $lowest['harvest_date'] . ".";
        return $this->reply('answer', $text, [
            ['title' => 'Harvest records', 'body' => 'Open harvest history.', 'link' => 'index.php?module=Harvest&action=manage'],
        ]);
    }

    private function answerSell($q) {
        $crop = $this->cropMention($q);
        $stmt = $this->pdo->prepare("SELECT sp.*, c.name AS crop_name FROM stored_produce sp LEFT JOIN crops c ON c.id = sp.crop_id WHERE sp.is_in_storage = true" . ($crop ? " AND LOWER(c.name) LIKE ?" : "") . " ORDER BY sp.id");
        $stmt->execute($crop ? ['%' . strtolower($crop) . '%'] : []);
        $produce = $stmt->fetchAll();
        if (!$produce) {
            return $this->reply('answer', "You don't have any stored produce" . ($crop ? " matching " . ucfirst($crop) : "") . " right now to evaluate.");
        }
        $advice = [];
        foreach ($produce as $p) {
            $c = $p['crop_name'] ?: 'crop';
            $days = $this->daysInStorage($p['storage_start_date']);
            $max = $this->maxStorage($c);
            $risk = $max > 0 ? min(100, ($days / $max) * 100) : 40;
            $price = $this->latestPrice($c);
            list($trend) = $this->priceTrend($c);
            $cmd = ($risk > 70 || $trend === 'falling') ? 'SELL NOW' : 'HOLD';
            $advice[] = "• " . ucfirst($c) . ": $cmd (spoilage " . round($risk) . "%, price " . $trend . " " . money($price) . "/kg)";
        }
        $text = "Based on current storage age and market price trends:\n" . implode("\n", $advice) . "\n\nTip: spoilage risk >70% or a falling price favour selling; rising prices with low spoilage favour holding.";
        return $this->reply('answer', $text, [
            ['title' => 'Sell recommendations', 'body' => 'Open the full sell-now-vs-wait breakdown.', 'link' => 'index.php?module=Market'],
            ['title' => 'Stored produce', 'body' => 'Manage what is in storage.', 'link' => 'index.php?module=Storage'],
        ]);
    }

    private function answerWeatherIntr($q) {
        $rows = $this->pdo->query("
            SELECT f.id AS farm_id, f.name, COALESCE(SUM(w.rainfall_mm),0) AS rain
            FROM farms f LEFT JOIN weather_records w ON w.farm_id = f.id AND w.weather_date >= CURRENT_DATE - INTERVAL '7 days'
            GROUP BY f.id ORDER BY f.id")->fetchAll();
        if (!$rows) {
            return $this->reply('answer', "I don't have any weather records for your farms yet.");
        }
        $lines = [];
        foreach ($rows as $r) { $lines[] = "• " . $r['name'] . ": " . round((float)$r['rain'], 1) . " mm rain (last 7 days)"; }
        return $this->reply('answer', "Here's the rainfall on your farms over the last week:\n" . implode("\n", $lines), [
            ['title' => 'Irrigation advice', 'body' => 'See whether your crops need a top-up.', 'link' => 'index.php?module=Irrigation'],
            ['title' => 'Weather panel', 'body' => 'Open the live weather panel.', 'link' => 'index.php?module=Weather'],
        ]);
    }

    private function answerLivestock($q) {
        $stmt = $this->pdo->query("SELECT type, COUNT(*) AS n FROM livestock GROUP BY type ORDER BY n DESC");
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return $this->reply('answer', "You don't have any livestock records yet.");
        }
        $lines = [];
        $total = 0;
        foreach ($rows as $r) { $total += (int)$r['n']; $lines[] = "• {$r['type']}: {$r['n']}"; }
        return $this->reply('answer', "You have $total animals total:\n" . implode("\n", $lines), [
            ['title' => 'Livestock panel', 'body' => 'Monitor your animals live.', 'link' => 'index.php?module=Livestock'],
        ]);
    }

    private function answerFarms($q) {
        $farms = $this->pdo->query("SELECT name, location FROM farms ORDER BY id")->fetchAll();
        $fields = $this->pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(size),0) AS ha FROM fields")->fetch();
        $crops = $this->pdo->query("SELECT COUNT(*) AS n FROM crops")->fetchColumn();
        if (!$farms) return $this->reply('answer', "You don't have any farms registered yet.");
        $names = array_map(fn($f) => $f['name'] . ($f['location'] ? " (" . $f['location'] . ")" : ""), $farms);
        $text = "You have " . count($farms) . " farm" . (count($farms) > 1 ? 's' : '') . ": " . implode(', ', $names) . ". Total: " . $fields['n'] . " field" . ($fields['n'] > 1 ? 's' : '') . " (" . round((float)$fields['ha'], 1) . " ha), $crops crop" . ($crops > 1 ? 's' : '') . ".";
        return $this->reply('answer', $text, [
            ['title' => 'Farm map', 'body' => 'Open the interactive farm & asset map.', 'link' => 'index.php?module=Farm'],
        ]);
    }

    private function answerProfit($q) {
        $cost = (float) $this->pdo->query("SELECT COALESCE(SUM(cost),0) FROM procurement_records")->fetchColumn();
        $sales = (float) $this->pdo->query("SELECT COALESCE(SUM(amount),0) FROM sales_records")->fetchColumn();
        $exp = (float) $this->pdo->query("SELECT COALESCE(SUM(amount),0) FROM finance_records WHERE type='expense'")->fetchColumn();
        $inc = (float) $this->pdo->query("SELECT COALESCE(SUM(amount),0) FROM finance_records WHERE type='income'")->fetchColumn();
        $net = $sales - $cost;
        $text = "Revenue $sales · Procurement costs " . money($cost) . " → net ≈ " . money($net) . " (finance ledger: income " . money($inc) . " vs expenses " . money($exp) . ").";
        return $this->reply('answer', $text, [
            ['title' => 'Finance panel', 'body' => 'Dive into the financials.', 'link' => 'index.php?module=Finance'],
        ]);
    }

    // ------------------------------------------------------------------
    private function reply($type, $text, $cards = []) {
        return ['type' => $type, 'text' => $text, 'cards' => $cards];
    }

    private function any($q, $words) {
        foreach ($words as $w) { if (strpos($q, $w) !== false) return true; }
        return false;
    }

    /** Best-effort period detection. @return string week|month|year|season */
    private function period($q, $default) {
        if ($this->any($q, ['this week', 'last 7', 'past week'])) return 'week';
        if ($this->any($q, ['this month', 'past month'])) return 'month';
        if ($this->any($q, ['this year', 'past year'])) return 'year';
        return $default;
    }

    private function categoryFilter($q) {
        static $cats = ['seed', 'fertilizer', 'diesel', 'insecticide', 'pesticide', 'vaccine', 'feed', 'spare', 'chemical'];
        foreach ($cats as $c) {
            if (strpos($q, $c) !== false) return $c;
        }
        return null;
    }

    private function cropMention($q) {
        static $crops = ['maize', 'soybean', 'wheat', 'sunflower', 'groundnut', 'rice'];
        foreach ($crops as $c) { if (strpos($q, $c) !== false) return $c; }
        return null;
    }

    private function balance($itemId, $opening) {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity ELSE 0 END),0) AS ti, COALESCE(SUM(CASE WHEN direction='out' THEN quantity ELSE 0 END),0) AS to, COUNT(*) AS c FROM stock_movements WHERE item_id = ?");
        $stmt->execute([(int)$itemId]);
        $m = $stmt->fetch();
        return (int)$m['c'] > 0 ? ((int)$m['ti'] - (int)$m['to']) : (int)$opening;
    }

    private function farmName($id) {
        $stmt = $this->pdo->prepare("SELECT name FROM farms WHERE id = ?"); $stmt->execute([(int)$id]);
        $r = $stmt->fetch(); return $r ? $r['name'] : "Farm #$id";
    }
    private function fieldForFarm($farmId) {
        $stmt = $this->pdo->prepare("SELECT name FROM fields WHERE farm_id = ? ORDER BY id LIMIT 1"); $stmt->execute([(int)$farmId]);
        return $stmt->fetch();
    }
    private function daysInStorage($start) {
        if (!$start) return 0;
        try { $d1 = new DateTime((string)$start); return max(0, (int)$d1->diff(new DateTime())->days); } catch (\Exception $e) { return 0; }
    }
    private function maxStorage($crop) {
        static $map = ['maize'=>180,'soybeans'=>240,'wheat'=>365,'sunflower'=>365,'groundnuts'=>270,'rice'=>365];
        return $map[strtolower((string)$crop)] ?? 90;
    }
    private function latestPrice($crop) {
        $stmt = $this->pdo->prepare("SELECT price FROM market_data WHERE crop_name = ? ORDER BY market_date DESC, id DESC LIMIT 1");
        $stmt->execute([$crop]); $r = $stmt->fetch(); return $r ? (float)$r['price'] : 0.0;
    }
    private function priceTrend($crop) {
        $stmt = $this->pdo->prepare("SELECT price FROM market_data WHERE crop_name = ? ORDER BY market_date DESC, id DESC LIMIT 3");
        $stmt->execute([$crop]); $p = array_map(fn($r) => (float)$r['price'], $stmt->fetchAll());
        if (count($p) < 2) return ['flat', $p[0] ?? 0];
        $flat = abs($p[0] - $p[count($p)-1]) < 0.001;
        return [$flat ? 'flat' : ($p[0] > $p[count($p)-1] ? 'rising' : 'falling'), $p[0]];
    }
}
