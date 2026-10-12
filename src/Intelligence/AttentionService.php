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

    public function __construct($pdo, $role = 'worker') {
        $this->pdo = $pdo;
        $this->isManager = in_array($role, ['admin', 'manager', 'superadmin'], true);
    }

    /** All attention items, most urgent first. */
    public function collect() {
        $items = [];
        $this->push($items, $this->overdueVaccinations());
        $this->push($items, $this->dueSoonVaccinations());
        $this->push($items, $this->openHealthIncidents());
        $this->push($items, $this->activeWithdrawals());
        $this->push($items, $this->lowStock());
        $this->push($items, $this->equipmentService());
        $this->push($items, $this->harvestsDue());
        if ($this->isManager) {
            $this->push($items, $this->unpaidFinance());
            $this->push($items, $this->loansDue());
            $this->push($items, $this->pendingLeave());
        }
        $rank = ['red' => 0, 'amber' => 1, 'slate' => 2];
        usort($items, function ($a, $b) use ($rank) {
            return ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9);
        });
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
             JOIN livestock l ON l.id = v.livestock_id
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
             JOIN livestock l ON l.id = v.livestock_id
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
             JOIN livestock l ON l.id = a.livestock_id
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
}
