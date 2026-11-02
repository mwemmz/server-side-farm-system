<?php
$cp = $data;
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Farm Credit Passport</h1>
<?php if (empty($cp)): ?>
    <div class="glass-card p-6"><p class="text-sm text-red-700">This passport link is invalid, expired, or revoked.</p></div>
<?php else:
    $s = $cp['score'] ?? [];
    $score = (int) ($s['score'] ?? 0);
    $band = (string) ($s['band'] ?? 'bronze');
    $factors = $s['factors'] ?? [];
?>
    <div class="glass-card p-6 mb-6 text-center">
        <div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Score</div>
        <div class="mt-2 text-5xl font-extrabold text-emerald-700"><?php echo $score; ?></div>
        <div class="mt-1 text-sm font-semibold text-slate-600"><?php echo ucfirst($band); ?> band</div>
        <div class="mt-2 text-xs text-slate-400">Computed <?php echo htmlspecialchars((string) ($s['computed_at'] ?? '')); ?></div>
    </div>
    <div class="glass-card p-4 sm:p-6 mb-6">
        <h2 class="text-lg font-semibold mb-3">Factor breakdown</h2>
        <ul class="space-y-2">
            <?php foreach ($factors as $f): ?>
            <li class="flex items-center justify-between text-sm">
                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars(str_replace('_',' ',$f['factor'])); ?></span>
                <span class="text-slate-500"><?php echo (int)$f['score']; ?> × <?php echo $f['weight']; ?> = <?php echo (int)$f['contribution']; ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <p class="text-xs text-slate-400">Farmer-owned, consent-based, explainable and revocable. No personal data is shown.</p>
<?php endif; ?>