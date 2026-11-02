<?php
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
$success = SessionHelper::getFlash('success');
$error = SessionHelper::getFlash('error');
$conns = $data['connections'] ?? [];
$search = $data['search'] ?? [];
$role = $data['role'] ?? 'worker';
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Farm Connections (Extranet)</h1>
<?php if ($success): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold mb-3">My connections</h2>
    <?php if (empty($conns)): ?><p class="text-sm text-slate-400">No connections yet.</p><?php else: ?>
    <ul class="space-y-2">
        <?php foreach ($conns as $c): ?>
        <li class="flex items-center justify-between bg-white/50 border border-white/60 rounded-xl px-3.5 py-2.5 text-sm">
            <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($c['requester_name']); ?> ↔ <?php echo htmlspecialchars($c['target_name']); ?></span>
            <span class="text-xs font-bold uppercase px-2 py-0.5 rounded-full <?php echo $c['status']==='accepted' ? 'bg-emerald-100 text-emerald-800' : ($c['status']==='pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'); ?>"><?php echo htmlspecialchars($c['status']); ?></span>
            <?php if ($c['status'] === 'pending'): ?>
            <form method="post" action="index.php?module=Connections&action=decide" class="flex gap-1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                <button name="decision" value="accepted" class="text-xs font-bold px-2 py-1 rounded bg-emerald-600 text-white">Accept</button>
                <button name="decision" value="declined" class="text-xs font-bold px-2 py-1 rounded bg-red-600 text-white">Decline</button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<?php if (in_array($role, ['admin','manager','farm-owner'], true)): ?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold mb-3">Find farms to connect</h2>
    <form method="get" action="index.php" class="flex gap-2 mb-4">
        <input type="hidden" name="module" value="Connections">
        <input type="text" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>" placeholder="Farm name…" class="flex-1 px-3 py-2 text-sm border rounded-lg">
        <input type="text" name="district" value="<?php echo htmlspecialchars($_GET['district'] ?? ''); ?>" placeholder="District…" class="px-3 py-2 text-sm border rounded-lg">
        <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold">Search</button>
    </form>
    <?php if (!empty($search)): ?>
    <ul class="space-y-2">
        <?php foreach ($search as $f): ?>
        <li class="flex items-center justify-between bg-white/50 border border-white/60 rounded-xl px-3.5 py-2.5 text-sm">
            <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($f['name']); ?> <span class="text-slate-400">(<?php echo htmlspecialchars($f['district'] ?? ''); ?>)</span></span>
            <form method="post" action="index.php?module=Connections&action=request">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="hidden" name="target_farm_id" value="<?php echo (int)$f['id']; ?>">
                <button type="submit" class="text-xs font-bold px-3 py-1.5 rounded bg-blue-600 text-white">Request connection</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>
<?php endif; ?>
<p class="text-xs text-slate-400">Extranet messaging requires an accepted connection between two farms.</p>