<?php
/**
 * FarmConnectionService (Wave 1B) — mutual-accept graph between farms.
 * All methods are farm-scoped and role-gated by the caller.
 */
class FarmConnectionService
{
    private $pdo;

    public function __construct($pdo) { $this->pdo = $pdo; }

    private function farmIdForUser($userId) {
        $st = $this->pdo->prepare("SELECT farm_id FROM farm_members WHERE user_id = ? LIMIT 1");
        $st->execute([(int)$userId]);
        $v = $st->fetchColumn();
        if ($v !== false && $v !== null) return (int)$v;
        // Fallback: farms owned/created by the user? Use first farm.
        $st = $this->pdo->prepare("SELECT id FROM farms ORDER BY id LIMIT 1");
        $st->execute();
        $v = $st->fetchColumn();
        return $v === false ? null : (int)$v;
    }

    public function searchFarms($userId, $district, $q) {
        $district = trim((string)$district);
        $q = trim((string)$q);
        $sql = "SELECT id, name, district, region FROM farms WHERE 1=1";
        $params = [];
        if ($district !== '') { $sql .= " AND LOWER(COALESCE(district,'')) = LOWER(?)"; $params[] = $district; }
        if ($q !== '') { $sql .= " AND (LOWER(name) LIKE LOWER(?) OR LOWER(COALESCE(region,'')) LIKE LOWER(?))"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $sql .= " ORDER BY name ASC LIMIT 50";
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll() ?: [];
    }

    public function requestConnection($userId, $targetFarmId, $role) {
        if (!in_array($role, ['admin','manager','farm-owner','owner'], true)) {
            throw new Exception('Only owners/managers can request connections.');
        }
        $fromFarm = $this->farmIdForUser($userId);
        if ($fromFarm === null) throw new Exception('No farm for user.');
        $targetFarmId = (int)$targetFarmId;
        if ($targetFarmId === $fromFarm) throw new Exception('Cannot connect a farm to itself.');
        // existing?
        $st = $this->pdo->prepare("SELECT id, status FROM farm_connections WHERE (requester_farm_id=? AND target_farm_id=?) OR (requester_farm_id=? AND target_farm_id=?)");
        $st->execute([$fromFarm, $targetFarmId, $targetFarmId, $fromFarm]);
        $existing = $st->fetch();
        if ($existing) {
            if ($existing['status'] === 'blocked') throw new Exception('Connection is blocked.');
            return (int)$existing['id'];
        }
        $st = $this->pdo->prepare("INSERT INTO farm_connections (requester_farm_id, target_farm_id, status, requested_by) VALUES (?,?, 'pending', ?) RETURNING id");
        $st->execute([$fromFarm, $targetFarmId, $userId]);
        $id = (int)$st->fetchColumn();
        AuditHelper::log($this->pdo, 'human', 'farm_connection_requested', 'farm_connections', $id, ['from'=>$fromFarm,'to'=>$targetFarmId], $userId);
        return $id;
    }

    public function decide($userId, $connId, $decision, $role) {
        if (!in_array($role, ['admin','manager','farm-owner','owner'], true)) {
            throw new Exception('Only owners/managers can decide connections.');
        }
        $connId = (int)$connId;
        $st = $this->pdo->prepare("SELECT * FROM farm_connections WHERE id = ?");
        $st->execute([$connId]);
        $c = $st->fetch();
        if (!$c) throw new Exception('Connection not found.');
        $myFarm = $this->farmIdForUser($userId);
        if ((int)$c['target_farm_id'] !== $myFarm && (int)$c['requester_farm_id'] !== $myFarm) {
            throw new Exception('Not your connection.');
        }
        // only target farm decides accept/decline
        if ((int)$c['target_farm_id'] !== $myFarm) {
            throw new Exception('Only the target farm can decide.');
        }
        $decision = strtolower(trim($decision));
        if (!in_array($decision, ['accepted','declined','blocked'], true)) throw new Exception('Invalid decision.');
        $st = $this->pdo->prepare("UPDATE farm_connections SET status=?, decided_by=?, decided_at=NOW() WHERE id=?");
        $st->execute([$decision, $userId, $connId]);
        AuditHelper::log($this->pdo, 'human', 'farm_connection_' . $decision, 'farm_connections', $connId, [], $userId);
        return true;
    }

    public function listConnections($userId) {
        $myFarm = $this->farmIdForUser($userId);
        $st = $this->pdo->prepare("SELECT fc.*, fr.name AS requester_name, ft.name AS target_name
            FROM farm_connections fc
            JOIN farms fr ON fr.id = fc.requester_farm_id
            JOIN farms ft ON ft.id = fc.target_farm_id
            WHERE fc.requester_farm_id = ? OR fc.target_farm_id = ?
            ORDER BY fc.created_at DESC");
        $st->execute([$myFarm, $myFarm]);
        return $st->fetchAll() ?: [];
    }
}
