<?php
/**
 * PracticeScoringService (Wave 2B) — Farm Credit Passport scoring.
 * Weights configurable via config. Traceability factor exposed as 0 until Wave 3 ships.
 */
class PracticeScoringService
{
    private $pdo;
    private $weights;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->weights = [
            'record_keeping'        => 0.20,
            'treatment_compliance'  => 0.15,
            'crop_rotation'         => 0.15,
            'yield_trend'           => 0.15,
            'sales_regularity'      => 0.15,
            'expense_discipline'    => 0.10,
            'traceability'          => 0.10,
        ];
    }

    private function safeScalar($sql, $params = []) {
        try { $st = $this->pdo->prepare($sql); $st->execute($params); return $st->fetchColumn(); }
        catch (Throwable $e) { return 0; }
    }
    private function safeRows($sql, $params = []) {
        try { $st = $this->pdo->prepare($sql); $st->execute($params); return $st->fetchAll() ?: []; }
        catch (Throwable $e) { return []; }
    }

    public function compute($userId) {
        $uid = (int)$userId;
        // record-keeping consistency: count of distinct record types present
        $tables = ['crops','livestock','inventory','finance_records','sales_records','harvest_records'];
        $present = 0;
        foreach ($tables as $t) {
            $n = (int)$this->safeScalar("SELECT COUNT(*) FROM {$t} WHERE 1=1");
            if ($n > 0) $present++;
        }
        $rk = min(100, (int) round(($present / count($tables)) * 100));

        // treatment compliance: % of health incidents resolved
        $totalInc = (int)$this->safeScalar("SELECT COUNT(*) FROM health_incidents");
        $resolved = (int)$this->safeScalar("SELECT COUNT(*) FROM health_incidents WHERE LOWER(COALESCE(status,'')) IN ('resolved','treated','closed')");
        $tc = $totalInc > 0 ? (int) round(($resolved / $totalInc) * 100) : 100;

        // crop rotation adherence: distinct crops per field history
        $rotRows = $this->safeRows("SELECT field_id, COUNT(DISTINCT COALESCE(crop_id,0)) AS c FROM field_history GROUP BY field_id");
        $rotScore = 0;
        if ($rotRows) {
            $avg = 0; foreach ($rotRows as $r) $avg += (int)$r['c'];
            $avg = $avg / count($rotRows);
            $rotScore = min(100, (int) round($avg * 25));
        } else {
            $rotScore = 50; // neutral when no data
        }

        // yield trend: presence of harvest records
        $harvestN = (int)$this->safeScalar("SELECT COUNT(*) FROM harvest_records");
        $yt = min(100, $harvestN * 10);

        // sales regularity: number of sales in last 90 days
        $salesN = (int)$this->safeScalar("SELECT COUNT(*) FROM sales_records WHERE sale_date >= CURRENT_DATE - INTERVAL '90 days'");
        $sr = min(100, $salesN * 10);

        // expense discipline: finance records with categories
        $finN = (int)$this->safeScalar("SELECT COUNT(*) FROM finance_records");
        $catN = (int)$this->safeScalar("SELECT COUNT(*) FROM finance_records WHERE category IS NOT NULL AND category <> ''");
        $ed = $finN > 0 ? (int) round(($catN / $finN) * 100) : 100;

        // traceability: 0 until Wave 3 ships
        $tr = 0;

        $factorScores = [
            'record_keeping' => $rk,
            'treatment_compliance' => $tc,
            'crop_rotation' => $rotScore,
            'yield_trend' => $yt,
            'sales_regularity' => $sr,
            'expense_discipline' => $ed,
            'traceability' => $tr,
        ];
        $evidence = [
            'record_keeping' => ['tables_with_data' => $present, 'total_tables' => count($tables)],
            'treatment_compliance' => ['total_incidents' => $totalInc, 'resolved' => $resolved],
            'crop_rotation' => ['fields_tracked' => count($rotRows)],
            'yield_trend' => ['harvest_records' => $harvestN],
            'sales_regularity' => ['sales_last_90d' => $salesN],
            'expense_discipline' => ['finance_records' => $finN, 'categorised' => $catN],
            'traceability' => ['status' => 'pending Wave 3'],
        ];

        $score = 0;
        foreach ($this->weights as $k => $w) {
            $score += ($factorScores[$k] ?? 0) * $w;
        }
        $score = (int) round($score);
        $band = $score >= 85 ? 'platinum' : ($score >= 70 ? 'gold' : ($score >= 50 ? 'silver' : 'bronze'));

        $factors = [];
        foreach ($this->weights as $k => $w) {
            $factors[] = [
                'factor' => $k,
                'score' => $factorScores[$k] ?? 0,
                'weight' => $w,
                'contribution' => (int) round(($factorScores[$k] ?? 0) * $w),
                'evidence' => $evidence[$k] ?? [],
            ];
        }

        // persist
        $st = $this->pdo->prepare("INSERT INTO practice_scores (user_id, score, band, factors, computed_by) VALUES (?,?,?,?, 'system')");
        $st->execute([$uid, $score, $band, json_encode($factors)]);

        return ['score' => $score, 'band' => $band, 'factors' => $factors, 'computed_at' => date('Y-m-d H:i:s')];
    }

    public function history($userId, $days = 90) {
        $st = $this->pdo->prepare("SELECT score, band, computed_at FROM practice_scores WHERE user_id = ? AND computed_at >= NOW() - (? || ' days')::interval ORDER BY computed_at ASC");
        $st->execute([(int)$userId, (int)$days]);
        return $st->fetchAll() ?: [];
    }

    public function createShare($userId, $scope, $expiresDays) {
        $token = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime("+".(int)$expiresDays." days"));
        $st = $this->pdo->prepare("INSERT INTO credit_passport_shares (user_id, token, scope, expires_at) VALUES (?,?,?,?) RETURNING id");
        $st->execute([(int)$userId, $token, json_encode($scope), $expires]);
        $id = (int)$st->fetchColumn();
        AuditHelper::log($this->pdo, 'human', 'credit_passport_share_created', 'credit_passport_shares', $id, ['scope'=>$scope,'expires'=>$expires], $userId);
        return ['id' => $id, 'token' => $token, 'expires_at' => $expires];
    }

    public function revokeShare($userId, $shareId) {
        $st = $this->pdo->prepare("UPDATE credit_passport_shares SET revoked = TRUE WHERE id = ? AND user_id = ?");
        $st->execute([(int)$shareId, (int)$userId]);
        AuditHelper::log($this->pdo, 'human', 'credit_passport_share_revoked', 'credit_passport_shares', (int)$shareId, [], $userId);
        return true;
    }

    public function viewShared($token) {
        $st = $this->pdo->prepare("SELECT * FROM credit_passport_shares WHERE token = ? AND revoked = FALSE AND (expires_at IS NULL OR expires_at > NOW())");
        $st->execute([$token]);
        $share = $st->fetch();
        if (!$share) return null;
        // latest score
        $st = $this->pdo->prepare("SELECT score, band, factors, computed_at FROM practice_scores WHERE user_id = ? ORDER BY computed_at DESC LIMIT 1");
        $st->execute([(int)$share['user_id']]);
        $score = $st->fetch();
        AuditHelper::log($this->pdo, 'human', 'credit_passport_viewed', 'credit_passport_shares', (int)$share['id'], ['token'=>$token], null);
        return ['score' => $score, 'scope' => $share['scope'], 'expires_at' => $share['expires_at']];
    }
}
