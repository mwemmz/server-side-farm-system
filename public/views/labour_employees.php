<?php
// Employee Records — HR sub-section with live add (no page refresh).
require_once __DIR__ . '/partials/labour_nav.php';
$employees = $data['employees'];
$departments = $data['departments'];
$flash = SessionHelper::getFlash('success') ?? null;
$flashErr = SessionHelper::getFlash('error') ?? null;
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Employee Records</h1>

<?php if ($flash): ?><div class="glass-card bg-emerald-50/80 p-4 mb-5 text-emerald-800 text-sm font-semibold"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="glass-card bg-rose-50/80 p-4 mb-5 text-rose-700 text-sm font-semibold"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
    <!-- Add employee (live) -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-1">Add Employee</h2>
        <p class="text-xs text-slate-500 mb-4">Appears in the table instantly — no page reload.</p>
        <form id="empAddForm" class="space-y-3">
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-slate-700">Employee No.</label>
                <input type="text" name="emp_no" placeholder="Auto-generated if blank" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white/70 border border-slate-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-sm font-semibold text-slate-700">Full Name *</label>
                <input type="text" name="name" required class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white/70 border border-slate-200 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Job Title</label>
                    <input type="text" name="job_title" class="w-full px-3.5 py-2 text-sm text-slate-800 bg-white/70 border border-slate-200 rounded-lg focus:ring-2 focus:ring-green-500"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Department</label>
                    <select name="department_id" class="w-full px-3 py-2 text-sm text-slate-800 bg-white/70 border border-slate-200 rounded-lg focus:ring-2 focus:ring-green-500">
                        <option value="">— None —</option>
                        <?php foreach ($departments as $d): ?><option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Status</label>
                    <select name="employment_status" class="w-full px-3 py-2 text-sm border rounded-lg">
                        <option value="active">Active</option><option value="inactive">Inactive</option>
                    </select></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Contract</label>
                    <select name="contract_type" class="w-full px-3 py-2 text-sm border rounded-lg">
                        <option value="permanent">Permanent</option><option value="seasonal">Seasonal</option><option value="casual">Casual</option>
                    </select></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Hire Date</label>
                    <input type="date" name="hire_date" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Pay Type</label>
                    <select name="pay_type" class="w-full px-3 py-2 text-sm border rounded-lg">
                        <option value="monthly">Monthly</option><option value="daily">Daily</option><option value="piece">Piece-rate</option>
                    </select></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Monthly</label>
                    <input type="number" step="0.01" name="monthly_salary" value="0" class="w-full px-3 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Daily</label>
                    <input type="number" step="0.01" name="daily_rate" value="0" class="w-full px-3 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Piece</label>
                    <input type="number" step="0.01" name="piece_rate" value="0" class="w-full px-3 py-2 text-sm border rounded-lg"></div>
            </div>
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Documents / Notes</label>
                <textarea name="documents" rows="2" class="w-full px-3.5 py-2 text-sm border rounded-lg" placeholder="Linked docs, notes"></textarea></div>
            <div id="empMsg" class="text-sm font-semibold"></div>
            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg transition">Add Employee</button>
        </form>
    </div>

    <!-- Employee list -->
    <div class="lg:col-span-2 glass-card p-5">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
            <h2 class="text-lg font-bold text-slate-700">All Employees (<?php echo count($employees); ?>)</h2>
            <form method="get" action="index.php" class="flex items-center gap-2">
                <input type="hidden" name="module" value="Labour"><input type="hidden" name="subsection" value="employees">
                <select name="dept" onchange="this.form.submit()" class="px-3 py-1.5 text-sm border rounded-lg bg-white/70">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo (isset($_GET['dept']) && (int)$_GET['dept']===(int)$d['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                        <th class="py-2 pr-3">No.</th><th class="py-2 pr-3">Name</th><th class="py-2 pr-3">Job Title</th>
                        <th class="py-2 pr-3">Department</th><th class="py-2 pr-3">Contract</th><th class="py-2 pr-3">Status</th>
                    </tr>
                </thead>
                <tbody id="empTable">
                    <?php if (!empty($employees)): foreach ($employees as $e): ?>
                        <tr class="border-b border-slate-100">
                            <td class="py-2.5 pr-3 font-mono text-slate-500"><?php echo htmlspecialchars($e['emp_no']); ?></td>
                            <td class="py-2.5 pr-3 font-semibold text-slate-800"><?php echo htmlspecialchars($e['name']); ?></td>
                            <td class="py-2.5 pr-3 text-slate-600"><?php echo htmlspecialchars($e['job_title'] ?? '—'); ?></td>
                            <td class="py-2.5 pr-3 text-slate-600"><?php echo htmlspecialchars($e['department'] ?? '—'); ?></td>
                            <td class="py-2.5 pr-3"><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-100 text-slate-600"><?php echo htmlspecialchars($e['contract_type']); ?></span></td>
                            <td class="py-2.5">
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $e['employment_status']==='active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600'; ?>"><?php echo htmlspecialchars($e['employment_status']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6" class="py-6 text-center text-slate-400">No employees yet. Add your first one.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('empAddForm');
    var msg = document.getElementById('empMsg');
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        msg.textContent = 'Saving…';
        msg.className = 'text-sm font-semibold text-slate-500';
        var fd = new FormData(form);
        fetch('index.php?module=Labour&action=emp_add&subsection=employees', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    msg.textContent = 'Employee added.';
                    msg.className = 'text-sm font-semibold text-emerald-600';
                    form.reset();
                    return loadList();
                }
                msg.textContent = res.error || 'Failed to add.';
                msg.className = 'text-sm font-semibold text-rose-600';
            })
            .catch(function () { msg.textContent = 'Request failed.'; msg.className = 'text-sm font-semibold text-rose-600'; });
    });
    function esc(s) { return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function loadList() {
        var dept = new URLSearchParams(window.location.search).get('dept') || '';
        return fetch('index.php?module=Labour&action=emp_json&dept=' + encodeURIComponent(dept))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                var tbody = document.getElementById('empTable');
                var rows = res.data || [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="py-6 text-center text-slate-400">No employees yet.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (e) {
                    var st = e.employment_status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600';
                    return '<tr class="border-b border-slate-100">' +
                        '<td class="py-2.5 pr-3 font-mono text-slate-500">' + esc(e.emp_no) + '</td>' +
                        '<td class="py-2.5 pr-3 font-semibold text-slate-800">' + esc(e.name) + '</td>' +
                        '<td class="py-2.5 pr-3 text-slate-600">' + esc(e.job_title || '') + '</td>' +
                        '<td class="py-2.5 pr-3 text-slate-600">' + esc(e.department || '') + '</td>' +
                        '<td class="py-2.5 pr-3"><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">' + esc(e.contract_type || '') + '</span></td>' +
                        '<td class="py-2.5"><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full ' + st + '">' + esc(e.employment_status || '') + '</span></td>' +
                        '</tr>';
                }).join('');
            });
    }
})();
</script>
