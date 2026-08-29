<?php
// Shifts & Attendance — HR sub-section.
require_once __DIR__ . '/partials/labour_nav.php';
$shifts = $data['shifts'];
$attendance = $data['attendance'];
$employees = $data['employees'];
$flash = SessionHelper::getFlash('success') ?? null;
$flashErr = SessionHelper::getFlash('error') ?? null;
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Shifts & Attendance</h1>

<?php if ($flash): ?><div class="glass-card bg-emerald-50/80 p-4 mb-5 text-emerald-800 text-sm font-semibold"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="glass-card bg-rose-50/80 p-4 mb-5 text-rose-700 text-sm font-semibold"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
    <!-- Add shift -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-4">Create Shift / Roster</h2>
        <form method="POST" action="index.php?module=Labour&action=add&subsection=shifts" class="space-y-3">
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Employee *</label>
                <select name="employee_id" required class="w-full px-3 py-2 text-sm border rounded-lg">
                    <option value="">— Select employee —</option>
                    <?php foreach ($employees as $e): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Shift Date *</label>
                    <input type="date" name="shift_date" required class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Shift Type</label>
                    <select name="shift_type" class="w-full px-3 py-2 text-sm border rounded-lg">
                        <option value="">—</option><option>Feeding</option><option>Morning</option><option>Afternoon</option>
                        <option>Night</option><option>Harvest</option><option>Irrigation</option>
                    </select></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Start Time</label>
                    <input type="time" name="start_time" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">End Time</label>
                    <input type="time" name="end_time" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
            </div>
            <button class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg transition">Schedule Shift</button>
        </form>
    </div>

    <!-- Record attendance -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-4">Clock In / Out</h2>
        <form method="POST" action="index.php?module=Labour&action=add&subsection=attendance" class="space-y-3">
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Employee *</label>
                <select name="employee_id" required class="w-full px-3 py-2 text-sm border rounded-lg">
                    <option value="">— Select employee —</option>
                    <?php foreach ($employees as $e): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Date *</label>
                    <input type="date" name="work_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Clock In</label>
                    <input type="time" name="clock_in" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Clock Out</label>
                    <input type="time" name="clock_out" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
            </div>
            <p class="text-xs text-slate-500">Hours worked are computed automatically from clock-in/out and feed into payroll.</p>
            <button class="w-full bg-gradient-to-r from-sky-600 to-blue-700 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg transition">Save Attendance</button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Upcoming Shifts</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                    <th class="py-2 pr-3">Employee</th><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Type</th><th class="py-2">Time</th>
                </tr></thead>
                <tbody>
                <?php $shown = 0; if (!empty($shifts)): foreach ($shifts as $s): if ($shown >= 10) break; ?>
                    <tr class="border-b border-slate-100">
                        <td class="py-2.5 pr-3 font-semibold text-slate-800"><?php echo htmlspecialchars($s['employee_name'] ?? '—'); ?></td>
                        <td class="py-2.5 pr-3 text-slate-500"><?php echo date('M j', strtotime($s['shift_date'])); ?></td>
                        <td class="py-2.5 pr-3 text-slate-600"><?php echo htmlspecialchars($s['shift_type'] ?? '—'); ?></td>
                        <td class="py-2.5 text-slate-600"><?php echo substr($s['start_time'] ?? '—', 0, 5); ?>–<?php echo substr($s['end_time'] ?? '—', 0, 5); ?></td>
                    </tr>
                <?php $shown++; endforeach; else: ?>
                    <tr><td colspan="4" class="py-5 text-center text-slate-400">No shifts scheduled.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Attendance Log</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                    <th class="py-2 pr-3">Employee</th><th class="py-2 pr-3">Date</th><th class="py-2 pr-3">In</th><th class="py-2 pr-3">Out</th><th class="py-2">Hours</th>
                </tr></thead>
                <tbody>
                <?php $shown = 0; if (!empty($attendance)): foreach ($attendance as $a): if ($shown >= 10) break; ?>
                    <tr class="border-b border-slate-100">
                        <td class="py-2.5 pr-3 font-semibold text-slate-800"><?php echo htmlspecialchars($a['employee_name'] ?? '—'); ?></td>
                        <td class="py-2.5 pr-3 text-slate-500"><?php echo date('M j', strtotime($a['work_date'])); ?></td>
                        <td class="py-2.5 pr-3 text-slate-600"><?php echo substr($a['clock_in'] ?? '—', 0, 5); ?></td>
                        <td class="py-2.5 pr-3 text-slate-600"><?php echo substr($a['clock_out'] ?? '—', 0, 5); ?></td>
                        <td class="py-2.5 text-slate-700 font-semibold"><?php echo number_format((float) $a['hours'], 1); ?>h</td>
                    </tr>
                <?php $shown++; endforeach; else: ?>
                    <tr><td colspan="5" class="py-5 text-center text-slate-400">No attendance logged.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
