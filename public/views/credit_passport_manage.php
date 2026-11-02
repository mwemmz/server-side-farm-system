<?php
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
$success = SessionHelper::getFlash('success');
$score = $data['score'] ?? [];
$history = $data['history'] ?? [];
$shares = $data['shares'] ?? [];
$share = $data['share'] ?? null;
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">My Credit Passport</h1>
<?php if ($success): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($share): ?>
<div class="glass-card p-4 sm:p-6 mb-6 bg-emerald-50 border border-emerald-200">
    <h2 class="text-lg font-semibold mb-2 text-emerald-800">New share link</h2>
    <p class="text-sm text-emerald-700 break-all">index.php?module=CreditPassport&token=<?php echo htmlspecialchars($share['token']); ?></p>
    <p class="text-xs text-emerald-600 mt-1">Expires <?php echo htmlspecialchars($share['expires_at']); ?></p>
</div>
<?php endif; ?>

<div class="glass-card p-6 mb-6 text-center">
    <div class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Score</div>
    <div class="mt-2 text-5xl font-extrabold text-emerald-700"><?php echo (int)($score['score'] ?? 0); ?></div>
    <div class="mt-1 text-sm font-semibold text-slate-600"><?php echo ucfirst((string)($score['band'] ?? 'bronze')); ?> band</div>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold mb-3">Factor breakdown</h2>
    <ul class="space-y-2">
        <?php foreach (($score['factors'] ?? []) as $f): ?>
        <li class="flex items-center justify-between text-sm">
            <span class="font-semibold text-slate-700"><?php echo htmlspecialchars(str_replace('_',' ',$f['factor'])); ?></span>
            <span class="text-slate-500"><?php echo (int)$f['score']; ?> × <?php echo $f['weight']; ?> = <?php echo (int)$f['contribution']; ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold mb-3">Share your passport</h2>
    <form method="post" class="space-y-3">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
        <div class="flex flex-wrap gap-3 text-sm">
            <label class="flex items-center gap-1"><input type="checkbox" name="scope[]" value="score" checked> Score</label>
            <label class="flex items-center gap-1"><input type="checkbox" name="scope[]" value="band" checked> Band</label>
            <label class="flex items-center gap-1"><input type="checkbox" name="scope[]" value="factors" checked> Factors</label>
        </div>
        <div class="flex items-center gap-2">
            <label class="text-sm text-slate-600">Expires in (days)</label>
            <input type="number" name="expires_days" value="30" min="1" max="365" class="w-20 px-2 py-1 text-sm border rounded-lg">
            <button type="submit" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-bold hover:bg-indigo-700">Create share link</button>
        </div>
    </form>
</div>

<?php if (!empty($shares)): ?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold mb-3">Existing shares</h2>
    <ul class="space-y-2">
        <?php foreach ($shares as $sh): ?>
        <li class="flex items-center justify-between text-sm bg-white/50 border border-white/60 rounded-xl px-3.5 py-2.5">
            <span class="font-mono text-xs text-slate-600"><?php echo htmlspecialchars($sh['token']); ?></span>
            <span class="text-xs text-slate-400">expires <?php echo htmlspecialchars($sh['expires_at']); ?> <?php echo $sh['revoked'] ? '(revoked)' : ''; ?></span>
            <?php if (!$sh['revoked']): ?>
            <form method="post" action="index.php?module=CreditPassport" class="inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="hidden" name="revoke_id" value="<?php echo (int)$sh['id']; ?>">
                <button type="submit" class="text-xs font-bold text-red-600 hover:text-red-700">Revoke</button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<p class="text-xs text-slate-400">Farmer-owned, consent-based, explainable and revocable. No personal data is shown on the public page.</p>