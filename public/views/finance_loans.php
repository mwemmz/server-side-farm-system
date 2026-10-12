<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../config/env.php';

$success = SessionHelper::getFlash('success');
$loans  = $data['loans'] ?? [];
$totals = $data['totals'] ?? ['principal' => 0, 'balance' => 0];
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Loans</h1>

<?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
    <div class="glass-card p-5"><div class="text-xs uppercase font-bold text-slate-400">Active principal</div><div class="text-2xl font-bold text-slate-800 mt-1"><?php echo money($totals['principal']); ?></div></div>
    <div class="glass-card p-5"><div class="text-xs uppercase font-bold text-slate-400">Outstanding balance</div><div class="text-2xl font-bold text-red-600 mt-1"><?php echo money($totals['balance']); ?></div></div>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Loan</h2>
    <?php
    $fields = [
        ['name' => 'lender', 'label' => 'Lender', 'type' => 'text', 'value' => $formData['lender'] ?? ''],
        ['name' => 'principal', 'label' => 'Principal (K)', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'value' => $formData['principal'] ?? ''],
        ['name' => 'interest_rate', 'label' => 'Interest Rate (%)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'max' => '100', 'value' => $formData['interest_rate'] ?? '0', 'required' => false],
        ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'value' => $formData['start_date'] ?? date('Y-m-d'), 'required' => false],
        ['name' => 'due_date', 'label' => 'Due Date', 'type' => 'date', 'value' => $formData['due_date'] ?? '', 'required' => false],
        ['name' => 'balance', 'label' => 'Outstanding Balance (K)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'value' => $formData['balance'] ?? '', 'help' => 'Leave equal to principal if untouched.', 'required' => false],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'repaid' => 'Repaid', 'defaulted' => 'Defaulted'], 'value' => $formData['status'] ?? 'active'],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'value' => $formData['notes'] ?? '', 'required' => false],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Finance&action=loans', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Registered Loans</h2>
    <?php if (!empty($loans)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase text-slate-400 border-b border-slate-200">
                    <tr><th class="py-2 pr-4">Lender</th><th class="py-2 pr-4">Principal</th><th class="py-2 pr-4">Rate</th><th class="py-2 pr-4">Balance</th><th class="py-2 pr-4">Due</th><th class="py-2 pr-4">Status</th><th class="py-2 pr-4">Repayment</th></tr>
                </thead>
                <tbody>
                <?php foreach ($loans as $l): ?>
                    <tr class="border-b border-slate-100 text-slate-700">
                        <td class="py-2 pr-4 font-semibold"><?php echo htmlspecialchars($l['lender']); ?></td>
                        <td class="py-2 pr-4"><?php echo money($l['principal']); ?></td>
                        <td class="py-2 pr-4"><?php echo htmlspecialchars($l['interest_rate']); ?>%</td>
                        <td class="py-2 pr-4"><?php echo money($l['balance']); ?></td>
                        <td class="py-2 pr-4"><?php echo htmlspecialchars($l['due_date'] ?? '—'); ?></td>
                        <td class="py-2 pr-4"><span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $l['status'] === 'active' ? 'bg-amber-100 text-amber-800' : ($l['status'] === 'repaid' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-700'); ?>"><?php echo htmlspecialchars($l['status']); ?></span></td>
                        <td class="py-2 pr-4">
                            <?php if ((float) $l['balance'] > 0): ?>
                            <form method="post" action="index.php?module=Finance&action=loans" class="flex items-center gap-1.5">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                                <input type="hidden" name="loan_id" value="<?php echo (int) $l['id']; ?>">
                                <input type="number" name="repay_amount" step="0.01" min="0.01" max="<?php echo htmlspecialchars($l['balance']); ?>" placeholder="K" class="w-24 border border-slate-300 rounded-lg px-2 py-1 text-xs bg-white">
                                <button type="submit" class="text-[11px] font-bold bg-green-700 hover:bg-green-800 text-white px-2.5 py-1 rounded-lg">Pay</button>
                            </form>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Settled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-slate-500">No loans recorded yet.</p>
    <?php endif; ?>
</div>

<p class="mt-4"><a href="index.php?module=Finance&action=manage" class="text-sm font-semibold text-green-700 hover:text-green-800">← Back to Finance</a></p>
