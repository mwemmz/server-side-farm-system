<?php
/**
 * CommsService (Wave 1C/2A) — intranet + extranet conversations & messages.
 * Enforces farm scoping: a user may only access conversations whose farm_id
 * matches their farm_members.farm_id (intranet). Extranet requires an
 * accepted farm_connections row.
 */
class CommsService
{
    private $pdo;
    public function __construct($pdo) { $this->pdo = $pdo; }

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

    private function assertMember($userId, $convId) {
        $st = $this->pdo->prepare("SELECT c.* FROM conversations c
            JOIN conversation_members m ON m.conversation_id = c.id
            WHERE c.id = ? AND m.user_id = ?");
        $st->execute([(int)$convId, (int)$userId]);
        $c = $st->fetch();
        if (!$c) throw new Exception('Conversation not found or access denied.');
        return $c;
    }

    private function assertIntranetScope($userId, $conv) {
        if ($conv['scope'] !== 'intranet') return;
        $myFarm = $this->farmIdForUser($userId);
        if ($myFarm === null || (int)$conv['farm_id'] !== $myFarm) {
            throw new Exception('Access denied: intranet conversation belongs to another farm.');
        }
    }

    private function hasAcceptedConnection($farmA, $farmB) {
        $st = $this->pdo->prepare("SELECT 1 FROM farm_connections
            WHERE status='accepted'
              AND ((requester_farm_id=? AND target_farm_id=?) OR (requester_farm_id=? AND target_farm_id=?))");
        $st->execute([$farmA, $farmB, $farmB, $farmA]);
        return (bool)$st->fetchColumn();
    }

    // --- conversations ---
    public function listConversations($userId, $scope) {
        $myFarm = $this->farmIdForUser($userId);
        if ($scope === 'intranet') {
            $st = $this->pdo->prepare("SELECT c.* FROM conversations c
                JOIN conversation_members m ON m.conversation_id = c.id
                WHERE c.scope='intranet' AND c.farm_id = ? AND m.user_id = ?
                ORDER BY c.created_at DESC");
            $st->execute([$myFarm, $userId]);
        } else {
            $st = $this->pdo->prepare("SELECT c.* FROM conversations c
                JOIN conversation_members m ON m.conversation_id = c.id
                WHERE c.scope='extranet' AND m.user_id = ?
                ORDER BY c.created_at DESC");
            $st->execute([$userId]);
        }
        return $st->fetchAll() ?: [];
    }

    public function createConversation($userId, $scope, $title, $memberIds, $kind = 'group') {
        $scope = $scope === 'extranet' ? 'extranet' : 'intranet';
        $myFarm = $this->farmIdForUser($userId);
        if ($scope === 'intranet' && $myFarm === null) throw new Exception('No farm for user.');

        // Extranet: require accepted connection for each invited member's farm.
        if ($scope === 'extranet') {
            foreach ($memberIds as $mid) {
                $otherFarm = $this->farmIdForUser($mid);
                if ($otherFarm === null || !$this->hasAcceptedConnection($myFarm, $otherFarm)) {
                    throw new Exception('Extranet conversation requires an accepted farm connection.');
                }
            }
        }

        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare("INSERT INTO conversations (scope, kind, farm_id, title, created_by) VALUES (?,?,?,?,?) RETURNING id");
            $st->execute([$scope, $kind, $scope === 'intranet' ? $myFarm : null, $title, $userId]);
            $convId = (int)$st->fetchColumn();
            // add creator + members
            $members = array_unique(array_merge([(int)$userId], array_map('intval', (array)$memberIds)));
            $ins = $this->pdo->prepare("INSERT INTO conversation_members (conversation_id, user_id, member_role) VALUES (?,?,?) ON CONFLICT DO NOTHING");
            foreach ($members as $mid) {
                $ins->execute([$convId, $mid, $mid === (int)$userId ? 'owner' : 'member']);
            }
            $this->pdo->commit();
            AuditHelper::log($this->pdo, 'human', 'conversation_created', 'conversations', $convId, ['scope'=>$scope,'kind'=>$kind], $userId);
            return $convId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function broadcast($userId, $role, $title, $body) {
        if (!in_array($role, ['admin','manager','farm-owner','owner'], true)) {
            throw new Exception('Only owners/managers can broadcast.');
        }
        $myFarm = $this->farmIdForUser($userId);
        if ($myFarm === null) throw new Exception('No farm for user.');

        // Neon pooler reuses connections — a prior aborted transaction poisons
        // the connection. Issue a ROLLBACK using a direct PDO query that bypasses
        // the PDO transaction state machine entirely, then proceed without wrapping
        // in a transaction (broadcast inserts are idempotent enough without one).
        try {
            $this->pdo->query("ROLLBACK");
        } catch (\Throwable $ignored) {}

        $st = $this->pdo->prepare("INSERT INTO conversations (scope, kind, farm_id, title, is_public, created_by) VALUES ('intranet','broadcast',?,?,FALSE,?) RETURNING id");
        $st->execute([$myFarm, $title, $userId]);
        $convId = (int)$st->fetchColumn();

        // add all farm members
        $members = $this->pdo->prepare("SELECT user_id FROM farm_members WHERE farm_id = ?");
        $members->execute([$myFarm]);
        $ins = $this->pdo->prepare("INSERT INTO conversation_members (conversation_id, user_id, member_role) VALUES (?,?,?) ON CONFLICT DO NOTHING");
        foreach ($members->fetchAll(\PDO::FETCH_COLUMN) as $mid) {
            $ins->execute([$convId, (int)$mid, 'member']);
        }
        // ensure creator is a member
        $ins->execute([$convId, (int)$userId, 'owner']);

        // post the broadcast body as a system message
        $this->pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body, message_type) VALUES (?,?,?,'system')")
            ->execute([$convId, $userId, $body]);

        AuditHelper::log($this->pdo, 'human', 'intranet_broadcast', 'conversations', $convId, ['title' => $title], $userId);
        return $convId;
    }

    // --- messages ---
    public function listMessages($userId, $convId, $since = null) {
        $conv = $this->assertMember($userId, $convId);
        $this->assertIntranetScope($userId, $conv);
        $sql = "SELECT m.*, u.username AS sender_name FROM messages m LEFT JOIN users u ON u.id = m.sender_id
            WHERE m.conversation_id = ? AND m.deleted_at IS NULL";
        $params = [$convId];
        if ($since) { $sql .= " AND m.id > ?"; $params[] = (int)$since; }
        $sql .= " ORDER BY m.id ASC LIMIT 200";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    }

    public function postMessage($userId, $convId, $body) {
        $conv = $this->assertMember($userId, $convId);
        $this->assertIntranetScope($userId, $conv);
        $body = trim((string)$body);
        if ($body === '') throw new Exception('Message body is required.');
        if (mb_strlen($body) > 4000) throw new Exception('Message too long (max 4000 chars).');
        $st = $this->pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?,?,?) RETURNING id");
        $st->execute([$convId, $userId, $body]);
        $id = (int)$st->fetchColumn();
        AuditHelper::log($this->pdo, 'human', 'message_posted', 'messages', $id, ['conversation_id'=>$convId], $userId);
        return $id;
    }

    public function markRead($userId, $convId, $lastMessageId) {
        $this->assertMember($userId, $convId);
        $st = $this->pdo->prepare("UPDATE conversation_members SET last_read_message_id = ? WHERE conversation_id = ? AND user_id = ?");
        $st->execute([(int)$lastMessageId, (int)$convId, (int)$userId]);
        return true;
    }

    // --- task integration: auto-create conversation ref_type='task' ---
    public function ensureTaskConversation($userId, $taskId, $title, $memberIds = []) {
        // check existing
        $st = $this->pdo->prepare("SELECT id FROM conversations WHERE ref_type='task' AND ref_id=? LIMIT 1");
        $st->execute([(int)$taskId]);
        $existing = $st->fetchColumn();
        if ($existing) return (int)$existing;
        $myFarm = $this->farmIdForUser($userId);
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare("INSERT INTO conversations (scope, kind, farm_id, title, ref_type, ref_id, created_by) VALUES ('intranet','group',?,?, 'task', ?, ?) RETURNING id");
            $st->execute([$myFarm, $title, (int)$taskId, $userId]);
            $convId = (int)$st->fetchColumn();
            $members = array_unique(array_merge([(int)$userId], array_map('intval', (array)$memberIds)));
            $ins = $this->pdo->prepare("INSERT INTO conversation_members (conversation_id, user_id, member_role) VALUES (?,?,?) ON CONFLICT DO NOTHING");
            foreach ($members as $mid) {
                $ins->execute([$convId, $mid, 'member']);
            }
            $this->pdo->commit();
            return $convId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    // --- Wave 2A extranet moderation ---
    public function reportMessage($userId, $messageId, $reason) {
        $st = $this->pdo->prepare("INSERT INTO message_reports (message_id, reporter_id, reason) VALUES (?,?,?) RETURNING id");
        $st->execute([(int)$messageId, (int)$userId, trim((string)$reason)]);
        $id = (int)$st->fetchColumn();
        AuditHelper::log($this->pdo, 'human', 'message_reported', 'message_reports', $id, ['message_id'=>$messageId], $userId);
        return $id;
    }

    public function removeMessage($adminId, $messageId) {
        $st = $this->pdo->prepare("UPDATE messages SET deleted_at = NOW() WHERE id = ?");
        $st->execute([(int)$messageId]);
        // mark reports resolved
        $st = $this->pdo->prepare("UPDATE message_reports SET status='removed', resolved_by=?, resolved_at=NOW() WHERE message_id=?");
        $st->execute([(int)$adminId, (int)$messageId]);
        AuditHelper::log($this->pdo, 'human', 'message_removed', 'messages', (int)$messageId, [], $adminId);
        return true;
    }
}
