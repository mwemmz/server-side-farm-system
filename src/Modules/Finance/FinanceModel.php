<?php
require_once __DIR__ . '/../BaseModuleModel.php';
class FinanceModel extends BaseModuleModel {
    public function __construct($pdo) { parent::__construct($pdo, 'finance_records'); }

    public function getAll() {
        return $this->pdo->query("SELECT * FROM finance_records ORDER BY date DESC, id DESC")->fetchAll();
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
