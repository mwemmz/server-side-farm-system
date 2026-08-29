<?php
// Training & Certification — HR sub-section.
require_once __DIR__ . '/partials/labour_nav.php';
$training = $data['training'];
$employees = $data['employees'];
$flash = SessionHelper::getFlash('success') ?? null;
$flashErr = SessionHelper::getFlash('error') ?? null;
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Training & Certification</h1>

<?php if ($flash): ?><div class="glass-card bg-emerald-50/80 p-4 mb-5 text-emerald-800 text-sm font-semibold"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="glass-card bg-rose-50/80 p-4 mb-5 text-rose-700 text-sm font-semibold"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-4">Add Training Record</h2>
        <form method="POST" action="index.php?module=Labour&action=add&subsection=training" class="space-y-3">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-slate-700">Employee *</label>
                <select name="employee_id" required class="w-full px-3 py-2 text-sm border rounded-lg">
                    <option value="">— Select employee —</option>
                    <?php foreach ($employees as $e): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']) . ' (' . htmlspecialchars($e['emp_no']) . ')'; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Course Name *</label>
                <input type="text" name="course_name" required class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Provider</label>
                <input type="text" name="provider" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Completion Date</label>
                    <input type="date" name="completion_date" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Status</label>
                    <select name="status" class="w-full px-3 py-2 text-sm border rounded-lg">
                        <option value="completed">Completed</option><option value="in_progress">In progress</option><option value="pending">Pending</option>
                    </select></div>
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input type="checkbox" name="certified" value="1" class="w-4 h-4"> Certified badge
            </label>
            <button class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg transition">Add Training</button>
        </form>
    </div>
    <div class="lg:col-span-2 glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">All Training Records</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                    <th class="py-2 pr-3">Employee</th><th class="py-2 pr-3">Course</th><th class="py-2 pr-3">Provider</th>
                    <th class="py-2 pr-3">Completed</th><th class="py-2 pr-3">Status</th><th class="py-2">Certified</th>
                </tr></thead>
                <tbody>
                <?php if (!empty($training)): foreach ($training as $t): ?>
                    <tr class="border-b border-slate-100">
                        <td class="py-2.5 pr-3 font-semibold text-slate-800"><?php echo htmlspecialchars($t['employee_name'] ?? '—'); ?></td>
                        <td class="py-2.5 pr-3 text-slate-600"><?php echo htmlspecialchars($t['course_name']); ?></td>
                        <td class="py-2.5 pr-3 text-slate-600"><?php echo htmlspecialchars($t['provider'] ?? '—'); ?></td>
                        <td class="py-2.5 pr-3 text-slate-500"><?php echo !empty($t['completion_date']) ? date('M j, Y', strtotime($t['completion_date'])) : '—'; ?></td>
                        <td class="py-2.5 pr-3 text-slate-600"><?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?></td>
                        <td class="py-2.5">
                            <?php if (!empty($t['certified'])): ?><span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Certified</span>
                            <?php else: ?><span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">No</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">No training records yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
