<?php
// Cases & Grievances — HR sub-section.
require_once __DIR__ . '/partials/labour_nav.php';
$grievances = $data['grievances'];
$employees = $data['employees'];
$flash = SessionHelper::getFlash('success') ?? null;
$flashErr = SessionHelper::getFlash('error') ?? null;
$statusBadge = [
    'open' => 'bg-rose-100 text-rose-700', 'in_progress' => 'bg-amber-100 text-amber-800',
    'resolved' => 'bg-emerald-100 text-emerald-800', 'closed' => 'bg-slate-100 text-slate-600',
];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Cases & Grievances</h1>

<?php if ($flash): ?><div class="glass-card bg-emerald-50/80 p-4 mb-5 text-emerald-800 text-sm font-semibold"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="glass-card bg-rose-50/80 p-4 mb-5 text-rose-700 text-sm font-semibold"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-4">Log a Case</h2>
        <form method="POST" action="index.php?module=Labour&action=add&subsection=grievances" class="space-y-3">
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Employee *</label>
                <select name="employee_id" required class="w-full px-3 py-2 text-sm border rounded-lg">
                    <option value="">— Select employee —</option>
                    <?php foreach ($employees as $e): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Category</label>
                <select name="category" class="w-full px-3 py-2 text-sm border rounded-lg">
                    <option value="general">General</option><option value="harassment">Harassment</option>
                    <option value="wages">Wages / Pay</option><option value="working_conditions">Working Conditions</option>
                    <option value="disciplinary">Disciplinary</option><option value="other">Other</option>
                </select></div>
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Description *</label>
                <textarea name="description" rows="4" required class="w-full px-3.5 py-2 text-sm border rounded-lg"></textarea></div>
            <button class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg transition">Log Case</button>
        </form>
    </div>
    <div class="lg:col-span-2 glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Case Log (<?php echo count($grievances); ?>)</h2>
        <?php if (!empty($grievances)): foreach ($grievances as $g): ?>
            <div class="border border-white/60 bg-white/40 rounded-xl px-4 py-3 mb-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($g['employee_name'] ?? '—'); ?></span>
                    <span class="flex items-center gap-2">
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-100 text-slate-600"><?php echo htmlspecialchars($g['category']); ?></span>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $statusBadge[$g['status']] ?? 'bg-slate-100 text-slate-600'; ?>"><?php echo str_replace('_', ' ', $g['status']); ?></span>
                    </span>
                </div>
                <p class="text-sm text-slate-600 mt-1.5"><?php echo nl2br(htmlspecialchars($g['description'] ?? '')); ?></p>
                <div class="text-xs text-slate-400 mt-1">Logged <?php echo date('M j, Y', strtotime($g['created_at'])); ?></div>
                <?php if ($g['status'] === 'resolved' && !empty($g['resolution_notes'])): ?>
                    <div class="mt-2 text-xs bg-emerald-50 border border-emerald-100 rounded-lg px-3 py-2 text-emerald-800"><b>Resolution:</b> <?php echo htmlspecialchars($g['resolution_notes']); ?></div>
                <?php endif; ?>
                <?php if ($g['status'] === 'open' || $g['status'] === 'in_progress'): ?>
                    <form method="POST" action="index.php?module=Labour&action=resolve&subsection=grievances&id=<?php echo $g['id']; ?>" class="flex items-center gap-2 mt-2">
                        <input type="text" name="resolution_notes" placeholder="Resolution notes…" class="flex-1 px-3 py-1.5 text-sm border rounded-lg">
                        <button class="text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-600 text-white">Mark Resolved</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; else: ?>
            <p class="text-slate-400">No cases logged yet.</p>
        <?php endif; ?>
    </div>
</div>
