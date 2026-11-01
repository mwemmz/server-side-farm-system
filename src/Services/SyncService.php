<?php

require_once __DIR__ . '/../Helpers/AuditHelper.php';

class SyncService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function push(string $key, string $type, array $payload, int $userId): array
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{8,120}$/', $key) || $type !== 'sale') {
            throw new InvalidArgumentException('Invalid idempotency key or operation type.');
        }
        $existing = $this->pdo->prepare("SELECT result FROM sync_operations WHERE idempotency_key=?");
        $existing->execute([$key]);
        $row = $existing->fetch();
        if ($row) return json_decode($row['result'], true) ?: ['success' => true, 'duplicate' => true];
        $amount = filter_var($payload['amount'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($amount === false || $amount <= 0) throw new InvalidArgumentException('Sale amount must be positive.');
        $this->pdo->beginTransaction();
        try {
            $sale = $this->pdo->prepare("INSERT INTO sales_records (customer_name, amount, sale_date) VALUES (?, ?, ?) RETURNING id");
            $sale->execute([trim((string) ($payload['customer_name'] ?? 'Offline sale')), $amount, $payload['sale_date'] ?? date('Y-m-d')]);
            $result = ['success' => true, 'sale_id' => (int) $sale->fetchColumn(), 'idempotency_key' => $key];
            $stmt = $this->pdo->prepare("INSERT INTO sync_operations (idempotency_key,user_id,operation_type,payload,result) VALUES (?,?,?,?,?)");
            $stmt->execute([$key, $userId, $type, json_encode($payload), json_encode($result)]);
            $this->pdo->commit();
            AuditHelper::log($this->pdo, 'human', 'offline_sync_sale', 'sale', $result['sale_id'], $payload, $userId);
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
