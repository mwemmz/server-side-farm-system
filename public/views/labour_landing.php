<?php
// Labour / HR Management dashboard landing.
// $data: stats array + upcoming_leave, pending_leave, recent_training.
$s = $data;
$sub = 'dashboard';
$sections = [
    'employees'   => ['Employee Records', 'Profiles: number, job title, department, contract type, hire date, documents.', 'leaf-green', 'M21 13.255A23.931 23.931 0 0 1 12 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2m4 6h.01M5 20h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z'],
    'departments' => ['Departments', 'Crop ops, livestock, equipment, admin — the structure other modules plug into.', 'sky', 'M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4'],
    'training'    => ['Training & Certification', 'Courses, providers, completion dates, certified badges per employee.', 'amber', 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 0 1 .665 6.479A11.952 11.952 0 0 0 12 20.055a11.952 11.952 0 0 0-6.824-2.998 12.078 12.078 0 0 1 .665-6.479L12 14zm-4 6v-7.5l4-2.222'],
    'payroll'     => ['Payroll', 'Monthly / daily / piece-rate wages with Zambia statutory deductions (NAPSA, PAYE, NHIMA) and payslips.', 'violet', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
    'leave'       => ['Leave Management', 'Annual / sick / maternity / paternity / unpaid with balances, approval workflow and a calendar.', 'rose', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z'],
    'shifts'      => ['Shifts & Attendance', 'Rosters, clock-in/out feeding attendance into payroll, swap requests and conflict flagging.', 'cyan', 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z'],
    'grievances'  => ['Cases & Grievances', 'Log grievances and disciplinary cases with status tracking and resolution notes.', 'slate', 'M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2z'],
];
$accent = [
    'leaf-green' => 'from-green-600 to-emerald-600', 'sky' => 'from-sky-600 to-blue-600',
    'amber' => 'from-amber-500 to-orange-600', 'violet' => 'from-violet-600 to-purple-700',
    'rose' => 'from-rose-500 to-pink-600', 'cyan' => 'from-cyan-500 to-teal-600',
    'slate' => 'from-slate-600 to-slate-700',
];
$badge = [
    'leaf-green' => 'bg-green-100 text-green-800', 'sky' => 'bg-sky-100 text-sky-800',
    'amber' => 'bg-amber-100 text-amber-800', 'violet' => 'bg-violet-100 text-violet-800',
    'rose' => 'bg-rose-100 text-rose-800', 'cyan' => 'bg-cyan-100 text-cyan-800',
    'slate' => 'bg-slate-100 text-slate-700',
];
$money = function ($n) { require_once __DIR__ . '/../../config/env.php'; return money($n); };
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-1 text-green-800 flex items-center gap-3">
    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Labour Management
</h1>
<p class="text-sm text-slate-500 mb-6">Human resources across the farm — employee records, departments, payroll, leave, shifts and cases, aligned to Zambia's Employment Code Act 2019.</p>

<!-- Stat strip -->
<div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-3 mb-6">
    <div class="glass-card p-3 text-center"><div class="text-2xl font-extrabold text-green-700"><?php echo $s['total']; ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Employees</div></div>
    <div class="glass-card p-3 text-center"><div class="text-2xl font-extrabold text-emerald-600"><?php echo $s['active']; ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Active</div></div>
    <div class="glass-card p-3 text-center"><div class="text-2xl font-extrabold text-sky-600"><?php echo $s['on_leave_today']; ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">On Leave Today</div></div>
    <div class="glass-card p-3 text-center"><div class="text-2xl font-extrabold text-violet-600"><?php echo $s['departments']; ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Departments</div></div>
    <div class="glass-card p-3 text-center"><div class="text-2xl font-extrabold text-amber-600"><?php echo $s['pending_leave']; ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Pending Leave</div></div>
    <div class="glass-card p-3 text-center"><div class="text-2xl font-extrabold text-rose-500"><?php echo $s['pending_grievances']; ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Open Cases</div></div>
    <div class="glass-card p-3 text-center"><div class="text-lg font-extrabold text-emerald-700"><?php echo $money($s['gross_paid']); ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Gross Paid</div></div>
    <div class="glass-card p-3 text-center"><div class="text-lg font-extrabold text-slate-700"><?php echo $money($s['net_paid']); ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Net Paid</div></div>
</div>

<!-- Sub-section cards -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <?php foreach ($sections as $key => $sec): ?>
        <a href="index.php?module=Labour&subsection=<?php echo $key; ?>"
           class="glass-card group p-5 hover:-translate-y-0.5 hover:shadow-xl transition-all duration-200">
            <div class="flex items-center justify-between mb-3">
                <div class="w-11 h-11 rounded-2xl bg-gradient-to-br <?php echo $accent[$sec[2]]; ?> flex items-center justify-center text-white shadow-lg">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="<?php echo $sec[3]; ?>"/></svg>
                </div>
                <span class="<?php echo $badge[$sec[2]]; ?> text-[10px] font-bold uppercase px-2 py-1 rounded-full">Open</span>
            </div>
            <div class="text-base font-bold text-slate-800 mb-1 group-hover:text-green-700"><?php echo $sec[0]; ?></div>
            <p class="text-xs text-slate-500 leading-relaxed"><?php echo $sec[1]; ?></p>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Upcoming & pending leave -->
    <div class="glass-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-bold text-slate-700">Leave Calendar (next 30 days)</h2>
            <a href="index.php?module=Labour&subsection=leave" class="text-xs font-bold text-green-700 hover:text-green-800">Manage →</a>
        </div>
        <?php if (!empty($data['upcoming_leave'])): ?>
            <ul class="space-y-2">
                <?php foreach ($data['upcoming_leave'] as $l):
                    $lType = ucfirst($l['leave_type']); ?>
                    <li class="flex items-center justify-between bg-white/50 border border-white/60 rounded-xl px-4 py-2.5 text-sm">
                        <div>
                            <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($l['employee_name']); ?></span>
                            <span class="text-slate-500 text-xs ml-1">(<?php echo $lType; ?>)</span>
                        </div>
                        <span class="text-xs font-semibold text-slate-500"><?php echo date('M j', strtotime($l['start_date'])); ?> → <?php echo date('M j', strtotime($l['end_date'])); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-sm text-slate-500">No approved leave in the next 30 days.</p>
        <?php endif; ?>

        <h3 class="text-sm font-bold text-slate-600 mt-5 mb-2">Pending approvals (<?php echo count($data['pending_leave']); ?>)</h3>
        <?php if (!empty($data['pending_leave'])): foreach ($data['pending_leave'] as $l): ?>
            <div class="flex items-center justify-between text-sm border-l-4 border-amber-400 bg-amber-50/60 rounded-r-xl px-3 py-2 mb-2">
                <span class="text-slate-700"><b><?php echo htmlspecialchars($l['employee_name']); ?></b> · <?php echo ucfirst($l['leave_type']); ?> · <?php echo $l['days']; ?> days</span>
                <a href="index.php?module=Labour&subsection=leave" class="text-xs font-bold text-amber-700">Review</a>
            </div>
        <?php endforeach; else: ?>
            <p class="text-xs text-slate-400">Nothing waiting.</p>
        <?php endif; ?>
    </div>

    <!-- Recent training -->
    <div class="glass-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-bold text-slate-700">Recent Training & Certifications</h2>
            <a href="index.php?module=Labour&subsection=training" class="text-xs font-bold text-green-700 hover:text-green-800">Manage →</a>
        </div>
        <?php if (!empty($data['recent_training'])): ?>
            <ul class="space-y-2">
                <?php foreach ($data['recent_training'] as $t): ?>
                    <li class="flex items-center justify-between bg-white/50 border border-white/60 rounded-xl px-4 py-2.5 text-sm">
                        <div>
                            <span class="font-semibold text-slate-800"><?php echo htmlspecialchars($t['course_name']); ?></span>
                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($t['employee_name']); ?> · <?php echo htmlspecialchars($t['provider'] ?? '—'); ?></div>
                        </div>
                        <?php if (!empty($t['certified'])): ?>
                            <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">Certified</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-sm text-slate-500">No training records yet.</p>
        <?php endif; ?>
    </div>
</div>
