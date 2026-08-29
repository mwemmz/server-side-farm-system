<?php
// Departments — HR sub-section.
require_once __DIR__ . '/partials/labour_nav.php';
$departments = $data['departments'];
$flash = SessionHelper::getFlash('success') ?? null;
$flashErr = SessionHelper::getFlash('error') ?? null;
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Departments</h1>

<?php if ($flash): ?><div class="glass-card bg-emerald-50/80 p-4 mb-5 text-emerald-800 text-sm font-semibold"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="glass-card bg-rose-50/80 p-4 mb-5 text-rose-700 text-sm font-semibold"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-4">Add Department</h2>
        <form method="POST" action="index.php?module=Labour&action=add&subsection=departments" class="space-y-3">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-slate-700">Department Name *</label>
                <input type="text" name="name" required class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white/70 border border-slate-200 rounded-lg focus:ring-2 focus:ring-green-500">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-slate-700">Description</label>
                <textarea name="description" rows="3" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white/70 border border-slate-200 rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
            </div>
            <button class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg transition">Add Department</button>
        </form>
    </div>
    <div class="lg:col-span-2 glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Department Structure (<?php echo count($departments); ?>)</h2>
        <p class="text-xs text-slate-500 mb-4">Other modules (purchase orders, budget lines, payroll) tie back to these departments.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php if (!empty($departments)): foreach ($departments as $d): ?>
                <div class="bg-white/50 border border-white/60 rounded-xl px-4 py-3">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-800"><?php echo htmlspecialchars($d['name']); ?></span>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-100 text-sky-800"><?php echo (int) $d['headcount']; ?> staff</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($d['description'] ?? 'No description'); ?></p>
                </div>
            <?php endforeach; else: ?>
                <p class="text-slate-400 col-span-full">No departments yet. Add your first department.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
