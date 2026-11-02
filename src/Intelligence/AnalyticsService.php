<?php
/**
 * AnalyticsService — financial and production analytics built on the single
 * costing ledger (finance_records expenses) plus sales_records revenue.
 *
 * Every query is defensive: a missing table or column (a database that has not
 * finished migrating) is logged and treated as zero/empty, never thrown.
 */
class AnalyticsService
{
    private $pdo;
    private $from;
    private $to;

    public function __construct($pdo, ?string $from = null, ?string $to = null)
    {
        $this->pdo = $pdo;
        $this->from = $this->validDate($from) ?: date('Y-01-01');
        $this->to   = $this->validDate($to) ?: date('Y-m-d');
        if ($this->from > $this->to) {
            $tmp = $this->from;
            $this->from = $this->to;
            $this->to = $tmp;
        }
    }

    public function range(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }

    /** Headline numbers for the analytics page and dashboard strip. */
    public function overview(): array
    {
        $revenue = (float) $this->scalar(
            "SELECT COALESCE(SUM(amount),0) FROM sales_records WHERE sale_date BETWEEN ? AND ?",
            [$this->from, $this->to]
        );
        $expenses = (float) $this->scalar(
            "SELECT COALESCE(SUM(amount),0) FROM finance_records WHERE type = 'expense' AND date BETWEEN ? AND ?",
            [$this->from, $this->to]
        );
        $area = (float) $this->scalar(
            "SELECT COALESCE(SUM(COALESCE(computed_hectares, size)),0) FROM fields"
        );
        $harvestKg = (float) $this->scalar(
            "SELECT COALESCE(SUM(quantity),0) FROM harvest_records WHERE harvest_date BETWEEN ? AND ?",
            [$this->from, $this->to]
        );
        $activeCrops = (int) $this->scalar("SELECT COUNT(*) FROM crops WHERE COALESCE(status,'active') = 'active'");
        $livestock = (int) $this->scalar("SELECT COUNT(*) FROM livestock WHERE COALESCE(status,'active') = 'active'");
        $inventoryValue = (float) $this->scalar(
            "SELECT COALESCE(SUM(COALESCE(quantity,0) * COALESCE(unit_cost,0)),0) FROM inventory"
        );

        $profit = $revenue - $expenses;
        return [
            'from'            => $this->from,
            'to'              => $this->to,
            'revenue'         => $revenue,
            'expenses'        => $expenses,
            'profit'          => $profit,
            'margin_pct'      => $revenue > 0 ? ($profit / $revenue) * 100 : 0.0,
            'roi_pct'         => $expenses > 0 ? ($profit / $expenses) * 100 : 0.0,
            'area_ha'         => $area,
            'harvest_kg'      => $harvestKg,
            'cost_per_ha'     => $area > 0 ? $expenses / $area : null,
            'cost_per_kg'     => $harvestKg > 0 ? $expenses / $harvestKg : null,
            'active_crops'    => $activeCrops,
            'livestock'       => $livestock,
            'inventory_value' => $inventoryValue,
        ];
    }

    /** At-a-glance production planning figures for the main dashboard. */
    public function productionSnapshot(): array
    {
        $crops = $this->rows(
            "SELECT c.id, c.name, c.variety,
                    COALESCE(c.area_ha, NULLIF(f.size, 0), 0) AS area_ha,
                    c.expected_yield, COALESCE(NULLIF(c.yield_unit, ''), 'kg') AS yield_unit,
                    c.expected_harvest_date, f.name AS field_name,
                    COALESCE(h.harvested, 0) AS harvested
             FROM crops c
             LEFT JOIN fields f ON f.id = c.field_id
             LEFT JOIN (
                 SELECT crop_id, SUM(quantity) AS harvested
                 FROM harvest_records
                 GROUP BY crop_id
             ) h ON h.crop_id = c.id
             WHERE COALESCE(c.status, 'active') = 'active'
             ORDER BY c.name ASC, c.id ASC"
        );
        $species = $this->rows(
            "SELECT lower(COALESCE(type, 'Other')) AS species,
                    SUM(COALESCE(flock_size, 1)) AS quantity
             FROM livestock
             WHERE COALESCE(status, 'active') = 'active'
             GROUP BY lower(COALESCE(type, 'Other'))
             ORDER BY quantity DESC, species ASC"
        );
        $groupedCrops = [];
        $totalArea = 0.0;
        $expectedHarvest = 0.0;
        foreach ($crops as $crop) {
            $area = (float) ($crop['area_ha'] ?? 0);
            $totalArea += $area;
            $key = strtolower(trim((string) ($crop['name'] ?? ''))) ?: 'other';
            if (!isset($groupedCrops[$key])) {
                $groupedCrops[$key] = $crop;
                $groupedCrops[$key]['area_ha'] = 0.0;
                $groupedCrops[$key]['expected_yield'] = null;
                $groupedCrops[$key]['harvested'] = 0.0;
            }
            $groupedCrops[$key]['area_ha'] += $area;
            $groupedCrops[$key]['harvested'] += (float) ($crop['harvested'] ?? 0);
            if ($crop['expected_yield'] !== null && $crop['expected_yield'] !== '') {
                $expected = $area * (float) $crop['expected_yield'];
                $expectedHarvest += $expected;
                $groupedCrops[$key]['expected_yield'] = (float) ($groupedCrops[$key]['expected_yield'] ?? 0) + $expected;
            }
        }
        foreach ($groupedCrops as &$crop) {
            if ($crop['expected_yield'] !== null && (float) $crop['area_ha'] > 0) {
                $crop['expected_yield'] = (float) $crop['expected_yield'] / (float) $crop['area_ha'];
            }
            $crop['variety'] = '';
            $crop['field_name'] = '';
        }
        unset($crop);
        $totalAnimals = 0;
        $cattle = 0;
        foreach ($species as &$row) {
            $row['quantity'] = (int) $row['quantity'];
            $totalAnimals += $row['quantity'];
            if (in_array($row['species'], ['cattle', 'cow', 'cows', 'bull', 'bulls', 'bovine', 'heifer', 'heifers', 'calf', 'calves'], true)) {
                $cattle += $row['quantity'];
            }
        }
        unset($row);
        return [
            'crops' => array_values($groupedCrops),
            'species' => $species,
            'total_area_ha' => $totalArea,
            'expected_harvest' => $expectedHarvest,
            'total_animals' => $totalAnimals,
            'cattle' => $cattle,
        ];
    }

    /** Revenue vs cost for each crop (dimension columns on the ledger). */
    public function cropEnterprise(): array
    {
        return $this->rows(
            "SELECT c.id, c.name,
                    COALESCE(s.revenue,0) AS revenue,
                    COALESCE(f.cost,0) AS cost,
                    COALESCE(s.revenue,0) - COALESCE(f.cost,0) AS profit
             FROM crops c
             LEFT JOIN (SELECT crop_id, SUM(amount) AS revenue FROM sales_records
                        WHERE crop_id IS NOT NULL AND sale_date BETWEEN ? AND ? GROUP BY crop_id) s ON s.crop_id = c.id
             LEFT JOIN (SELECT crop_id, SUM(amount) AS cost FROM finance_records
                        WHERE type = 'expense' AND crop_id IS NOT NULL AND date BETWEEN ? AND ? GROUP BY crop_id) f ON f.crop_id = c.id
             ORDER BY profit DESC, c.name ASC",
            [$this->from, $this->to, $this->from, $this->to]
        );
    }

    /** Revenue vs cost for each animal (or the herd when not tagged). */
    public function livestockEnterprise(): array
    {
        return $this->rows(
            "SELECT l.id,
                    COALESCE(NULLIF(l.tag_no,''), l.type || ' #' || l.id) AS name,
                    COALESCE(s.revenue,0) AS revenue,
                    COALESCE(f.cost,0) AS cost,
                    COALESCE(s.revenue,0) - COALESCE(f.cost,0) AS profit
             FROM livestock l
             LEFT JOIN (SELECT livestock_id, SUM(amount) AS revenue FROM sales_records
                        WHERE livestock_id IS NOT NULL AND sale_date BETWEEN ? AND ? GROUP BY livestock_id) s ON s.livestock_id = l.id
             LEFT JOIN (SELECT livestock_id, SUM(amount) AS cost FROM finance_records
                        WHERE type = 'expense' AND livestock_id IS NOT NULL AND date BETWEEN ? AND ? GROUP BY livestock_id) f ON f.livestock_id = l.id
             WHERE l.status IS NULL OR l.status <> 'dead'
             ORDER BY profit DESC, name ASC",
            [$this->from, $this->to, $this->from, $this->to]
        );
    }

    /** Running cost posted against each machine. */
    public function equipmentCosts(): array
    {
        return $this->rows(
            "SELECT e.id, e.name, COALESCE(SUM(f.amount),0) AS cost
             FROM equipment e
             LEFT JOIN finance_records f ON f.equipment_id = e.id
                  AND f.type = 'expense' AND f.date BETWEEN ? AND ?
             GROUP BY e.id, e.name
             ORDER BY cost DESC, e.name ASC",
            [$this->from, $this->to]
        );
    }

    /** Yield against crop area, with the expected benchmark when recorded. */
    public function yieldRows(): array
    {
        return $this->rows(
            "SELECT c.id, c.name,
                    COALESCE(c.area_ha,0) AS area_ha,
                    COALESCE(c.expected_yield,0) AS expected_yield,
                    COALESCE(h.qty,0) AS harvested,
                    CASE WHEN COALESCE(c.area_ha,0) > 0 THEN COALESCE(h.qty,0) / c.area_ha END AS yield_per_ha
             FROM crops c
             LEFT JOIN (SELECT crop_id, SUM(quantity) AS qty FROM harvest_records
                        WHERE harvest_date BETWEEN ? AND ? GROUP BY crop_id) h ON h.crop_id = c.id
             ORDER BY c.name ASC",
            [$this->from, $this->to]
        );
    }

    /** Stock on hand and its value. */
    public function inventoryRows(): array
    {
        return $this->rows(
            "SELECT name, category, quantity, unit, unit_cost, expiry_date,
                    COALESCE(quantity,0) * COALESCE(unit_cost,0) AS value
             FROM inventory
             ORDER BY value DESC NULLS LAST, name ASC
             LIMIT 200"
        );
    }

    /** Latest market price per crop, with the previous observation for trend. */
    public function marketPrices(): array
    {
        return $this->rows(
            "SELECT m.crop_name, m.price, m.market_date,
                    (SELECT m2.price FROM market_data m2
                     WHERE m2.crop_name = m.crop_name AND m2.market_date < m.market_date
                     ORDER BY m2.market_date DESC LIMIT 1) AS prev_price
             FROM market_data m
             WHERE m.market_date = (SELECT MAX(m3.market_date) FROM market_data m3 WHERE m3.crop_name = m.crop_name)
             ORDER BY m.crop_name ASC"
        );
    }

    /** Profitability broken down by field (revenue vs posted cost). */
    public function profitByField(): array
    {
        $revenue = $this->rows(
            "SELECT field_id, COALESCE(SUM(amount),0) AS revenue
             FROM sales_records WHERE field_id IS NOT NULL AND sale_date BETWEEN ? AND ?
             GROUP BY field_id",
            [$this->from, $this->to]
        );
        $cost = $this->rows(
            "SELECT field_id, COALESCE(SUM(amount),0) AS cost
             FROM finance_records WHERE type = 'expense' AND field_id IS NOT NULL AND date BETWEEN ? AND ?
             GROUP BY field_id",
            [$this->from, $this->to]
        );
        $labour = $this->rows(
            "SELECT field_id, COALESCE(SUM(COALESCE(labour_cost,0)),0) AS labour
             FROM tasks WHERE field_id IS NOT NULL AND due_date BETWEEN ? AND ?
             GROUP BY field_id",
            [$this->from, $this->to]
        );
        $harvest = $this->rows(
            "SELECT fh.field_id, COALESCE(SUM(fh.yield_quantity),0) AS harvested
             FROM field_history fh WHERE fh.field_id IS NOT NULL
               AND COALESCE(fh.end_date, fh.start_date) BETWEEN ? AND ?
             GROUP BY fh.field_id",
            [$this->from, $this->to]
        );
        $revMap = []; foreach ($revenue as $r) $revMap[(int)$r['field_id']] = (float)$r['revenue'];
        $costMap = []; foreach ($cost as $r) $costMap[(int)$r['field_id']] = (float)$r['cost'];
        $labMap = []; foreach ($labour as $r) $labMap[(int)$r['field_id']] = (float)$r['labour'];
        $harvMap = []; foreach ($harvest as $r) $harvMap[(int)$r['field_id']] = (float)$r['harvested'];

        $fields = $this->rows("SELECT id, name FROM fields ORDER BY name ASC");
        $out = [];
        foreach ($fields as $f) {
            $fid = (int) $f['id'];
            $r = $revMap[$fid] ?? 0.0;
            $c = ($costMap[$fid] ?? 0.0) + ($labMap[$fid] ?? 0.0);
            $profit = $r - $c;
            $out[] = [
                'field_id'   => $fid,
                'name'       => $f['name'],
                'revenue'    => $r,
                'cost'       => $c,
                'profit'     => $profit,
                'margin_pct' => $r > 0 ? ($profit / $r) * 100 : 0.0,
                'cost_per_kg'=> ($harvMap[$fid] ?? 0) > 0 ? $c / $harvMap[$fid] : null,
                'harvested'  => $harvMap[$fid] ?? 0.0,
            ];
        }
        return $out;
    }

    /** Lifetime profitability per animal. */
    public function profitByAnimal(): array
    {
        $animals = $this->rows(
            "SELECT id, tag_no, type, breed, purchase_price, purchase_date, status, dob
             FROM livestock ORDER BY id ASC"
        );
        $feedCost = $this->rows(
            "SELECT livestock_id, COALESCE(SUM(COALESCE(cost,0)),0) AS cost
             FROM feed_records GROUP BY livestock_id"
        );
        $treatCost = $this->rows(
            "SELECT livestock_id, COALESCE(SUM(COALESCE(cost,0)),0) AS cost
             FROM animal_treatments GROUP BY livestock_id"
        );
        $vaccCost = $this->rows(
            "SELECT livestock_id, COALESCE(SUM(COALESCE(cost,0)),0) AS cost
             FROM vaccinations GROUP BY livestock_id"
        );
        $revenue = $this->rows(
            "SELECT livestock_id, COALESCE(SUM(amount),0) AS revenue, MIN(sale_date) AS sale_date
             FROM sales_records WHERE livestock_id IS NOT NULL GROUP BY livestock_id"
        );
        $feedMap = []; foreach ($feedCost as $r) $feedMap[(int)$r['livestock_id']] = (float)$r['cost'];
        $treatMap = []; foreach ($treatCost as $r) $treatMap[(int)$r['livestock_id']] = (float)$r['cost'];
        $vaccMap = []; foreach ($vaccCost as $r) $vaccMap[(int)$r['livestock_id']] = (float)$r['cost'];
        $revMap = []; $saleMap = [];
        foreach ($revenue as $r) { $revMap[(int)$r['livestock_id']] = (float)$r['revenue']; $saleMap[(int)$r['livestock_id']] = $r['sale_date']; }

        $out = [];
        foreach ($animals as $a) {
            $aid = (int) $a['id'];
            $purchase = (float) ($a['purchase_price'] ?? 0);
            $feed = $feedMap[$aid] ?? 0.0;
            $treat = $treatMap[$aid] ?? 0.0;
            $vacc = $vaccMap[$aid] ?? 0.0;
            $cost = $purchase + $feed + $treat + $vacc;
            $rev = $revMap[$aid] ?? 0.0;
            $profit = $rev - $cost;
            $daysToMarket = null;
            if (!empty($a['purchase_date']) && !empty($saleMap[$aid])) {
                $d1 = strtotime((string)$a['purchase_date']);
                $d2 = strtotime((string)$saleMap[$aid]);
                if ($d1 && $d2 && $d2 >= $d1) $daysToMarket = (int) round(($d2 - $d1) / 86400);
            }
            $out[] = [
                'livestock_id'  => $aid,
                'tag_no'        => $a['tag_no'],
                'type'          => $a['type'],
                'breed'         => $a['breed'],
                'status'        => $a['status'],
                'purchase_cost' => $purchase,
                'feed_cost'     => $feed,
                'treatment_cost'=> $treat,
                'vaccine_cost'  => $vacc,
                'total_cost'    => $cost,
                'revenue'       => $rev,
                'profit'        => $profit,
                'margin_pct'    => $rev > 0 ? ($profit / $rev) * 100 : 0.0,
                'sale_date'     => $saleMap[$aid] ?? null,
                'days_to_market'=> $daysToMarket,
            ];
        }
        return $out;
    }

    /** Products approaching their expiry date. */
    public function expiringInventory(): array
    {
        return $this->rows(
            "SELECT name, quantity, unit, expiry_date
             FROM inventory
             WHERE expiry_date IS NOT NULL AND expiry_date <= CURRENT_DATE + INTERVAL '30 days'
             ORDER BY expiry_date ASC LIMIT 50"
        );
    }

    private function rows(string $sql, array $params = []): array
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('AnalyticsService: ' . $e->getMessage());
            return [];
        }
    }

    private function scalar(string $sql, array $params = [])
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchColumn();
        } catch (\Throwable $e) {
            error_log('AnalyticsService: ' . $e->getMessage());
            return 0;
        }
    }

    private function validDate($v): ?string
    {
        if (!is_string($v) || trim($v) === '') return null;
        $ts = strtotime($v);
        return $ts === false ? null : date('Y-m-d', $ts);
    }
}
