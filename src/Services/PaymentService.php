<?php

require_once __DIR__ . '/../Helpers/AuditHelper.php';

class PaymentService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function record(array $input, int $userId): int
    {
        $saleId = filter_var($input['sale_id'] ?? null, FILTER_VALIDATE_INT);
        $amount = filter_var($input['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $method = trim((string) ($input['method'] ?? ''));
        if (!$saleId || $amount === false || $amount <= 0 || !in_array($method, ['cash', 'mobile-money', 'bank'], true)) {
            throw new InvalidArgumentException('Sale, payment method, and positive amount are required.');
        }
        $stmt = $this->pdo->prepare("INSERT INTO payment_records (sale_id, method, reference, amount, status, paid_at, created_by)
            VALUES (?, ?, ?, ?, ?, CASE WHEN ?='paid' THEN CURRENT_TIMESTAMP ELSE NULL END, ?) RETURNING id");
        $status = in_array($input['status'] ?? 'pending', ['pending', 'paid', 'failed'], true) ? $input['status'] : 'pending';
        $stmt->execute([$saleId, $method, trim((string) ($input['reference'] ?? '')), $amount, $status, $status, $userId]);
        $id = (int) $stmt->fetchColumn();
        AuditHelper::log($this->pdo, 'human', 'payment_recorded', 'payment', $id, $input, $userId);
        return $id;
    }
}
