<?php

require_once __DIR__ . '/../Helpers/AuditHelper.php';

class TraceabilityService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function createBatch(array $input, int $userId): array
    {
        $code = trim((string) ($input['batch_code'] ?? ''));
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $code) || $quantity === false || $quantity <= 0) {
            throw new InvalidArgumentException('Batch code and a positive quantity are required.');
        }
        $token = bin2hex(random_bytes(32));
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO harvest_batches (batch_code, field_id, crop_id, harvest_id, quantity, unit, harvest_date, public_token, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id");
            $stmt->execute([$code, $this->nullableId($input['field_id'] ?? null), $this->nullableId($input['crop_id'] ?? null),
                $this->nullableId($input['harvest_id'] ?? null), $quantity, trim((string) ($input['unit'] ?? '')),
                $input['harvest_date'] ?? null, $token, $userId]);
            $id = (int) $stmt->fetchColumn();
            $event = $this->pdo->prepare("INSERT INTO chain_events (batch_id, event_type, location, notes, created_by) VALUES (?, 'harvest', ?, ?, ?)");
            $event->execute([$id, trim((string) ($input['location'] ?? '')), trim((string) ($input['notes'] ?? '')), $userId]);
            $this->pdo->commit();
            AuditHelper::log($this->pdo, 'human', 'traceability_batch_created', 'harvest_batch', $id, $input, $userId);
            return ['id' => $id, 'token' => $token, 'public_url' => 'index.php?module=Traceability&action=public&token=' . rawurlencode($token)];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function publicHistory(string $token): ?array
    {
        $stmt = $this->pdo->prepare("SELECT b.batch_code, b.quantity, b.unit, b.harvest_date, f.name AS field_name, c.name AS crop_name
            FROM harvest_batches b LEFT JOIN fields f ON f.id=b.field_id LEFT JOIN crops c ON c.id=b.crop_id WHERE b.public_token=?");
        $stmt->execute([$token]);
        $batch = $stmt->fetch();
        if (!$batch) return null;
        $events = $this->pdo->prepare("SELECT event_type, location, notes, event_time FROM chain_events
            JOIN harvest_batches b ON b.id=chain_events.batch_id WHERE b.public_token=? ORDER BY event_time");
        $events->execute([$token]);
        $batch['events'] = $events->fetchAll();
        return $batch;
    }

    private function nullableId(mixed $value): ?int
    {
        return is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0 ? (int) $value : null;
    }
}
