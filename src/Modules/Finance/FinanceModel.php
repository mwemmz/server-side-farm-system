<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class FinanceModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'finance_records'); }

    public function getAll($f = []) {
        $where = [];
        $args = [];
        if (!empty($f['type']) && in_array($f['type'], ['income', 'expense'], true)) {
            $where[] = "type = ?"; $args[] = $f['type'];
        }
        if (!empty($f['category'])) {
            $where[] = "category = ?"; $args[] = $f['category'];
        }
        if (!empty($f['farm_id'])) {
            $where[] = "farm_id = ?"; $args[] = (int) $f['farm_id'];
        }
        if (!empty($f['payment_status'])) {
            $where[] = "COALESCE(payment_status,'paid') = ?"; $args[] = $f['payment_status'];
        }
        if (!empty($f['date_from'])) {
            $where[] = "date >= ?"; $args[] = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where[] = "date <= ?"; $args[] = $f['date_to'];
        }
        $sql = "SELECT * FROM finance_records";
        if ($where) { $sql .= " WHERE " . implode(' AND ', $where); }
        $sql .= " ORDER BY date DESC, id DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    /** Per-category subtotals for the current filter. */
    public function groupTotals($f = []) {
        $where = [];
        $args = [];
        if (!empty($f['type']) && in_array($f['type'], ['income', 'expense'], true)) {
            $where[] = "type = ?"; $args[] = $f['type'];
        }
        if (!empty($f['farm_id'])) { $where[] = "farm_id = ?"; $args[] = (int) $f['farm_id']; }
        if (!empty($f['payment_status'])) { $where[] = "COALESCE(payment_status,'paid') = ?"; $args[] = $f['payment_status']; }
        if (!empty($f['date_from'])) { $where[] = "date >= ?"; $args[] = $f['date_from']; }
        if (!empty($f['date_to'])) { $where[] = "date <= ?"; $args[] = $f['date_to']; }
        $sql = "SELECT type, COALESCE(NULLIF(category,''),'Uncategorised') AS cat, SUM(amount) AS total, COUNT(*) AS n
                FROM finance_records";
        if ($where) { $sql .= " WHERE " . implode(' AND ', $where); }
        $sql .= " GROUP BY type, COALESCE(NULLIF(category,''),'Uncategorised') ORDER BY type, total DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    /** Unpaid or overdue records (for the "needs attention" views). */
    public function getUnpaidOverdue() {
        return $this->pdo->query(
            "SELECT * FROM finance_records
             WHERE COALESCE(payment_status,'paid') IN ('unpaid','partial')
             ORDER BY (due_date IS NULL), due_date ASC, date DESC"
        )->fetchAll();
    }

    public function loans() {
        return $this->pdo->query("SELECT * FROM loans ORDER BY status, created_at DESC")->fetchAll();
    }
    public function addLoan($d) {
        $balance = (isset($d['balance']) && $d['balance'] !== '') ? (float) $d['balance'] : (float) $d['principal'];
        $st = $this->pdo->prepare(
            "INSERT INTO loans (lender, principal, interest_rate, start_date, due_date, balance, status, notes)
             VALUES (?,?,?,?,?,?,?,?)");
        return $st->execute([
            trim($d['lender']), (float) $d['principal'], (float) ($d['interest_rate'] ?? 0),
            ($d['start_date'] ?: null), ($d['due_date'] ?: null),
            $balance, $d['status'] ?? 'active', trim($d['notes'] ?? ''),
        ]);
    }
    public function getLoan($id) {
        $st = $this->pdo->prepare("SELECT * FROM loans WHERE id = ?");
        $st->execute([(int) $id]);
        return $st->fetch();
    }
    public function repayLoan($id, $amount) {
        $st = $this->pdo->prepare(
            "UPDATE loans
             SET balance = GREATEST(balance - ?, 0),
                 status = CASE WHEN balance - ? <= 0 THEN 'paid' ELSE status END
             WHERE id = ?"
        );
        return $st->execute([(float) $amount, (float) $amount, (int) $id]);
    }
    public function loanTotals() {
        return $this->pdo->query(
            "SELECT COALESCE(SUM(principal),0) AS principal, COALESCE(SUM(balance),0) AS balance
             FROM loans WHERE status = 'active'")->fetch();
    }

    public function budgets() {
        return $this->pdo->query("SELECT * FROM budgets ORDER BY created_at DESC")->fetchAll();
    }
    public function addBudget($d) {
        $st = $this->pdo->prepare(
            "INSERT INTO budgets (category, period, planned_amount, notes) VALUES (?,?,?,?)");
        return $st->execute([trim($d['category']), trim($d['period'] ?? ''), (float) $d['planned_amount'], trim($d['notes'] ?? '')]);
    }
    public function budgetTotals() {
        return (float) $this->pdo->query("SELECT COALESCE(SUM(planned_amount),0) FROM budgets")->fetchColumn();
    }
}
?>
