<?php
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../src/Services/FarmTrustScoreService.php';
$success     = SessionHelper::getFlash('success');
$farms       = $data['farms'] ?? [];
$farmId      = (int)($data['farmId'] ?? 0);
$trust       = $data['trust'] ?? null;
$leaderboard = $data['leaderboard'] ?? [];
$history     = $data['history'] ?? [];

$trustSvc = new FarmTrustScoreService($pdo);

function bandBadge(string $band): string {
    $colors = [
        'platinum' => 'bg-indigo-100 text-indigo-800 ring-indigo-300',
        'gold'     => 'bg-amber-100 text-amber-800 ring-amber-300',
        'silver'   => 'bg-slate-100 text-slate-700 ring-slate-300',
        'bronze'   => 'bg-orange-100 text-orange-800 ring-orange-300',
    ];
    $emojis = ['platinum'=>'💎','gold'=>'🥇','silver'=>'🥈','bronze'=>'🥉'];
    $cls = $colors[$band] ?? 'bg-slate-100 text-slate-700 ring-slate-300';
    $em  = $emojis[$band] ?? '🏅';
    return "<span class='inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold ring-1 {$cls}'>{$em} " . ucfirst($band) . "</span>";
}
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-800 flex items-center gap-2">
            🏅 Farm Trust Score
        </h1>
        <p class="text-sm text-slate-500 mt-1">A 0-100 score per farm based on data completeness, sales, financial health, traceability, connections &amp; compliance.</p>
    </div>
    <form method="get" action="index.php" class="flex items-center gap-2">
        <input type="hidden" name="module" value="FarmTrust">
        <select name="farm_id" onchange="this.form.submit()"
                class="px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-green-400 outline-none">
            <option value="">— Select farm —</option>
            <?php foreach ($farms as $f): ?>
            <option value="<?php echo (int)$f['id']; ?>" <?php echo $farmId === (int)$f['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($f['name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($success): ?>
<div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-xl mb-5 text-sm">✅ <?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- Score card -->
    <div class="xl:col-span-2 space-y-5">
        <?php if ($trust && $farmId): ?>
        <!-- Hero score -->
        <div class="glass-card p-6 bg-gradient-to-br from-slate-800 to-slate-900 text-white rounded-2xl shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5">
                <div>
                    <div class="text-sm font-semibold text-slate-400 mb-1">Farm Trust Score</div>
                    <div class="flex items-end gap-3">
                        <div class="text-7xl font-extrabold leading-none text-white"><?php echo $trust['score']; ?></div>
                        <div class="text-2xl text-slate-400 pb-2">/100</div>
                    </div>
                </div>
                <div class="text-center">
                    <div class="text-5xl mb-2"><?php echo $trustSvc->bandEmoji($trust['band']); ?></div>
                    <div class="text-lg font-bold text-white uppercase tracking-wide"><?php echo htmlspecialchars(ucfirst($trust['band'])); ?></div>
                    <div class="text-xs text-slate-400 mt-0.5">Computed <?php echo htmlspecialchars(date('d M Y', strtotime($trust['computed_at']))); ?></div>
                </div>
            </div>
            <!-- Score bar -->
            <div class="w-full bg-white/10 rounded-full h-3 mb-2">
                <div class="h-3 rounded-full bg-gradient-to-r from-emerald-400 to-green-300 transition-all duration-700"
                     style="width: <?php echo $trust['score']; ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-slate-400">
                <span>Bronze</span><span>Silver (40)</span><span>Gold (65)</span><span>Platinum (85)</span>
            </div>
            <!-- Recompute -->
            <form method="post" action="index.php?module=FarmTrust&action=compute" class="mt-5">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="hidden" name="farm_id" value="<?php echo $farmId; ?>">
                <button type="submit" class="px-5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-sm font-bold text-white transition border border-white/20">
                    ↻ Recompute Score
                </button>
            </form>
        </div>

        <!-- Factor breakdown -->
        <div class="glass-card p-5">
            <h2 class="text-base font-bold text-slate-800 mb-4">Score Breakdown</h2>
            <div class="space-y-4">
                <?php foreach ($trust['factors'] as $f): ?>
                <?php $pct = (int)$f['score']; ?>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($f['label'] ?? ucwords(str_replace('_',' ',$f['factor']))); ?></span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">×<?php echo $f['weight']; ?> weight</span>
                            <span class="text-sm font-bold text-slate-800"><?php echo $pct; ?>/100</span>
                        </div>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all duration-500
                            <?php echo $pct >= 70 ? 'bg-emerald-500' : ($pct >= 40 ? 'bg-amber-400' : 'bg-red-400'); ?>"
                             style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <?php if (!empty($f['evidence'])): ?>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                        <?php foreach ($f['evidence'] as $ek => $ev): ?>
                        <span><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$ek))); ?>: <strong><?php echo htmlspecialchars((string)$ev); ?></strong></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- History chart -->
        <?php if (count($history) > 1): ?>
        <div class="glass-card p-5">
            <h2 class="text-base font-bold text-slate-800 mb-3">Score History (30 days)</h2>
            <canvas id="trustHistoryChart" height="80"></canvas>
            <script>
            (function(){
                var labels = <?php echo json_encode(array_map(fn($h) => date('M j', strtotime($h['computed_at'])), $history)); ?>;
                var scores = <?php echo json_encode(array_column($history, 'score')); ?>;
                new Chart(document.getElementById('trustHistoryChart').getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Trust Score',
                            data: scores,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,0.1)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: { min: 0, max: 100, title: { display: true, text: 'Score' } }
                        },
                        plugins: { legend: { display: false } }
                    }
                });
            })();
            </script>
        </div>
        <?php endif; ?>

        <?php elseif ($farmId): ?>
        <!-- No score yet -->
        <div class="glass-card p-8 text-center">
            <div class="text-5xl mb-3">🏅</div>
            <p class="font-semibold text-slate-700 mb-3">No trust score computed yet for this farm.</p>
            <form method="post" action="index.php?module=FarmTrust&action=compute">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="hidden" name="farm_id" value="<?php echo $farmId; ?>">
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:opacity-90 transition">
                    Compute Trust Score
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="glass-card p-8 text-center text-slate-400">
            <div class="text-5xl mb-3">🔍</div>
            <p class="font-semibold">Select a farm above to view its trust score.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Leaderboard sidebar -->
    <div class="space-y-5">
        <!-- What is this? -->
        <div class="glass-card p-5 bg-gradient-to-br from-emerald-50 to-green-50 border border-emerald-200">
            <h3 class="text-sm font-bold text-emerald-800 mb-2">What is the Trust Score?</h3>
            <p class="text-xs text-emerald-700 leading-relaxed">
                A data-driven score (0–100) computed from your farm's actual records:
            </p>
            <ul class="mt-2 space-y-1 text-xs text-emerald-700">
                <li>🗃️ <strong>Data Completeness</strong> — fields, crops, livestock, equipment on record</li>
                <li>📈 <strong>Sales Activity</strong> — recent sales transactions</li>
                <li>💰 <strong>Financial Health</strong> — categorised expense records</li>
                <li>🔍 <strong>Traceability</strong> — harvest batches with full chain</li>
                <li>🤝 <strong>Connection Standing</strong> — accepted farm connections</li>
                <li>💉 <strong>Compliance</strong> — vaccinations &amp; resolved health incidents</li>
            </ul>
            <div class="mt-3 text-xs text-emerald-600 font-semibold">
                Other farms can see your score when they view your profile on the extranet.
            </div>
        </div>

        <!-- Leaderboard -->
        <?php if (!empty($leaderboard)): ?>
        <div class="glass-card p-5">
            <h2 class="text-base font-bold text-slate-800 mb-3">🏆 Farm Leaderboard</h2>
            <div class="space-y-2">
                <?php foreach ($leaderboard as $i => $lf): ?>
                <div class="flex items-center gap-3 py-2 <?php echo $i < count($leaderboard)-1 ? 'border-b border-slate-100' : ''; ?>">
                    <div class="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0">
                        <?php echo $i + 1; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold text-slate-800 truncate">
                            <?php echo htmlspecialchars($lf['farm_name']); ?>
                        </div>
                        <div class="flex items-center gap-1 mt-0.5">
                            <?php echo bandBadge($lf['band']); ?>
                        </div>
                    </div>
                    <div class="text-lg font-extrabold <?php echo $lf['score'] >= 65 ? 'text-emerald-600' : ($lf['score'] >= 40 ? 'text-amber-600' : 'text-slate-500'); ?>">
                        <?php echo (int)$lf['score']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Band guide -->
        <div class="glass-card p-5">
            <h3 class="text-sm font-bold text-slate-700 mb-3">Band Guide</h3>
            <div class="space-y-2 text-sm">
                <div class="flex items-center gap-2">💎 <strong>Platinum</strong> <span class="text-slate-400 text-xs">(85–100) — Exemplary farm practices</span></div>
                <div class="flex items-center gap-2">🥇 <strong>Gold</strong> <span class="text-slate-400 text-xs">(65–84) — Strong &amp; reliable operation</span></div>
                <div class="flex items-center gap-2">🥈 <strong>Silver</strong> <span class="text-slate-400 text-xs">(40–64) — Good standing, room to grow</span></div>
                <div class="flex items-center gap-2">🥉 <strong>Bronze</strong> <span class="text-slate-400 text-xs">(0–39) — Getting started</span></div>
            </div>
        </div>
    </div>
</div>
