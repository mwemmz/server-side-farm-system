<?php
/**
 * AttentionService (Phase 3 / B5)
 *
 * One place that answers "what needs attention today?" so the Dashboard,
 * Notifications and the Assistant all speak with the same voice. Every query is
 * defensive: a missing table/column is logged, never thrown, so a partially
 * migrated database can never white-screen the dashboard.
 *
 * Each item is: [
 *   'key'      => stable id,
 *   'severity' => 'red' | 'amber' | 'slate',
 *   'icon'     => emoji-free short label,
 *   'title'    => short headline,
 *   'detail'   => supporting sentence,
 *   'count'    => int,
 *   'module'   => module to link to,
 *   'link'     => url,
 * ]
 */
class AttentionService {
    private $pdo;
    private $isManager;
    private $seesFinance;
    private $role;
    private $userId;
    private $ttl = 300; // seconds

    public function __construct($pdo, $role = 'worker') {
        $this->pdo = $pdo;
        $this->role = (string) $role;
        $this->isManager = in_array($this->role, ['admin', 'manager', 'superadmin'], true);
        $this->seesFinance = in_array($this->role, ['admin', 'accountant', 'farm-owner', 'superadmin'], true);
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $this->userId = (int) ($_SESSION['user_id'] ?? 0);
    }

    /** All attention items, most urgent first. Cached briefly per user to avoid
     *  re-running ~20 queries on every dashboard/assistant request. */
    public function collect() {
        $cache = $_SESSION['attention_cache'] ?? null;
        if (is_array($cache)
            && ($cache['user'] ?? 0) === $this->userId
            && ($cache['role'] ?? '') === $this->role
            && (time() - (int) ($cache['ts'] ?? 0)) < $this->ttl) {
            return (array) ($cache['items'] ?? []);
        }

        $items = [];
        $this->push($items, $this->overdueVaccinations());
        $this->push($items, $this->overdueVaccinations());
        $this->push($items, $this->dueSoonVaccinations());
        $this->push($items, $this->openHealthIncidents());
        $this->push($items, $this->activeWithdrawals());
        $this->push($items, $this->lowStock());
        $this->push($items, $this->expiringStock());
        $this->push($items, $this->equipmentService());
        $this->push($items, $this->harvestsDue());
        $this->push($items, $this->spraysDue());
        $this->push($items, $this->tasksDue());
        $this->push($items, $this->outbreaks());
        $this->push($items, $this->followupsDue());
        $this->push($items, $this->spoilageRisk());
        $this->push($items, $this->weatherRisk());
        if ($this->seesFinance) {
            $this->push($items, $this->unpaidFinance());
            $this->push($items, $this->loansDue());
        }
        if ($this->seesFinance || $this->isManager) {
            $this->push($items, $this->invoicesDue());
            $this->push($items, $this->contractsExpiring());
        }
        $this->push($items, $this->marketOpportunity());
        if ($this->isManager) {
            $this->push($items, $this->pendingLeave());
        }
        $rank = ['red' => 0, 'amber' => 1, 'slate' => 2];
        usort($items, function ($a, $b) use ($rank) {
            return ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
        });
        $_SESSION['attention_cache'] = [
            'user'  => $this->userId,
            'role'  => $this->role,
            'ts'    => time(),
            'items' => $items,
        ];
        return $items;
    }

    /** Total number of items (for the dashboard badge). */
    public function count() { return count($this->collect()); }

    /** Flat summary lines, e.g. for the Assistant. */
    public function summaryLines() {
        $lines = [];
        foreach ($this->collect() as $i) {
            $lines[] = $i['title'] . ' — ' . $i['detail'];
        }
        return $lines;
    }

    private function push(&$items, $list) {
        foreach ($list as $i) { $items[] = $i; }
    }

    private function rows($sql, $params = []) {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll();
        } catch (\Throwable $e) {
            error_log('AttentionService: ' . $e->getMessage());
            return [];
        }
    }

    private function scalar($sql, $params = []) {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchColumn();
        } catch (\Throwable $e) {
            error_log('AttentionService: ' . $e->getMessage());
            return 0;
        }
    }

    private function overdueVaccinations() {
        $rows = $this->rows(
            "SELECT v.next_due_date, v.vaccine_name, l.type, l.tag_no
             FROM vaccinations v
             JOIN livestock l ON l.id = v.livestock_id AND COALESCE(l.status,'active') = 'active'
             WHERE v.next_due_date IS NOT NULL AND v.next_due_date < CURRENT_DATE
             ORDER BY v.next_due_date ASC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        $first = $rows[0];
        return [[
            'key' => 'vacc_overdue', 'severity' => 'red',
            'title' => $n . ' vaccination' . ($n > 1 ? 's' : '') . ' overdue',
            'detail' => 'Earliest: ' . $first['vaccine_name'] . ' for ' . trim(ucfirst($first['type'] ?? '') . ' ' . ($first['tag_no'] ?? '')) . ' was due ' . $first['next_due_date'] . '.',
            'count' => $n, 'module' => 'Livestock', 'link' => 'index.php?module=Livestock&action=manage',
        ]];
    }

    private function dueSoonVaccinations() {
        $rows = $this->rows(
            "SELECT v.next_due_date, v.vaccine_name, l.type, l.tag_no
             FROM vaccinations v
             JOIN livestock l ON l.id = v.livestock_id AND COALESCE(l.status,'active') = 'active'
             WHERE v.next_due_date IS NOT NULL
               AND v.next_due_date >= CURRENT_DATE AND v.next_due_date <= CURRENT_DATE + INTERVAL '14 days'
             ORDER BY v.next_due_date ASC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        return [[
            'key' => 'vacc_soon', 'severity' => 'amber',
            'title' => $n . ' vaccination' . ($n > 1 ? 's' : '') . ' due in the next 14 days',
            'detail' => 'Next: ' . $rows[0]['vaccine_name'] . ' by ' . $rows[0]['next_due_date'] . '.',
            'count' => $n, 'module' => 'Livestock', 'link' => 'index.php?module=Livestock&action=manage',
        ]];
    }

    private function openHealthIncidents() {
        $n = (int) $this->scalar(
            "SELECT COUNT(*) FROM health_incidents WHERE status = 'open' AND LOWER(COALESCE(severity,'')) IN ('high','severe','critical')"
        );
        if ($n < 1) return [];
        return [[
            'key' => 'health_open', 'severity' => 'red',
            'title' => $n . ' high-severity health incident' . ($n > 1 ? 's' : '') . ' open',
            'detail' => 'Review and mark treated or resolved.',
            'count' => $n, 'module' => 'Crop', 'link' => 'index.php?module=Crop&action=manage',
        ]];
    }

    private function activeWithdrawals() {
        $rows = $this->rows(
            "SELECT a.withdrawal_ends, a.treatment_name, l.type, l.tag_no
             FROM animal_treatments a
             JOIN livestock l ON l.id = a.livestock_id AND COALESCE(l.status,'active') = 'active'
             WHERE a.withdrawal_ends IS NOT NULL AND a.withdrawal_ends >= CURRENT_DATE
             ORDER BY a.withdrawal_ends ASC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        return [[
            'key' => 'withdrawal', 'severity' => 'amber',
            'title' => $n . ' animal' . ($n > 1 ? 's' : '') . ' in withdrawal',
            'detail' => 'Do not sell milk/meat until the withdrawal clears (latest ' . $rows[0]['withdrawal_ends'] . ').',
            'count' => $n, 'module' => 'Livestock', 'link' => 'index.php?module=Livestock&action=manage',
        ]];
    }

    private function lowStock() {
        $rows = $this->rows(
            "SELECT name, quantity, unit, min_level FROM inventory
             WHERE min_level IS NOT NULL AND min_level > 0 AND COALESCE(quantity,0) <= min_level
             ORDER BY (min_level - COALESCE(quantity,0)) DESC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        return [[
            'key' => 'low_stock', 'severity' => 'amber',
            'title' => $n . ' item' . ($n > 1 ? 's' : '') . ' at or below minimum stock',
            'detail' => 'Lowest: ' . $rows[0]['name'] . ' (' . rtrim(rtrim(number_format((float) $rows[0]['quantity'], 2), '0'), '.') . ' ' . ($rows[0]['unit'] ?? '') . ' vs min ' . $rows[0]['min_level'] . ').',
            'count' => $n, 'module' => 'Inventory', 'link' => 'index.php?module=Inventory&action=manage',
        ]];
    }

    private function equipmentService() {
        $eff = "COALESCE(next_service_date, CASE WHEN last_service_date IS NOT NULL AND service_interval_days IS NOT NULL THEN last_service_date + service_interval_days END)";
        $overdue = (int) $this->scalar(
            "SELECT COUNT(*) FROM equipment WHERE $eff IS NOT NULL AND $eff < CURRENT_DATE"
        );
        $soon = (int) $this->scalar(
            "SELECT COUNT(*) FROM equipment WHERE $eff IS NOT NULL AND $eff >= CURRENT_DATE AND $eff <= CURRENT_DATE + INTERVAL '14 days'"
        );
        $out = [];
        if ($overdue > 0) {
            $out[] = [
                'key' => 'equip_overdue', 'severity' => 'red',
                'title' => $overdue . ' equipment service' . ($overdue > 1 ? 's' : '') . ' overdue',
                'detail' => 'Record the service or update the schedule.',
                'count' => $overdue, 'module' => 'Equipment', 'link' => 'index.php?module=Equipment&action=manage',
            ];
        }
        if ($soon > 0) {
            $out[] = [
                'key' => 'equip_soon', 'severity' => 'amber',
                'title' => $soon . ' equipment service' . ($soon > 1 ? 's' : '') . ' due soon',
                'detail' => 'Within the next 14 days.',
                'count' => $soon, 'module' => 'Equipment', 'link' => 'index.php?module=Equipment&action=manage',
            ];
        }
        return $out;
    }

    private function harvestsDue() {
        $rows = $this->rows(
            "SELECT c.name, c.expected_harvest_date
             FROM crops c
             WHERE c.expected_harvest_date IS NOT NULL
               AND c.expected_harvest_date >= CURRENT_DATE AND c.expected_harvest_date <= CURRENT_DATE + INTERVAL '7 days'
             ORDER BY c.expected_harvest_date ASC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        return [[
            'key' => 'harvest_soon', 'severity' => 'slate',
            'title' => $n . ' harvest' . ($n > 1 ? 's' : '') . ' due in the next 7 days',
            'detail' => 'Next: ' . $rows[0]['name'] . ' on ' . $rows[0]['expected_harvest_date'] . '.',
            'count' => $n, 'module' => 'Harvest', 'link' => 'index.php?module=Harvest&action=manage',
        ]];
    }

    private function unpaidFinance() {
        $rows = $this->rows(
            "SELECT COUNT(*) AS n,
                    SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURRENT_DATE THEN 1 ELSE 0 END) AS overdue
             FROM finance_records
             WHERE COALESCE(payment_status,'paid') <> 'paid'"
        );
        $n = $rows ? (int) $rows[0]['n'] : 0;
        if ($n < 1) return [];
        $overdue = $rows ? (int) $rows[0]['overdue'] : 0;
        return [[
            'key' => 'finance_unpaid', 'severity' => $overdue > 0 ? 'red' : 'amber',
            'title' => $n . ' unpaid finance record' . ($n > 1 ? 's' : '') . ($overdue > 0 ? " ($overdue overdue)" : ''),
            'detail' => 'Settle or reclassify the outstanding entries.',
            'count' => $n, 'module' => 'Finance', 'link' => 'index.php?module=Finance&action=manage',
        ]];
    }

    private function loansDue() {
        $rows = $this->rows(
            "SELECT COUNT(*) AS n, MIN(due_date) AS soonest
             FROM loans
             WHERE COALESCE(balance,0) > 0 AND due_date IS NOT NULL
               AND due_date <= CURRENT_DATE + INTERVAL '30 days'"
        );
        $n = $rows ? (int) $rows[0]['n'] : 0;
        if ($n < 1) return [];
        return [[
            'key' => 'loans_due', 'severity' => 'amber',
            'title' => $n . ' loan' . ($n > 1 ? 's' : '') . ' due within 30 days',
            'detail' => 'Earliest repayment: ' . $rows[0]['soonest'] . '.',
            'count' => $n, 'module' => 'Finance', 'link' => 'index.php?module=Finance&action=loans',
        ]];
    }

    private function pendingLeave() {
        $n = (int) $this->scalar("SELECT COUNT(*) FROM leave_requests WHERE LOWER(COALESCE(status,'')) = 'pending'");
        if ($n < 1) return [];
        return [[
            'key' => 'leave_pending', 'severity' => 'slate',
            'title' => $n . ' leave request' . ($n > 1 ? 's' : '') . ' awaiting approval',
            'detail' => 'Approve or reject them.',
            'count' => $n, 'module' => 'Labour', 'link' => 'index.php?module=Labour&action=leave',
        ]];
    }

    /** Stock that will expire within 30 days (already expired included). */
    private function expiringStock() {
        $rows = $this->rows(
            "SELECT name, quantity, unit, expiry_date FROM inventory
             WHERE expiry_date IS NOT NULL AND expiry_date <= CURRENT_DATE + INTERVAL '30 days'
             ORDER BY expiry_date ASC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        $today = date('Y-m-d');
        $expired = 0;
        foreach ($rows as $r) {
            if ((string) $r['expiry_date'] < $today) $expired++;
        }
        $first = $rows[0];
        return [[
            'key' => 'stock_expiry', 'severity' => $expired > 0 ? 'red' : 'amber',
            'title' => $n . ' stock item' . ($n > 1 ? 's' : '') . ' expiring'
                . ($expired > 0 ? " ($expired already expired)" : ' within 30 days'),
            'detail' => 'Earliest: ' . $first['name'] . ' on ' . $first['expiry_date'] . '. Use or dispose of it first.',
            'count' => $n, 'module' => 'Inventory', 'link' => 'index.php?module=Inventory&action=manage',
        ]];
    }

    /** Scheduled crop-protection sprays that are overdue or due in 3 days. */
    private function spraysDue() {
        $rows = $this->rows(
            "SELECT planned_date, product, target FROM spray_schedules
             WHERE COALESCE(status,'scheduled') = 'scheduled'
               AND planned_date <= CURRENT_DATE + INTERVAL '3 days'
             ORDER BY planned_date ASC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        $today = date('Y-m-d');
        $overdue = 0;
        foreach ($rows as $r) {
            if ((string) $r['planned_date'] < $today) $overdue++;
        }
        $first = $rows[0];
        $what = trim((string) ($first['product'] ?? ''));
        if ($what === '') $what = 'Spray';
        return [[
            'key' => 'spray_due', 'severity' => $overdue > 0 ? 'red' : 'amber',
            'title' => $n . ' spray' . ($n > 1 ? 's' : '') . ' scheduled'
                . ($overdue > 0 ? " ($overdue overdue)" : ' in the next 3 days'),
            'detail' => 'Next: ' . $what . ' on ' . $first['planned_date']
                . (trim((string) ($first['target'] ?? '')) !== '' ? ' for ' . $first['target'] : '') . '.',
            'count' => $n, 'module' => 'Ops', 'link' => 'index.php?module=Ops&view=spray_schedules',
        ]];
    }

    /** Open tasks that are overdue or due in 3 days. */
    private function tasksDue() {
        $rows = $this->rows(
            "SELECT title, due_date, priority FROM tasks
             WHERE COALESCE(status,'open') NOT IN ('completed','cancelled')
               AND due_date IS NOT NULL AND due_date <= CURRENT_DATE + INTERVAL '3 days'
             ORDER BY due_date ASC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        $today = date('Y-m-d');
        $overdue = 0;
        foreach ($rows as $r) {
            if ((string) $r['due_date'] < $today) $overdue++;
        }
        return [[
            'key' => 'tasks_due', 'severity' => $overdue > 0 ? 'red' : 'amber',
            'title' => $n . ' task' . ($n > 1 ? 's' : '') . ' due'
                . ($overdue > 0 ? " ($overdue overdue)" : ' in the next 3 days'),
            'detail' => 'Next: ' . $rows[0]['title'] . ' by ' . $rows[0]['due_date'] . '.',
            'count' => $n, 'module' => 'Ops', 'link' => 'index.php?module=Ops&view=tasks',
        ]];
    }

    /** Pest/disease incidents explicitly flagged as an outbreak. */
    private function outbreaks() {
        $n = (int) $this->scalar(
            "SELECT COUNT(*) FROM health_incidents
             WHERE COALESCE(outbreak_flag, FALSE) = TRUE
               AND LOWER(COALESCE(status,'')) NOT IN ('resolved','closed')"
        );
        if ($n < 1) return [];
        return [[
            'key' => 'outbreak', 'severity' => 'red',
            'title' => $n . ' outbreak' . ($n > 1 ? 's' : '') . ' flagged',
            'detail' => 'Isolate the affected area and confirm the control plan.',
            'count' => $n, 'module' => 'Ops', 'link' => 'index.php?module=Ops&view=treatment_followups',
        ]];
    }

    /** Pest/disease follow-up checks that are due. */
    private function followupsDue() {
        $rows = $this->rows(
            "SELECT issue_name, followup_due FROM health_incidents
             WHERE followup_due IS NOT NULL
               AND followup_due <= CURRENT_DATE + INTERVAL '3 days'
               AND LOWER(COALESCE(status,'')) NOT IN ('resolved','closed')
             ORDER BY followup_due ASC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        return [[
            'key' => 'followup_due', 'severity' => 'amber',
            'title' => $n . ' treatment follow-up' . ($n > 1 ? 's' : '') . ' due',
            'detail' => 'Check effectiveness for ' . ($rows[0]['issue_name'] ?? 'an incident')
                . ' by ' . $rows[0]['followup_due'] . '.',
            'count' => $n, 'module' => 'Ops', 'link' => 'index.php?module=Ops&view=treatment_followups',
        ]];
    }

    /** Stored produce approaching the end of its safe storage window. */
    private function spoilageRisk() {
        $rows = $this->rows(
            "SELECT quantity, grade, storage_end_date FROM stored_produce
             WHERE COALESCE(is_in_storage, TRUE) = TRUE
               AND storage_end_date IS NOT NULL
               AND storage_end_date <= CURRENT_DATE + INTERVAL '14 days'
             ORDER BY storage_end_date ASC LIMIT 50"
        );
        if (!$rows) return [];
        $n = count($rows);
        return [[
            'key' => 'spoilage_risk', 'severity' => 'amber',
            'title' => $n . ' stored batch' . ($n > 1 ? 'es' : '') . ' nearing end of storage life',
            'detail' => 'Sell, process or dispatch before ' . $rows[0]['storage_end_date'] . '.',
            'count' => $n, 'module' => 'Storage', 'link' => 'index.php?module=Storage&action=manage',
        ]];
    }

    /** Latest recorded weather, when it is hot/cold/wet enough to act on. */
    private function weatherRisk() {
        $rows = $this->rows(
            "SELECT temperature, humidity, weather_date FROM weather_records
             ORDER BY weather_date DESC LIMIT 1"
        );
        if (!$rows) return [];
        $w = $rows[0];
        $when = strtotime((string) ($w['weather_date'] ?? ''));
        if ($when === false || $when < strtotime('-2 days')) return [];

        $temp = $w['temperature'] !== null ? (float) $w['temperature'] : null;
        $humidity = $w['humidity'] !== null ? (float) $w['humidity'] : null;
        $out = [];
        if ($temp !== null && $temp >= 38) {
            $out[] = [
                'key' => 'weather_heat', 'severity' => 'red',
                'title' => 'Heat stress risk (' . rtrim(rtrim(number_format($temp, 1), '0'), '.') . '°C)',
                'detail' => 'Irrigate early/late and check livestock water and shade.',
                'count' => 1, 'module' => 'Weather', 'link' => 'index.php?module=Weather&action=manage',
            ];
        } elseif ($temp !== null && $temp <= 2) {
            $out[] = [
                'key' => 'weather_cold', 'severity' => 'red',
                'title' => 'Frost risk (' . rtrim(rtrim(number_format($temp, 1), '0'), '.') . '°C)',
                'detail' => 'Protect seedlings and vulnerable livestock overnight.',
                'count' => 1, 'module' => 'Weather', 'link' => 'index.php?module=Weather&action=manage',
            ];
        }
        if ($humidity !== null && $humidity >= 90 && $temp !== null && $temp >= 18 && $temp <= 32) {
            $out[] = [
                'key' => 'weather_disease', 'severity' => 'amber',
                'title' => 'High disease pressure (humidity ' . rtrim(rtrim(number_format($humidity, 0), '0'), '.') . '%)',
                'detail' => 'Warm, humid conditions favour fungal disease — scout crops today.',
                'count' => 1, 'module' => 'Crop', 'link' => 'index.php?module=Crop&action=manage',
            ];
        }
        return $out;
    }

    /** Invoices that are unpaid and due within 7 days. */
    private function invoicesDue() {
        $rows = $this->rows(
            "SELECT COUNT(*) AS n, MIN(due_date) AS soonest FROM invoices
             WHERE COALESCE(status,'unpaid') IN ('unpaid','partial','overdue')
               AND COALESCE(total_amount,0) > COALESCE(amount_paid,0)
               AND due_date IS NOT NULL AND due_date <= CURRENT_DATE + INTERVAL '7 days'"
        );
        $n = $rows ? (int) $rows[0]['n'] : 0;
        if ($n < 1) return [];
        return [[
            'key' => 'invoice_due', 'severity' => 'amber',
            'title' => $n . ' invoice' . ($n > 1 ? 's' : '') . ' due within 7 days',
            'detail' => 'Earliest due date: ' . $rows[0]['soonest'] . '. Follow up on payment.',
            'count' => $n, 'module' => 'Ops', 'link' => 'index.php?module=Ops&view=invoices',
        ]];
    }

    /** Active contracts ending within 30 days. */
    private function contractsExpiring() {
        $rows = $this->rows(
            "SELECT COUNT(*) AS n, MIN(end_date) AS soonest FROM contracts
             WHERE COALESCE(status,'active') = 'active'
               AND end_date IS NOT NULL
               AND end_date <= CURRENT_DATE + INTERVAL '30 days'"
        );
        $n = $rows ? (int) $rows[0]['n'] : 0;
        if ($n < 1) return [];
        return [[
            'key' => 'contract_expiry', 'severity' => 'amber',
            'title' => $n . ' contract' . ($n > 1 ? 's' : '') . ' ending within 30 days',
            'detail' => 'Earliest end date: ' . $rows[0]['soonest'] . '. Renew or close it out.',
            'count' => $n, 'module' => 'Ops', 'link' => 'index.php?module=Ops&view=contracts',
        ]];
    }

    /** Latest market quote that has risen meaningfully since the previous one. */
    private function marketOpportunity() {
        $rows = $this->rows(
            "SELECT m.crop_name, m.price,
                    (SELECT m2.price FROM market_data m2
                     WHERE m2.crop_name = m.crop_name AND m2.market_date < m.market_date
                     ORDER BY m2.market_date DESC LIMIT 1) AS prev_price
             FROM market_data m
             WHERE m.market_date = (SELECT MAX(m3.market_date) FROM market_data m3 WHERE m3.crop_name = m.crop_name)
             ORDER BY m.crop_name ASC LIMIT 100"
        );
        if (!$rows) return [];
        $risers = [];
        foreach ($rows as $r) {
            $price = (float) ($r['price'] ?? 0);
            $prev = (float) ($r['prev_price'] ?? 0);
            if ($price > 0 && $prev > 0 && $price >= $prev * 1.05) {
                $risers[] = ['name' => (string) $r['crop_name'], 'price' => $price, 'pct' => (($price - $prev) / $prev) * 100];
            }
        }
        if (!$risers) return [];
        usort($risers, function ($a, $b) { return $b['pct'] <=> $a['pct']; });
        $top = $risers[0];
        return [[
            'key' => 'market_opportunity', 'severity' => 'slate',
            'title' => count($risers) . ' crop' . (count($risers) > 1 ? 's' : '') . ' trading higher',
            'detail' => $top['name'] . ' is up ' . number_format($top['pct'], 1) . '% to '
                . number_format($top['price'], 2) . ' — consider selling.',
            'count' => count($risers), 'module' => 'Market', 'link' => 'index.php?module=Market&action=manage',
        ]];
    }
}
