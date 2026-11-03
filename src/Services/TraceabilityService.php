<?php
require_once __DIR__ . '/../Helpers/AuditHelper.php';

class TraceabilityService
{
    public function __construct(private PDO $pdo) {}

    public function createBatch(array $input, int $userId): array
    {
        $code = trim((string) ($input['batch_code'] ?? ''));
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!preg_match('/^[A-Za-z0-9._-]{3,80}$/', $code) || $quantity === false || $quantity <= 0) {
            throw new InvalidArgumentException('Batch code and a positive quantity are required.');
        }
        $farmId = $this->farmIdForUser($userId);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO harvest_batches (farm_id, field_id, harvest_record_id, batch_code, quantity, unit, quality_grade)
                VALUES (?,?,?,?,?,?,?) RETURNING id");
            $stmt->execute([
                $farmId,
                $this->nullableId($input['field_id'] ?? null),
                $this->nullableId($input['harvest_record_id'] ?? null),
                $code,
                $quantity,
                trim((string) ($input['unit'] ?? '')),
                trim((string) ($input['quality_grade'] ?? '')),
            ]);
            $id = (int) $stmt->fetchColumn();
            $event = $this->pdo->prepare("INSERT INTO batch_events (batch_id, event_type, ref_type, ref_id, location, notes, actor_id) VALUES (?,?,?,?,?,?,?)");
            $event->execute([$id, 'harvest', $input['ref_type'] ?? null, $this->nullableId($input['ref_id'] ?? null), trim((string) ($input['location'] ?? '')), trim((string) ($input['notes'] ?? '')), $userId]);
            $this->pdo->commit();
            AuditHelper::log($this->pdo, 'human', 'traceability_batch_created', 'harvest_batches', $id, $input, $userId);
            return ['id' => $id, 'batch_code' => $code, 'public_url' => 'index.php?module=Traceability&action=public&token=' . rawurlencode($code)];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function addEvent(int $batchId, array $input, int $userId): int
    {
        $type = trim((string) ($input['event_type'] ?? ''));
        if (!in_array($type, ['harvest','storage','dispatch','sale'], true)) {
            throw new InvalidArgumentException('Invalid event type.');
        }
        $st = $this->pdo->prepare("INSERT INTO batch_events (batch_id, event_type, ref_type, ref_id, location, notes, actor_id) VALUES (?,?,?,?,?,?,?) RETURNING id");
        $st->execute([$batchId, $type, $input['ref_type'] ?? null, $this->nullableId($input['ref_id'] ?? null), trim((string) ($input['location'] ?? '')), trim((string) ($input['notes'] ?? '')), $userId]);
        $id = (int) $st->fetchColumn();
        AuditHelper::log($this->pdo, 'human', 'traceability_event_added', 'batch_events', $id, ['batch_id'=>$batchId,'type'=>$type], $userId);
        return $id;
    }

    public function getBatch(int $id): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM harvest_batches WHERE id = ?");
        $st->execute([$id]);
        $b = $st->fetch();
        if (!$b) return null;
        $ev = $this->pdo->prepare("SELECT * FROM batch_events WHERE batch_id = ? ORDER BY event_at ASC");
        $ev->execute([$id]);
        $b['events'] = $ev->fetchAll() ?: [];
        return $b;
    }

    public function publicByCode(string $code): ?array
    {
        $st = $this->pdo->prepare("SELECT batch_code, quantity, unit, quality_grade, created_at FROM harvest_batches WHERE batch_code = ?");
        $st->execute([$code]);
        $b = $st->fetch();
        if (!$b) return null;
        $ev = $this->pdo->prepare("SELECT event_type, location, notes, event_at FROM batch_events be JOIN harvest_batches hb ON hb.id=be.batch_id WHERE hb.batch_code = ? ORDER BY event_at ASC");
        $ev->execute([$code]);
        $b['events'] = $ev->fetchAll() ?: [];
        return $b;
    }

    // Backward compat: publicHistory by token/code
    public function publicHistory(string $token): ?array
    {
        // try as batch_code first
        $r = $this->publicByCode($token);
        if ($r) return $r;
        return null;
    }

    private function farmIdForUser($userId) {
        $st = $this->pdo->prepare("SELECT farm_id FROM farm_members WHERE user_id = ? LIMIT 1");
        $st->execute([(int)$userId]);
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null) return (int)$v;
        $st = $this->pdo->prepare("SELECT id FROM farms ORDER BY id LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        return $v === false ? null : (int)$v;
    }

    private function nullableId(mixed $value): ?int
    {
        return is_scalar($value) && ctype_digit((string) $value) && (int) $value > 0 ? (int) $value : null;
    }
}
