<?php
// Leave Management — HR sub-section (Employment Code Act aligned).
require_once __DIR__ . '/partials/labour_nav.php';
$balances = $data['balances'];
$requests = $data['requests'];
$pending  = $data['pending'];
$upcoming = $data['upcoming'];
$employees = $data['employees'];
$flash = SessionHelper::getFlash('success') ?? null;
$flashErr = SessionHelper::getFlash('error') ?? null;
$leaveColors = [
    'annual' => 'bg-sky-100 text-sky-800', 'sick' => 'bg-rose-100 text-rose-700',
    'maternity' => 'bg-pink-100 text-pink-800', 'paternity' => 'bg-indigo-100 text-indigo-800', 'unpaid' => 'bg-slate-100 text-slate-600',
];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-2 text-green-800">Leave Management</h1>
<p class="text-xs text-slate-500 mb-6">Entitlements aligned to Zambia's Employment Code Act 2019: annual 24 working days (after 12 months), sick 26 days/cycle, maternity 14 weeks paid, paternity 10 days paid.</p>

<?php if ($flash): ?><div class="glass-card bg-emerald-50/80 p-4 mb-5 text-emerald-800 text-sm font-semibold"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="glass-card bg-rose-50/80 p-4 mb-5 text-rose-700 text-sm font-semibold"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
    <!-- Request leave -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-4">Request Leave</h2>
        <form method="POST" action="index.php?module=Labour&action=add&subsection=leave" class="space-y-3">
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Employee *</label>
                <select name="employee_id" id="lvEmp" required class="w-full px-3 py-2 text-sm border rounded-lg">
                    <option value="">— Select employee —</option>
                    <?php foreach ($employees as $e): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Leave Type</label>
                <select name="leave_type" class="w-full px-3 py-2 text-sm border rounded-lg">
                    <option value="annual">Annual</option><option value="sick">Sick</option>
                    <option value="maternity">Maternity</option><option value="paternity">Paternity</option><option value="unpaid">Unpaid</option>
                </select></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Start *</label>
                    <input type="date" name="start_date" id="lvStart" required class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">End *</label>
                    <input type="date" name="end_date" id="lvEnd" required class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
            </div>
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Days</label>
                <div class="flex items-center gap-2"><input type="number" id="lvDays" name="days" value="1" min="1" class="w-24 px-3 py-2 text-sm border rounded-lg">
                    <button type="button" id="lvCalc" class="text-xs font-bold text-green-700 underline">auto-calc</button></div></div>
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Reason</label>
                <textarea name="reason" rows="2" class="w-full px-3.5 py-2 text-sm border rounded-lg"></textarea></div>
            <button class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg transition">Submit Request</button>
        </form>
    </div>

    <!-- Pending approvals -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Pending Approvals (<?php echo count($pending); ?>)</h2>
        <?php if (!empty($pending)): foreach ($pending as $p): ?>
            <div class="border-l-4 border-amber-400 bg-amber-50/60 rounded-r-xl px-3 py-2.5 mb-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($p['employee_name']); ?></span>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $leaveColors[$p['leave_type']] ?? 'bg-slate-100 text-slate-600'; ?>"><?php echo $p['leave_type']; ?></span>
                </div>
                <div class="text-xs text-slate-500 mt-1"><?php echo date('M j', strtotime($p['start_date'])); ?> → <?php echo date('M j', strtotime($p['end_date'])); ?> · <?php echo $p['days']; ?> days</div>
                <div class="flex gap-2 mt-2">
                    <a href="index.php?module=Labour&action=approve&subsection=leave&id=<?php echo $p['id']; ?>" class="flex-1 text-center text-xs font-bold px-3 py-1.5 rounded-lg bg-emerald-600 text-white">Approve</a>
                    <a href="index.php?module=Labour&action=reject&subsection=leave&id=<?php echo $p['id']; ?>" class="flex-1 text-center text-xs font-bold px-3 py-1.5 rounded-lg bg-rose-100 text-rose-700">Reject</a>
                </div>
            </div>
        <?php endforeach; else: ?>
            <p class="text-sm text-slate-400">No pending leave requests.</p>
        <?php endif; ?>
    </div>

    <!-- Leave calendar -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">On Leave — Next 30 Days</h2>
        <?php if (!empty($upcoming)): ?>
            <ul class="space-y-2">
                <?php foreach ($upcoming as $u): ?>
                    <li class="flex items-center justify-between bg-white/50 border border-white/60 rounded-xl px-3 py-2 text-sm">
                        <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($u['employee_name']); ?></span>
                        <span class="text-xs text-slate-500"><?php echo date('M j', strtotime($u['start_date'])); ?> → <?php echo date('M j', strtotime($u['end_date'])); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-sm text-slate-400">No approved leave in the next 30 days.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Leave balances + request history -->
<div class="grid grid-cols-1 gap-5">
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Leave Balances</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                    <th class="py-2 pr-3">Employee</th><th class="py-2 pr-3">Annual</th><th class="py-2 pr-3">Sick</th>
                    <th class="py-2 pr-3">Maternity</th><th class="py-2 pr-3">Paternity</th><th class="py-2">Unpaid</th>
                </tr></thead>
                <tbody>
                <?php
                $byEmp = [];
                foreach ($balances as $b) { $byEmp[$b['employee_id']][$b['leave_type']] = $b; }
                foreach ($byEmp as $empId => $row): ?>
                    <tr class="border-b border-slate-100">
                        <td class="py-2.5 pr-3 font-semibold text-slate-800"><?php echo htmlspecialchars($row['annual']['employee_name'] ?? '—'); ?></td>
                        <?php foreach (['annual','sick','maternity','paternity','unpaid'] as $t):
                            $b = $row[$t] ?? null; ?>
                            <td class="py-2.5 pr-3 text-slate-600"><?php echo $b ? ((int)$b['total_days'] - (int)$b['used_days']) . ' left' : '—'; ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var start = document.getElementById('lvStart'), end = document.getElementById('lvEnd'),
        days = document.getElementById('lvDays'), calc = document.getElementById('lvCalc');
    function diff() {
        if (!start.value || !end.value) return;
        var a = new Date(start.value + 'T00:00:00'), b = new Date(end.value + 'T00:00:00');
        var ms = b - a; if (ms < 0) ms = 0;
        days.value = Math.round(ms / 86400000) + 1;
    }
    calc.addEventListener('click', diff);
    start.addEventListener('change', diff); end.addEventListener('change', diff);
})();
</script>
