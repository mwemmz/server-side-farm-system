<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../config/env.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
$groups = $data['groups'] ?? [];
$unpaid = $data['unpaid'] ?? [];
$filters = $data['filters'] ?? [];
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Finance Management</h1>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <a href="index.php?module=Finance&action=loans" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold shadow-sm hover:bg-slate-50 transition-colors">Loans</a>
    <a href="index.php?module=Finance&action=budgets" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold shadow-sm hover:bg-slate-50 transition-colors">Budgets</a>
</div>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Finance Record</h2>
    <?php
    $fields = [
        ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['income' => 'Income', 'expense' => 'Expense'], 'placeholder' => 'Select a type…', 'value' => $formData['type'] ?? '', 'help' => 'Pick Income or Expense first — the Category list then follows it.'],
        ['name' => 'category', 'label' => 'Category', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'finance_category'), 'allow_custom' => true, 'category' => 'finance_category', 'depends_on' => 'type', 'placeholder' => 'Pick a type first, then a category…', 'value' => $formData['category'] ?? ''],
        ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'value' => $formData['amount'] ?? '', 'suffix' => 'K'],
        ['name' => 'farm_id', 'label' => 'Farm', 'type' => 'combo', 'required' => false, 'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a farm…', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'payment_method', 'label' => 'Payment Method', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'payment_method'), 'allow_custom' => true, 'category' => 'payment_method', 'placeholder' => 'Cash, Mobile money…', 'value' => $formData['payment_method'] ?? ''],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'value' => $formData['description'] ?? ''],
        ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $formData['date'] ?? date('Y-m-d')]
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Finance&action=add', 'POST', $errors);
    ?>
</div>

<?php if (!empty($unpaid)): ?>
<div class="glass-card p-4 sm:p-6 mb-6 border border-amber-200">
    <h2 class="text-lg font-semibold mb-3 text-amber-800">Unpaid / Part-paid (<?php echo count($unpaid); ?>)</h2>
    <ul class="space-y-2">
        <?php foreach ($unpaid as $u):
            $overdue = !empty($u['due_date']) && strtotime($u['due_date']) < strtotime('today');
        ?>
            <li class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-slate-700 bg-amber-50/70 border border-amber-100 rounded-xl px-3.5 py-2.5">
                <span class="font-semibold <?php echo $u['type'] === 'income' ? 'text-emerald-700' : 'text-red-700'; ?>"><?php echo htmlspecialchars(ucfirst($u['type'])); ?></span>
                <span class="font-mono font-semibold"><?php echo money($u['amount']); ?></span>
                <span class="text-slate-500"><?php echo htmlspecialchars($u['description']); ?></span>
                <?php if (!empty($u['due_date'])): ?>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $overdue ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'; ?>">
                        <?php echo $overdue ? 'Overdue' : 'Due'; ?> <?php echo htmlspecialchars($u['due_date']); ?>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Filter Records</h2>
    <form method="get" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <input type="hidden" name="module" value="Finance">
        <input type="hidden" name="action" value="manage">
        <label class="text-sm"><span class="block font-semibold text-slate-700 mb-1">Type</span>
            <select name="type" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">All</option>
                <option value="income" <?php echo (($filters['type'] ?? '') === 'income') ? 'selected' : ''; ?>>Income</option>
                <option value="expense" <?php echo (($filters['type'] ?? '') === 'expense') ? 'selected' : ''; ?>>Expense</option>
            </select>
        </label>
        <label class="text-sm"><span class="block font-semibold text-slate-700 mb-1">Category</span>
            <input type="text" name="category" value="<?php echo htmlspecialchars($filters['category'] ?? ''); ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white" placeholder="Any">
        </label>
        <label class="text-sm"><span class="block font-semibold text-slate-700 mb-1">Payment status</span>
            <select name="payment_status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">All</option>
                <option value="paid" <?php echo (($filters['payment_status'] ?? '') === 'paid') ? 'selected' : ''; ?>>Paid</option>
                <option value="unpaid" <?php echo (($filters['payment_status'] ?? '') === 'unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                <option value="partial" <?php echo (($filters['payment_status'] ?? '') === 'partial') ? 'selected' : ''; ?>>Partial</option>
            </select>
        </label>
        <label class="text-sm"><span class="block font-semibold text-slate-700 mb-1">Farm</span>
            <select name="farm_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">All farms</option>
                <?php foreach (FormHelper::farmOptions($pdo) as $val => $lab): ?>
                    <option value="<?php echo (int) $val; ?>" <?php echo ((string)($filters['farm_id'] ?? '') === (string)$val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="text-sm"><span class="block font-semibold text-slate-700 mb-1">From</span>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
        </label>
        <label class="text-sm"><span class="block font-semibold text-slate-700 mb-1">To</span>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
        </label>
        <div class="flex items-end gap-2">
            <button type="submit" class="bg-green-700 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">Apply</button>
            <a href="index.php?module=Finance&action=manage" class="text-sm font-semibold text-slate-600 px-3 py-2">Clear</a>
        </div>
    </form>
</div>

<?php if (!empty($groups)): ?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Subtotals by Category</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
        <?php
        $byType = ['income' => [], 'expense' => []];
        foreach ($groups as $g) { $byType[$g['type']][] = $g; }
        foreach (['income' => 'Income', 'expense' => 'Expense'] as $tkey => $tlabel):
            $rows = $byType[$tkey];
            if (empty($rows)) continue;
            $sum = 0; foreach ($rows as $r) { $sum += (float) $r['total']; }
        ?>
        <div>
            <h3 class="text-sm font-bold uppercase tracking-wide mb-2 <?php echo $tkey === 'income' ? 'text-emerald-700' : 'text-red-700'; ?>"><?php echo $tlabel; ?> — <?php echo money($sum); ?></h3>
            <ul class="space-y-1.5">
                <?php foreach ($rows as $r): ?>
                    <li class="flex items-center justify-between text-sm text-slate-700 bg-white/50 border border-white/60 rounded-lg px-3 py-2">
                        <span><?php echo htmlspecialchars($r['cat']); ?> <span class="text-slate-400">(<?php echo (int) $r['n']; ?>)</span></span>
                        <span class="font-mono font-semibold"><?php echo money($r['total']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Existing Records</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2.5 pr-3">Date</th>
                        <th class="py-2.5 pr-3">Description</th>
                        <th class="py-2.5 pr-3">Category</th>
                        <th class="py-2.5 pr-3 text-right">Amount</th>
                        <th class="py-2.5">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr class="border-b border-slate-100 last:border-0">
                            <td class="py-3 pr-3 whitespace-nowrap"><?php echo htmlspecialchars($record['date']); ?></td>
                            <td class="py-3 pr-3"><?php echo htmlspecialchars($record['description']); ?></td>
                            <td class="py-3 pr-3"><?php echo htmlspecialchars($record['category'] ?: '—'); ?></td>
                            <td class="py-3 pr-3 text-right font-mono font-semibold <?php echo $record['type'] === 'income' ? 'text-emerald-700' : 'text-red-700'; ?>">
                                <?php echo ($record['type'] === 'income' ? '+' : '−') . money($record['amount']); ?>
                            </td>
                            <td class="py-3">
                                <?php $ps = $record['payment_status'] ?? 'paid'; ?>
                                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $ps === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'; ?>"><?php echo htmlspecialchars($ps); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No finance records registered yet.</p>
    <?php endif; ?>
</div>