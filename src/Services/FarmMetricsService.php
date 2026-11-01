<?php

class FarmMetricsService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function profitability(?int $fieldId = null, ?int $animalId = null): array
    {
        $where = [];
        $params = [];
        if ($fieldId !== null) {
            $where[] = 's.field_id = ?';
            $params[] = $fieldId;
        }
        if ($animalId !== null) {
            $where[] = 's.livestock_id = ?';
            $params[] = $animalId;
        }
        $condition = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(s.amount),0) AS revenue FROM sales_records s" . ($condition ?: ''));
        $stmt->execute($params);
        $row = $stmt->fetch() ?: ['revenue' => 0];
        $costWhere = [];
        $costParams = [];
        if ($fieldId !== null) { $costWhere[] = 'field_id = ?'; $costParams[] = $fieldId; }
        if ($animalId !== null) { $costWhere[] = 'livestock_id = ?'; $costParams[] = $animalId; }
        $costCondition = $costWhere ? ' AND ' . implode(' AND ', $costWhere) : '';
        $costStmt = $this->pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM finance_records WHERE type = 'expense'{$costCondition}");
        $costStmt->execute($costParams);
        $row['cost'] = $costStmt->fetchColumn() ?: 0;
        $row['profit'] = (float) $row['revenue'] - (float) $row['cost'];
        $row['margin'] = (float) $row['revenue'] > 0 ? round($row['profit'] / (float) $row['revenue'] * 100, 2) : 0;
        return $row;
    }
}
