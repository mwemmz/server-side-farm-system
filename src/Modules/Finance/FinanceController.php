<?php
require_once __DIR__ . '/FinanceModel.php';
require_once __DIR__ . '/FinanceService.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class FinanceController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new FinanceModel($pdo); }

    private function filters() {
        return [
            'type'           => $_GET['type'] ?? '',
            'category'       => $_GET['category'] ?? '',
            'farm_id'        => $_GET['farm_id'] ?? '',
            'payment_status' => $_GET['payment_status'] ?? '',
            'date_from'      => $_GET['date_from'] ?? '',
            'date_to'        => $_GET['date_to'] ?? '',
        ];
    }

    public function index() {
        $f = $this->filters();
        return [
            'records'  => $this->model->getAll($f),
            'groups'   => $this->model->groupTotals($f),
            'unpaid'   => $this->model->getUnpaidOverdue(),
            'filters'  => $f,
        ];
    }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['type', 'amount', 'description', 'date'], $data);
            if (!isset($errors['type']) && !ValidationHelper::inList($data['type'], ['income', 'expense'])) {
                $errors['type'] = "Type must be either Income or Expense.";
            }
            if (!isset($errors['date'])) {
                $e = ValidationHelper::dateNotFuture($data, 'date', 'Date');
                if ($e) $errors['date'] = $e;
            }
            $e = ValidationHelper::number($data, 'amount', ['label' => 'Amount', 'positive' => true]);
            if ($e) $errors['amount'] = $e;
            if (!isset($errors['farm_id'])) {
                $e = ValidationHelper::exists($this->pdo, 'farms', $data['farm_id'] ?? '', 'farm');
                if ($e) $errors['farm_id'] = $e;
            }

            if (empty($errors)) {
                $row = [
                    'type'           => $data['type'],
                    'category'       => (trim($data['category'] ?? '') !== '' ? trim($data['category']) : null),
                    'amount'         => $data['amount'],
                    'farm_id'        => (trim($data['farm_id'] ?? '') !== '' ? (int) $data['farm_id'] : null),
                    'payment_method' => (trim($data['payment_method'] ?? '') !== '' ? trim($data['payment_method']) : null),
                    'description'    => trim($data['description']),
                    'date'           => $data['date'],
                ];
                try {
                    if ($this->model->create($row)) {
                        SessionHelper::setFlash('success', 'Finance record added successfully!');
                        SessionHelper::flashCreated('Finance', $this->model->lastId(), ucfirst($data['type']) . ' — K' . number_format((float) $data['amount'], 2));
                        header('Location: index.php?module=Finance&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add finance record.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save finance record.";
                }
            }
            return array_merge($this->index(), ['errors' => $errors, 'data' => $data]);
        }
        return $this->index();
    }

    // ---- Loans --------------------------------------------------------
    public function loans() {
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;

            // Repayment branch: reduces the balance and posts a Loan repayment expense.
            if (isset($data['repay_amount']) && $data['repay_amount'] !== '') {
                $err = ValidationHelper::number($data, 'repay_amount', ['label' => 'Repayment amount', 'positive' => true]);
                if ($err) {
                    $errors['repay_amount'] = $err;
                }
                $loan = $this->model->getLoan((int) ($data['loan_id'] ?? 0));
                if (!$loan) {
                    $errors['general'] = 'Loan not found.';
                }
                if (empty($errors)) {
                    $applied = min((float) $data['repay_amount'], (float) $loan['balance']);
                    try {
                        $this->model->repayLoan((int) $loan['id'], $applied);
                        // Each repayment is a distinct expense, so no source link (no dedupe).
                        (new FinanceService($this->pdo))->postSafe(
                            'expense', 'Loan repayment', $applied,
                            'Loan repayment to ' . $loan['lender'],
                            null, null, null, date('Y-m-d'), 'paid'
                        );
                        SessionHelper::setFlash('success', 'Repayment of K' . number_format($applied, 2) . ' recorded.');
                        header('Location: index.php?module=Finance&action=loans');
                        exit;
                    } catch (PDOException $ex) {
                        $errors['general'] = 'Could not record repayment.';
                    }
                }
                return ['loans' => $this->model->loans(), 'totals' => $this->model->loanTotals(), 'errors' => $errors, 'data' => $data];
            }

            $errors = ValidationHelper::validateRequired(['lender', 'principal'], $data);
            $e = ValidationHelper::number($data, 'principal', ['label' => 'Principal', 'positive' => true]);
            if ($e) $errors['principal'] = $e;
            $e = ValidationHelper::number($data, 'interest_rate', ['label' => 'Interest rate', 'min' => 0, 'max' => 100]);
            if ($e) $errors['interest_rate'] = $e;
            if (!isset($errors['principal'])) {
                $e = ValidationHelper::number($data, 'balance', ['label' => 'Balance', 'min' => 0]);
                if ($e) $errors['balance'] = $e;
            }
            if (empty($errors)) {
                try {
                    $this->model->addLoan($data);
                    SessionHelper::setFlash('success', 'Loan recorded successfully.');
                    SessionHelper::flashCreated('Loan', $this->model->lastId(), trim($data['lender']), ['Principal' => 'K' . number_format((float) $data['principal'], 2)]);
                    header('Location: index.php?module=Finance&action=loans');
                    exit;
                } catch (PDOException $ex) {
                    $errors['general'] = 'Could not save loan.';
                }
            }
        }
        return ['loans' => $this->model->loans(), 'totals' => $this->model->loanTotals(), 'errors' => $errors, 'data' => $_POST];
    }

    // ---- Budgets ------------------------------------------------------
    public function budgets() {
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['category', 'planned_amount'], $data);
            $e = ValidationHelper::number($data, 'planned_amount', ['label' => 'Planned amount', 'min' => 0]);
            if ($e) $errors['planned_amount'] = $e;
            if (empty($errors)) {
                try {
                    $this->model->addBudget($data);
                    SessionHelper::setFlash('success', 'Budget line saved successfully.');
                    SessionHelper::flashCreated('Budget', $this->model->lastId(), trim($data['category']), ['Planned' => 'K' . number_format((float) $data['planned_amount'], 2)]);
                    header('Location: index.php?module=Finance&action=budgets');
                    exit;
                } catch (PDOException $ex) {
                    $errors['general'] = 'Could not save budget.';
                }
            }
        }
        return ['budgets' => $this->model->budgets(), 'total' => $this->model->budgetTotals(), 'errors' => $errors, 'data' => $_POST];
    }
}
?>
