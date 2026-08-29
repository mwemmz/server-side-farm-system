<?php
// Payroll — HR sub-section with Zambia gross-to-net calculator.
require_once __DIR__ . '/partials/labour_nav.php';
$payroll = $data['payroll'];
$totals  = $data['totals'];
$employees = $data['employees'];
$flash = SessionHelper::getFlash('success') ?? null;
$flashErr = SessionHelper::getFlash('error') ?? null;
$money = function ($n) { require_once __DIR__ . '/../../config/env.php'; return money($n); };
$empJson = array_map(function ($e) {
    return [
        'id' => (int) $e['id'], 'name' => $e['name'], 'pay_type' => $e['pay_type'],
        'monthly' => (float) $e['monthly_salary'], 'daily' => (float) $e['daily_rate'], 'piece' => (float) $e['piece_rate'],
    ];
}, $employees);
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Payroll</h1>

<?php if ($flash): ?><div class="glass-card bg-emerald-50/80 p-4 mb-5 text-emerald-800 text-sm font-semibold"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>
<?php if ($flashErr): ?><div class="glass-card bg-rose-50/80 p-4 mb-5 text-rose-700 text-sm font-semibold"><?php echo htmlspecialchars($flashErr); ?></div><?php endif; ?>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <div class="glass-card p-3 text-center"><div class="text-lg font-extrabold text-emerald-700"><?php echo $money($totals['gross']); ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Gross Paid</div></div>
    <div class="glass-card p-3 text-center"><div class="text-lg font-extrabold text-slate-700"><?php echo $money($totals['net']); ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Net Paid</div></div>
    <div class="glass-card p-3 text-center"><div class="text-lg font-extrabold text-amber-600"><?php echo $money($totals['napsa'] + $totals['paye'] + $totals['nhima']); ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Total Statutory</div></div>
    <div class="glass-card p-3 text-center"><div class="text-lg font-extrabold text-violet-600"><?php echo $money($totals['overtime']); ?></div><div class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Overtime</div></div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- New pay run -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-1">New Pay Run</h2>
        <p class="text-xs text-slate-500 mb-4">Zambia statutory deductions auto-applied: NAPSA 5%, PAYE (progressive), NHIMA 1%.</p>
        <form id="payForm" method="POST" action="index.php?module=Labour&action=add&subsection=payroll" class="space-y-3">
            <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Employee *</label>
                <select name="employee_id" id="payEmp" required class="w-full px-3 py-2 text-sm border rounded-lg">
                    <option value="">— Select employee —</option>
                    <?php foreach ($employees as $e): ?><option value="<?php echo $e['id']; ?>" data-pay="<?php echo htmlspecialchars(json_encode([(float)$e['monthly_salary'],(float)$e['daily_rate'],(float)$e['piece_rate'],$e['pay_type']])); ?>"><?php echo htmlspecialchars($e['name']); ?></option><?php endforeach; ?>
                </select></div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Period Start</label>
                    <input type="date" name="period_start" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Period End</label>
                    <input type="date" name="period_end" class="w-full px-3.5 py-2 text-sm border rounded-lg"></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700" title="for daily-rate">Work Days</label>
                    <input type="number" name="work_days" id="payDays" value="0" min="0" class="w-full px-3 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700" title="for piece-rate">Units</label>
                    <input type="number" name="work_units" id="payUnits" value="0" min="0" step="0.01" class="w-full px-3 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Overtime</label>
                    <input type="number" name="overtime" id="payOvertime" value="0" min="0" step="0.01" class="w-full px-3 py-2 text-sm border rounded-lg"></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Bonus</label>
                    <input type="number" name="bonus" id="payBonus" value="0" min="0" step="0.01" class="w-full px-3 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Advances</label>
                    <input type="number" name="advances" id="payAdv" value="0" min="0" step="0.01" class="w-full px-3 py-2 text-sm border rounded-lg"></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Loans</label>
                    <input type="number" name="loans" id="payLoans" value="0" min="0" step="0.01" class="w-full px-3 py-2 text-sm border rounded-lg"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Payment Method</label>
                    <select name="payment_method" class="w-full px-3 py-2 text-sm border rounded-lg">
                        <option value="bank">Bank Transfer</option><option value="mobile_money">Mobile Money</option><option value="cash">Cash</option>
                    </select></div>
                <div class="flex flex-col gap-1"><label class="text-sm font-semibold text-slate-700">Status</label>
                    <select name="status" class="w-full px-3 py-2 text-sm border rounded-lg">
                        <option value="draft">Draft</option><option value="paid">Paid</option>
                    </select></div>
            </div>
            <div class="border border-slate-200 rounded-xl p-3 text-sm space-y-1">
                <div class="flex justify-between"><span class="text-slate-500">Gross</span><b id="pvGross" class="text-slate-800">0.00</b></div>
                <div class="flex justify-between"><span class="text-slate-500">NAPSA (5%)</span><span id="pvNapsa" class="text-rose-600">0.00</span></div>
                <div class="flex justify-between"><span class="text-slate-500">PAYE</span><span id="pvPaye" class="text-rose-600">0.00</span></div>
                <div class="flex justify-between"><span class="text-slate-500">NHIMA (1%)</span><span id="pvNhima" class="text-rose-600">0.00</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Advances + Loans</span><span class="text-rose-600" id="pvAdvLoans">0.00</span></div>
                <div class="flex justify-between border-t border-slate-200 pt-1.5"><span class="font-bold text-slate-700">Net Pay</span><b id="pvNet" class="text-emerald-700">0.00</b></div>
            </div>
            <button class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg transition">Save Pay Run</button>
        </form>
    </div>

    <!-- Pay run list -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Pay Runs (<?php echo count($payroll); ?>)</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-200">
                    <th class="py-2 pr-3">Employee</th><th class="py-2 pr-3">Period</th><th class="py-2 pr-3">Gross</th><th class="py-2 pr-3">Net</th><th class="py-2">Status</th>
                </tr></thead>
                <tbody>
                <?php if (!empty($payroll)): foreach ($payroll as $p): ?>
                    <tr class="border-b border-slate-100">
                        <td class="py-2.5 pr-3 font-semibold text-slate-800"><?php echo htmlspecialchars($p['employee_name'] ?? '—'); ?></td>
                        <td class="py-2.5 pr-3 text-slate-500"><?php echo !empty($p['period_end']) ? date('M Y', strtotime($p['period_end'])) : '—'; ?></td>
                        <td class="py-2.5 pr-3 text-slate-600"><?php echo $money($p['gross_pay']); ?></td>
                        <td class="py-2.5 pr-3 font-bold text-emerald-700"><?php echo $money($p['net_pay']); ?></td>
                        <td class="py-2.5">
                            <?php if ($p['status'] === 'paid'): ?>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Paid</span>
                            <?php else: ?>
                                <a href="index.php?module=Labour&action=paid&subsection=payroll&id=<?php echo $p['id']; ?>" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 hover:bg-amber-200">Draft · Mark paid</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="5" class="py-6 text-center text-slate-400">No pay runs yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var empSel = document.getElementById('payEmp');
    function pa(bands) {
        // Annual bands -> monthly equivalents (same as HrService::payeTax).
        var annual = ((bands + 0) * 12);
        var tax = 0;
        if (annual > 156000) { tax += (annual - 156000) * 0.375; annual = 156000; }
        if (annual > 132000) { tax += (annual - 132000) * 0.325; annual = 132000; }
        if (annual > 102000) { tax += (annual - 102000) * 0.30;  annual = 102000; }
        if (annual > 72000)  { tax += (annual - 72000)  * 0.25;  annual = 72000; }
        if (annual > 42000)  { tax += (annual - 42000)  * 0.20;  annual = 42000; }
        if (annual > 12000)  { tax += (annual - 12000)  * 0.10;  annual = 12000; }
        return tax / 12;
    }
    function upd() {
        var opt = empSel.selectedOptions[0];
        var pay = opt ? opt.getAttribute('data-pay') : null;
        var data = pay ? JSON.parse(pay) : [0, 0, 0, 'monthly'];
        var monthly = +data[0], daily = +data[1], piece = +data[2], type = data[3];
        var days = +document.getElementById('payDays').value || 0;
        var units = +document.getElementById('payUnits').value || 0;
        var ot = +document.getElementById('payOvertime').value || 0;
        var gross = type === 'daily' ? daily * days : (type === 'piece' ? piece * units : monthly);
        gross += ot;
        var napsa = gross * 0.05;
        var nhima = (gross > 100000 ? 100000 : gross) * 0.01;
        var paye = pa(gross - napsa);
        var adv = +document.getElementById('payAdv').value || 0;
        var loans = +document.getElementById('payLoans').value || 0;
        var bonus = +document.getElementById('payBonus').value || 0;
        var grossWithBonus = gross + bonus;
        // Recompute deductions on the bonus-inclusive gross for display.
        var g2 = grossWithBonus;
        var n2 = g2 * 0.05, h2 = (g2 > 100000 ? 100000 : g2) * 0.01, p2 = pa(g2 - n2);
        var net = g2 - n2 - p2 - h2 - adv - loans;
        var k = function (n) { return n.toFixed(2); };
        document.getElementById('pvGross').textContent = k(grossWithBonus);
        document.getElementById('pvNapsa').textContent = k(n2);
        document.getElementById('pvPaye').textContent = k(p2);
        document.getElementById('pvNhima').textContent = k(h2);
        document.getElementById('pvAdvLoans').textContent = k(adv + loans);
        document.getElementById('pvNet').textContent = k(net);
    }
    ['payEmp','payDays','payUnits','payOvertime','payBonus','payAdv','payLoans'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', upd);
    });
    upd();
})();
</script>
