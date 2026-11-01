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
