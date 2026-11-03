<?php
// Leave Management — role-aware view.
// Managers/admins: full HR panel (submit on behalf, pending approvals, calendar, all balances).
// Workers: personal leave request form + their own request history only.
require_once __DIR__ . '/partials/labour_nav.php';
require_once __DIR__ . '/../../src/Helpers/RoleHelper.php';

$balances     = $data['balances']     ?? [];
$requests     = $data['requests']     ?? [];
$pending      = $data['pending']      ?? [];
$upcoming     = $data['upcoming']     ?? [];
$employees    = $data['employees']    ?? [];
$isHr         = $data['isHr']         ?? false;
$myEmployeeId = $data['myEmployeeId'] ?? null;

$flash    = SessionHelper::getFlash('success') ?? null;
$flashErr = SessionHelper::getFlash('error')   ?? null;

$leaveColors = [
    'annual'    => 'bg-sky-100 text-sky-800',
    'sick'      => 'bg-rose-100 text-rose-700',
    'maternity' => 'bg-pink-100 text-pink-800',
    'paternity' => 'bg-indigo-100 text-indigo-800',
    'unpaid'    => 'bg-slate-100 text-slate-600',
];
$statusColors = [
    'pending'  => 'bg-amber-100 text-amber-700',
    'approved' => 'bg-emerald-100 text-emerald-700',
    'rejected' => 'bg-red-100 text-red-700',
];
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-green-800">Leave Management</h1>
        <p class="text-xs text-slate-500 mt-1">
            <?php if ($isHr): ?>
                HR view — submit, approve, and track all farm leave requests.
            <?php else: ?>
                Submit a leave request. Your manager will be notified for approval.
            <?php endif; ?>
        </p>
    </div>
    <?php if ($isHr): ?>
    <div class="flex items-center gap-2 text-xs text-slate-500 bg-white border border-slate-200 rounded-xl px-3 py-2">
        <span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>
        <?php echo count($pending); ?> pending approval<?php echo count($pending) !== 1 ? 's' : ''; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="glass-card bg-emerald-50/80 border border-emerald-200 p-4 mb-5 text-emerald-800 text-sm font-semibold flex items-center gap-2">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    <?php echo htmlspecialchars($flash); ?>
</div>
<?php endif; ?>
<?php if ($flashErr): ?>
<div class="glass-card bg-rose-50/80 border border-rose-200 p-4 mb-5 text-rose-700 text-sm font-semibold">
    <?php echo htmlspecialchars($flashErr); ?>
</div>
<?php endif; ?>

<?php if ($isHr): ?>
<!-- ================================================================ -->
<!-- HR / MANAGER VIEW                                                 -->
<!-- ================================================================ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

    <!-- Submit leave (on behalf of any employee) -->
    <div class="glass-card p-5">
        <h2 class="text-base font-bold text-slate-700 mb-4">Submit Leave Request</h2>
        <form method="POST" action="index.php?module=Labour&action=add&subsection=leave" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Employee *</label>
                <select name="employee_id" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                    <option value="">— Select employee —</option>
                    <?php foreach ($employees as $e): ?>
                    <option value="<?php echo (int)$e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?> (<?php echo htmlspecialchars($e['emp_no']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Leave Type</label>
                <select name="leave_type" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                    <option value="annual">Annual (24 days)</option>
                    <option value="sick">Sick (26 days)</option>
                    <option value="maternity">Maternity (98 days)</option>
                    <option value="paternity">Paternity (10 days)</option>
                    <option value="unpaid">Unpaid</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Start *</label>
                    <input type="date" name="start_date" id="lvStart" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">End *</label>
                    <input type="date" name="end_date" id="lvEnd" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Days</label>
                    <input type="number" id="lvDays" name="days" value="1" min="1" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                </div>
                <button type="button" id="lvCalc" class="mt-5 text-xs font-bold text-green-700 underline hover:no-underline">auto-calc</button>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Reason</label>
                <textarea name="reason" rows="2" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none resize-none"></textarea>
            </div>
            <button type="submit" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow hover:opacity-90 transition">
                Submit Request
            </button>
        </form>
    </div>

    <!-- Pending approvals -->
    <div class="glass-card p-5">
        <h2 class="text-base font-bold text-slate-700 mb-3">
            Pending Approvals
            <?php if (count($pending)): ?>
            <span class="ml-1 px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-bold"><?php echo count($pending); ?></span>
            <?php endif; ?>
        </h2>
        <?php if (empty($pending)): ?>
            <div class="text-center py-8">
                <div class="text-3xl mb-2">✅</div>
                <p class="text-sm text-slate-400">All clear — no pending requests.</p>
            </div>
        <?php else: ?>
            <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
            <?php foreach ($pending as $p): ?>
                <div class="border-l-4 border-amber-400 bg-amber-50/60 rounded-r-xl px-3 py-2.5">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($p['employee_name']); ?></span>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $leaveColors[$p['leave_type']] ?? 'bg-slate-100 text-slate-600'; ?>">
                            <?php echo ucfirst($p['leave_type']); ?>
                        </span>
                    </div>
                    <div class="text-xs text-slate-500">
                        <?php echo date('M j', strtotime($p['start_date'])); ?> → <?php echo date('M j', strtotime($p['end_date'])); ?>
                        · <strong><?php echo (int)$p['days']; ?> day<?php echo $p['days'] != 1 ? 's' : ''; ?></strong>
                    </div>
                    <?php if (!empty($p['reason'])): ?>
                    <div class="text-xs text-slate-400 mt-0.5 italic">"<?php echo htmlspecialchars(substr($p['reason'], 0, 60)); ?>"</div>
                    <?php endif; ?>
                    <div class="flex gap-2 mt-2">
                        <form method="POST" action="index.php?module=Labour&action=approve&subsection=leave&id=<?php echo (int)$p['id']; ?>" class="flex-1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                            <button class="w-full text-xs font-bold py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition">✓ Approve</button>
                        </form>
                        <form method="POST" action="index.php?module=Labour&action=reject&subsection=leave&id=<?php echo (int)$p['id']; ?>" class="flex-1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                            <button class="w-full text-xs font-bold py-1.5 rounded-lg bg-rose-100 text-rose-700 hover:bg-rose-200 transition">✕ Reject</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- On leave next 30 days -->
    <div class="glass-card p-5">
        <h2 class="text-base font-bold text-slate-700 mb-3">On Leave — Next 30 Days</h2>
        <?php if (empty($upcoming)): ?>
            <p class="text-sm text-slate-400">No approved leave scheduled in the next 30 days.</p>
        <?php else: ?>
            <ul class="space-y-2">
            <?php foreach ($upcoming as $u): ?>
                <li class="flex items-center justify-between bg-white/50 border border-white/60 rounded-xl px-3 py-2 text-sm">
                    <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($u['employee_name']); ?></span>
                    <span class="text-xs text-slate-500">
                        <?php echo date('M j', strtotime($u['start_date'])); ?> → <?php echo date('M j', strtotime($u['end_date'])); ?>
                    </span>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- All leave balances table (HR only) -->
<div class="glass-card p-5 mb-6">
    <h2 class="text-base font-bold text-slate-700 mb-3">All Employee Leave Balances</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                    <th class="py-2 pr-4">Employee</th>
                    <th class="py-2 pr-4">Annual</th>
                    <th class="py-2 pr-4">Sick</th>
                    <th class="py-2 pr-4">Maternity</th>
                    <th class="py-2 pr-4">Paternity</th>
                    <th class="py-2">Unpaid</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php
            $byEmp = [];
            foreach ($balances as $b) { $byEmp[$b['employee_id']][$b['leave_type']] = $b; }
            foreach ($byEmp as $empId => $row):
                $empName = ($row['annual'] ?? $row['sick'] ?? reset($row))['employee_name'] ?? '—';
            ?>
                <tr class="hover:bg-slate-50 transition">
                    <td class="py-2.5 pr-4 font-semibold text-slate-800"><?php echo htmlspecialchars($empName); ?></td>
                    <?php foreach (['annual','sick','maternity','paternity','unpaid'] as $t):
                        $b = $row[$t] ?? null;
                        $left = $b ? ((int)$b['total_days'] - (int)$b['used_days']) : null;
                        $low  = $b && $left !== null && $b['total_days'] > 0 && $left <= 3;
                    ?>
                    <td class="py-2.5 pr-4 <?php echo $low ? 'text-rose-600 font-bold' : 'text-slate-600'; ?>">
                        <?php echo $b ? $left . ' left' : '—'; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- ================================================================ -->
<!-- WORKER VIEW — personal leave only                                 -->
<!-- ================================================================ -->

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">

    <!-- Worker leave request form -->
    <div class="glass-card p-5">
        <h2 class="text-base font-bold text-slate-700 mb-4">Request Leave</h2>

        <?php if (empty($employees)): ?>
            <div class="text-center py-8 text-slate-400">
                <div class="text-3xl mb-2">👤</div>
                <p class="text-sm font-semibold">No employee record found for your account.</p>
                <p class="text-xs mt-1">Ask your manager to create an employee profile for you.</p>
            </div>
        <?php else: ?>
        <form method="POST" action="index.php?module=Labour&action=add&subsection=leave" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
            <?php if ($myEmployeeId): ?>
                <input type="hidden" name="employee_id" value="<?php echo $myEmployeeId; ?>">
                <p class="text-sm text-slate-500 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2">
                    Submitting as: <strong class="text-slate-800"><?php echo htmlspecialchars($employees[array_key_first($employees)]['name'] ?? ''); ?></strong>
                </p>
            <?php else: ?>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Employee *</label>
                    <select name="employee_id" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                        <option value="">— Select your name —</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?php echo (int)$e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Leave Type</label>
                <select name="leave_type" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                    <option value="annual">Annual Leave</option>
                    <option value="sick">Sick Leave</option>
                    <option value="maternity">Maternity Leave</option>
                    <option value="paternity">Paternity Leave</option>
                    <option value="unpaid">Unpaid Leave</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Start *</label>
                    <input type="date" name="start_date" id="lvStart" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">End *</label>
                    <input type="date" name="end_date" id="lvEnd" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Days</label>
                    <input type="number" id="lvDays" name="days" value="1" min="1" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                </div>
                <button type="button" id="lvCalc" class="mt-5 text-xs font-bold text-green-700 underline hover:no-underline">auto-calc</button>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Reason</label>
                <textarea name="reason" rows="2" placeholder="Brief reason for your leave…" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none resize-none"></textarea>
            </div>
            <button type="submit" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow hover:opacity-90 transition">
                Submit Leave Request
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Worker's own request history -->
    <div class="glass-card p-5">
        <h2 class="text-base font-bold text-slate-700 mb-3">My Leave Requests</h2>
        <?php
        // Filter requests to only this worker's employee record
        $myRequests = array_filter($requests, function($r) use ($myEmployeeId, $employees) {
            if ($myEmployeeId) return (int)$r['employee_id'] === $myEmployeeId;
            return true;
        });
        ?>
        <?php if (empty($myRequests)): ?>
            <p class="text-sm text-slate-400 text-center py-6">You have not submitted any leave requests yet.</p>
        <?php else: ?>
            <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
            <?php foreach ($myRequests as $r): ?>
                <div class="bg-white/60 border border-white/60 rounded-xl px-3.5 py-3 text-sm">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-semibold text-slate-700">
                            <?php echo date('M j', strtotime($r['start_date'])); ?>
                            → <?php echo date('M j', strtotime($r['end_date'])); ?>
                            · <strong><?php echo (int)$r['days']; ?> day<?php echo $r['days'] != 1 ? 's' : ''; ?></strong>
                        </span>
                        <div class="flex items-center gap-1">
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $leaveColors[$r['leave_type']] ?? ''; ?>">
                                <?php echo ucfirst($r['leave_type']); ?>
                            </span>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $statusColors[$r['status']] ?? 'bg-slate-100 text-slate-600'; ?>">
                                <?php echo ucfirst($r['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($r['reason'])): ?>
                    <div class="text-xs text-slate-400 italic">"<?php echo htmlspecialchars($r['reason']); ?>"</div>
                    <?php endif; ?>
                    <div class="text-[10px] text-slate-400 mt-1">Submitted <?php echo date('M j, Y', strtotime($r['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Worker's own leave balances -->
        <?php
        $myBalances = [];
        if ($myEmployeeId) {
            foreach ($balances as $b) {
                if ((int)$b['employee_id'] === $myEmployeeId) {
                    $myBalances[$b['leave_type']] = $b;
                }
            }
        }
        ?>
        <?php if (!empty($myBalances)): ?>
        <div class="mt-5 pt-4 border-t border-slate-100">
            <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-3">My Leave Balance</h3>
            <div class="grid grid-cols-2 gap-2">
            <?php foreach (['annual','sick','maternity','paternity','unpaid'] as $t):
                $b = $myBalances[$t] ?? null;
                if (!$b) continue;
                $left = (int)$b['total_days'] - (int)$b['used_days'];
                $low  = $b['total_days'] > 0 && $left <= 3;
            ?>
                <div class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-center">
                    <div class="text-[10px] font-bold uppercase text-slate-400 mb-0.5"><?php echo ucfirst($t); ?></div>
                    <div class="text-lg font-extrabold <?php echo $low ? 'text-rose-600' : 'text-slate-800'; ?>"><?php echo $left; ?></div>
                    <div class="text-[10px] text-slate-400">days left</div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; // end worker/HR split ?>

<script>
(function () {
    var start = document.getElementById('lvStart');
    var end   = document.getElementById('lvEnd');
    var days  = document.getElementById('lvDays');
    var calc  = document.getElementById('lvCalc');
    if (!start || !end || !days || !calc) return;
    function diff() {
        if (!start.value || !end.value) return;
        var a = new Date(start.value + 'T00:00:00');
        var b = new Date(end.value   + 'T00:00:00');
        var ms = b - a;
        if (ms < 0) { days.value = 1; return; }
        days.value = Math.round(ms / 86400000) + 1;
    }
    calc.addEventListener('click', diff);
    start.addEventListener('change', diff);
    end.addEventListener('change', diff);
})();
</script>
