<?php
require_once __DIR__ . '/PanelSupport.php';

/**
 * Financial Control Panel.
 * Real-time money picture from finance_records (+ sales_records):
 * current-month and year-to-date income/expense, running cash-flow trend,
 * top spend categories and a summary card. Aggregates across conditions.
 */
class FinancialPanelService {

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function panel($farmId = null) {
        $monthStart = date('Y-m-01');
        $yearStart  = date('Y-01-01');

        $month = $this->range($monthStart, date('Y-m-t'));
        $ytd   = $this->range($yearStart, date('Y-m-d'));

        $categories = $this->categories($yearStart);
        $cashflow   = $this->cashflow($yearStart);

        $netMonth = $month['income'] - $month['expense'];
        $netYtd   = $ytd['income'] - $ytd['expense'];

        return [
            'summary' => [
                'month_income'    => round($month['income'], 2),
                'month_expense'   => round($month['expense'], 2),
                'month_net'       => round($netMonth, 2),
                'month_net_color' => $this->netColor($netMonth),
                'ytd_income'      => round($ytd['income'], 2),
                'ytd_expense'     => round($ytd['expense'], 2),
                'ytd_net'         => round($netYtd, 2),
                'ytd_net_color'   => $this->netColor($netYtd),
            ],
            'cashflow' => $cashflow,
            'top_expenses' => array_slice($categories['expense'], 0, 5),
            'top_income'   => array_slice($categories['income'], 0, 5),
            'currency' => 'ZMW',
        ];
    }

    private function range($from, $to) {
        $stmt = $this->pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END),0) AS income,
                COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END),0) AS expense
             FROM finance_records WHERE date BETWEEN ? AND ?"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetch();
    }

    private function categories($yearStart) {
        $stmt = $this->pdo->prepare(
            "SELECT type, description, SUM(amount) AS total
             FROM finance_records WHERE date >= ?
             GROUP BY type, description ORDER BY total DESC"
        );
        $stmt->execute([$yearStart]);
        $rows = $stmt->fetchAll();
        $out = ['income' => [], 'expense' => []];
        foreach ($rows as $r) {
            $out[$r['type']][] = ['label' => $r['description'], 'amount' => round((float) $r['total'], 2)];
        }
        return $out;
    }

    private function cashflow($yearStart) {
        $stmt = $this->pdo->prepare(
            "SELECT to_char(date, 'YYYY-MM') AS month,
                    SUM(CASE WHEN type='income' THEN amount ELSE -amount END) AS net
             FROM finance_records WHERE date >= ?
             GROUP BY month ORDER BY month"
        );
        $stmt->execute([$yearStart]);
        return $stmt->fetchAll();
    }

    private function netColor($net) {
        if ($net >= 0) return 'green';
        if ($net >= -1000) return 'amber';
        return 'red';
    }
}
