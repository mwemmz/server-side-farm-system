<?php
/**
 * FarmTrustScoreService
 *
 * Computes a trust score (0-100) per farm — not per user.
 * The score is visible on the farm profile and on connection/extranet cards
 * so that other farms can assess reliability before connecting or trading.
 *
 * Factors (each scored 0-100, then weighted):
 *   - data_completeness  (0.20) — farm has fields, crops, livestock, inventory
 *   - sales_activity     (0.20) — recent sales records
 *   - financial_health   (0.15) — expense discipline (categorised records)
 *   - traceability       (0.15) — harvest batches with full chain
 *   - connection_standing(0.15) — accepted connections vs declined/blocked
 *   - compliance         (0.15) — vaccinations up to date, no unresolved incidents
 *
 * Bands: bronze < 40 | silver 40-64 | gold 65-84 | platinum 85+
 */
class FarmTrustScoreService
{
    private $pdo;
    private array $weights = [
        'data_completeness'   => 0.20,
        'sales_activity'      => 0.20,
        'financial_health'    => 0.15,
        'traceability'        => 0.15,
        'connection_standing' => 0.15,
        'compliance'          => 0.15,
    ];

    public function __construct($pdo) { $this->pdo = $pdo; }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /** Compute and persist a trust score for a farm. Returns the score record. */
    public function compute(int $farmId): array
    {
        $factors = [];
        $evidence = [];

        // 1. Data completeness
        [$dcScore, $dcEv] = $this->dataCompleteness($farmId);
        $factors['data_completeness'] = $dcScore;
        $evidence['data_completeness'] = $dcEv;

        // 2. Sales activity (last 90 days)
        [$saScore, $saEv] = $this->salesActivity($farmId);
        $factors['sales_activity'] = $saScore;
        $evidence['sales_activity'] = $saEv;

        // 3. Financial health
        [$fhScore, $fhEv] = $this->financialHealth($farmId);
        $factors['financial_health'] = $fhScore;
        $evidence['financial_health'] = $fhEv;

        // 4. Traceability
        [$trScore, $trEv] = $this->traceabilityScore($farmId);
        $factors['traceability'] = $trScore;
        $evidence['traceability'] = $trEv;

        // 5. Connection standing
        [$csScore, $csEv] = $this->connectionStanding($farmId);
        $factors['connection_standing'] = $csScore;
        $evidence['connection_standing'] = $csEv;

        // 6. Compliance
        [$coScore, $coEv] = $this->complianceScore($farmId);
        $factors['compliance'] = $coScore;
        $evidence['compliance'] = $coEv;

        // Weighted total
        $total = 0;
        foreach ($this->weights as $k => $w) {
            $total += ($factors[$k] ?? 0) * $w;
        }
        $score = (int) round($total);
        $band  = $this->band($score);

        // Build factor list
        $factorList = [];
        foreach ($this->weights as $k => $w) {
            $factorList[] = [
                'factor'       => $k,
                'label'        => $this->factorLabel($k),
                'score'        => $factors[$k],
                'weight'       => $w,
                'contribution' => (int) round($factors[$k] * $w),
                'evidence'     => $evidence[$k],
            ];
        }

        // Persist to farm_trust_scores
        $this->persist($farmId, $score, $band, $factorList);

        return [
            'farm_id'    => $farmId,
            'score'      => $score,
            'band'       => $band,
            'band_color' => $this->bandColor($band),
            'factors'    => $factorList,
            'computed_at'=> date('Y-m-d H:i:s'),
        ];
    }

    /** Get latest persisted score for a farm (without recomputing). */
    public function latest(int $farmId): ?array
    {
        try {
            $st = $this->pdo->prepare(
                "SELECT score, band, factors, computed_at
                 FROM farm_trust_scores WHERE farm_id = ?
                 ORDER BY computed_at DESC LIMIT 1"
            );
            $st->execute([$farmId]);
            $row = $st->fetch();
            if (!$row) return null;
            $row['band_color'] = $this->bandColor($row['band']);
            $row['factors']    = json_decode($row['factors'], true) ?? [];
            return $row;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Get or compute — return cached if < 24 hours old, else recompute. */
    public function getOrCompute(int $farmId): array
    {
        $latest = $this->latest($farmId);
        if ($latest && strtotime($latest['computed_at']) > strtotime('-24 hours')) {
            $latest['farm_id'] = $farmId;
            return $latest;
        }
        return $this->compute($farmId);
    }

    /** Leaderboard: top N farms by trust score. */
    public function leaderboard(int $limit = 10): array
    {
        try {
            $st = $this->pdo->prepare(
                "SELECT DISTINCT ON (fts.farm_id)
                    fts.farm_id, f.name AS farm_name, fts.score, fts.band, fts.computed_at
                 FROM farm_trust_scores fts
                 JOIN farms f ON f.id = fts.farm_id
                 ORDER BY fts.farm_id, fts.computed_at DESC"
            );
            $st->execute();
            $rows = $st->fetchAll();
            // sort by score desc
            usort($rows, fn($a, $b) => $b['score'] <=> $a['score']);
            return array_slice($rows, 0, $limit);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Score history for a farm (for charts). */
    public function history(int $farmId, int $days = 30): array
    {
        try {
            $st = $this->pdo->prepare(
                "SELECT score, band, computed_at
                 FROM farm_trust_scores
                 WHERE farm_id = ? AND computed_at >= NOW() - (?||' days')::interval
                 ORDER BY computed_at ASC"
            );
            $st->execute([$farmId, $days]);
            return $st->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // -----------------------------------------------------------------------
    // Factor computations
    // -----------------------------------------------------------------------

    private function dataCompleteness(int $farmId): array
    {
        $checks = [
            'fields'    => "SELECT COUNT(*) FROM fields WHERE farm_id = $farmId",
            'crops'     => "SELECT COUNT(*) FROM crops WHERE farm_id = $farmId",
            'livestock' => "SELECT COUNT(*) FROM livestock WHERE farm_id = $farmId",
            'inventory' => "SELECT COUNT(*) FROM inventory WHERE farm_id = $farmId",
            'equipment' => "SELECT COUNT(*) FROM equipment WHERE farm_id IS NOT NULL AND farm_id = $farmId",
        ];
        $present = 0;
        $counts  = [];
        foreach ($checks as $key => $sql) {
            $n = (int) $this->scalar($sql);
            $counts[$key] = $n;
            if ($n > 0) $present++;
        }
        $score = (int) round(($present / count($checks)) * 100);
        return [$score, $counts];
    }

    private function salesActivity(int $farmId): array
    {
        // Sales linked to farm via crops
        $recent = (int) $this->scalar(
            "SELECT COUNT(*) FROM sales_records sr
             JOIN crops c ON c.id = sr.crop_id
             WHERE c.farm_id = $farmId AND sr.sale_date >= CURRENT_DATE - INTERVAL '90 days'"
        );
        $total = (int) $this->scalar(
            "SELECT COUNT(*) FROM sales_records sr
             JOIN crops c ON c.id = sr.crop_id WHERE c.farm_id = $farmId"
        );
        // Also count sales with farm_id if that column exists
        $directRecent = (int) $this->scalar(
            "SELECT COUNT(*) FROM sales_records WHERE farm_id = $farmId AND sale_date >= CURRENT_DATE - INTERVAL '90 days'"
        );
        $allRecent = $recent + $directRecent;
        $score = min(100, $allRecent * 15); // 7+ sales in 90d = perfect score
        return [$score, ['recent_90d' => $allRecent, 'total' => $total + $directRecent]];
    }

    private function financialHealth(int $farmId): array
    {
        $total = (int) $this->scalar(
            "SELECT COUNT(*) FROM finance_records WHERE farm_id = $farmId"
        );
        $categorised = (int) $this->scalar(
            "SELECT COUNT(*) FROM finance_records WHERE farm_id = $farmId AND category IS NOT NULL AND category <> ''"
        );
        $score = $total > 0 ? (int) round(($categorised / $total) * 100) : 50;
        return [$score, ['total' => $total, 'categorised' => $categorised]];
    }

    private function traceabilityScore(int $farmId): array
    {
        $batches = (int) $this->scalar(
            "SELECT COUNT(*) FROM harvest_batches hb
             JOIN harvest_records hr ON hr.id = hb.harvest_record_id
             JOIN crops c ON c.id = hr.crop_id
             WHERE c.farm_id = $farmId"
        );
        $score = min(100, $batches * 20); // 5 batches = perfect
        return [$score, ['batches' => $batches]];
    }

    private function connectionStanding(int $farmId): array
    {
        $accepted = (int) $this->scalar(
            "SELECT COUNT(*) FROM farm_connections
             WHERE (requester_farm_id = $farmId OR target_farm_id = $farmId)
             AND status = 'accepted'"
        );
        $declined = (int) $this->scalar(
            "SELECT COUNT(*) FROM farm_connections
             WHERE (requester_farm_id = $farmId OR target_farm_id = $farmId)
             AND status IN ('declined','blocked')"
        );
        $total = $accepted + $declined;
        $score = $total === 0 ? 70 : (int) round(($accepted / $total) * 100);
        // Bonus: having any accepted connections at all
        if ($accepted > 0 && $score < 60) $score = 60;
        return [$score, ['accepted' => $accepted, 'declined_or_blocked' => $declined]];
    }

    private function complianceScore(int $farmId): array
    {
        // Vaccinations: % of livestock with a vaccination in the last year
        $totalLivestock = (int) $this->scalar(
            "SELECT COUNT(*) FROM livestock WHERE farm_id = $farmId"
        );
        $vaccinated = (int) $this->scalar(
            "SELECT COUNT(DISTINCT livestock_id) FROM vaccinations v
             JOIN livestock l ON l.id = v.livestock_id
             WHERE l.farm_id = $farmId AND v.vaccination_date >= CURRENT_DATE - INTERVAL '365 days'"
        );
        $vacRate = $totalLivestock > 0 ? ($vaccinated / $totalLivestock) : 1.0;

        // Health incidents: % resolved
        $totalInc = (int) $this->scalar(
            "SELECT COUNT(*) FROM health_incidents hi
             JOIN livestock l ON l.id = hi.livestock_id
             WHERE l.farm_id = $farmId"
        );
        $resolved = (int) $this->scalar(
            "SELECT COUNT(*) FROM health_incidents hi
             JOIN livestock l ON l.id = hi.livestock_id
             WHERE l.farm_id = $farmId
             AND LOWER(COALESCE(hi.status,'')) IN ('resolved','treated','closed')"
        );
        $incRate = $totalInc > 0 ? ($resolved / $totalInc) : 1.0;

        $score = (int) round((($vacRate + $incRate) / 2) * 100);
        return [$score, [
            'livestock' => $totalLivestock,
            'vaccinated_1yr' => $vaccinated,
            'incidents' => $totalInc,
            'resolved' => $resolved,
        ]];
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function scalar(string $sql): mixed
    {
        try {
            return $this->pdo->query($sql)->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function persist(int $farmId, int $score, string $band, array $factors): void
    {
        try {
            $this->pdo->prepare(
                "INSERT INTO farm_trust_scores (farm_id, score, band, factors)
                 VALUES (?, ?, ?, ?)"
            )->execute([$farmId, $score, $band, json_encode($factors)]);
        } catch (\Throwable $e) {
            // Non-fatal — table may not exist yet (migration runs on next request)
        }
    }

    public function band(int $score): string
    {
        if ($score >= 85) return 'platinum';
        if ($score >= 65) return 'gold';
        if ($score >= 40) return 'silver';
        return 'bronze';
    }

    public function bandColor(string $band): string
    {
        return match($band) {
            'platinum' => 'bg-indigo-100 text-indigo-800',
            'gold'     => 'bg-amber-100 text-amber-800',
            'silver'   => 'bg-slate-200 text-slate-700',
            default    => 'bg-orange-100 text-orange-800',
        };
    }

    public function bandEmoji(string $band): string
    {
        return match($band) {
            'platinum' => '💎',
            'gold'     => '🥇',
            'silver'   => '🥈',
            default    => '🥉',
        };
    }

    private function factorLabel(string $key): string
    {
        return match($key) {
            'data_completeness'   => 'Data Completeness',
            'sales_activity'      => 'Sales Activity',
            'financial_health'    => 'Financial Health',
            'traceability'        => 'Traceability',
            'connection_standing' => 'Connection Standing',
            'compliance'          => 'Compliance & Health',
            default               => ucwords(str_replace('_', ' ', $key)),
        };
    }
}
