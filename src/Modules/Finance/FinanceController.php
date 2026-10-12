<?php
require_once __DIR__ . '/FinanceModel.php';
require_once __DIR__ . '/../../Helpers/ValidationHelper.php';
require_once __DIR__ . '/../../Helpers/SessionHelper.php';

class FinanceController {
    private $model;
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; $this->model = new FinanceModel($pdo); }
    public function index() { return $this->model->getAll(); }

    public function add() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
            $errors = ValidationHelper::validateRequired(['type', 'amount', 'description', 'date'], $data);
            $e = ValidationHelper::number($data, 'amount', ['label' => 'Amount', 'positive' => true]);
            if ($e) $errors['amount'] = $e;

            if (empty($errors)) {
                $row = [
                    'type'        => $data['type'],
                    'amount'      => $data['amount'],
                    'description' => trim($data['description']),
                    'date'        => $data['date'],
                ];
                try {
                    if ($this->model->create($row)) {
                        SessionHelper::setFlash('success', 'Finance record added successfully!');
                        header('Location: index.php?module=Finance&action=manage');
                        exit;
                    }
                    $errors['general'] = "Failed to add finance record.";
                } catch (PDOException $e) {
                    $errors['general'] = "Could not save finance record.";
                }
            }
            return ['errors' => $errors, 'data' => $data, 'records' => $this->model->getAll()];
        }
        return ['records' => $this->model->getAll()];
    }

    // ---- Loans --------------------------------------------------------
    public function loans() {
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $_POST;
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
