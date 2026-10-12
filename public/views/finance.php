<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../config/env.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
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
        ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['income' => 'Income', 'expense' => 'Expense'], 'placeholder' => 'Select a type…', 'value' => $formData['type'] ?? ''],
        ['name' => 'category', 'label' => 'Category', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'finance_category'), 'allow_custom' => true, 'category' => 'finance_category', 'placeholder' => 'Seeds, Labour, Crop sales…', 'value' => $formData['category'] ?? ''],
        ['name' => 'amount', 'label' => 'Amount', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'value' => $formData['amount'] ?? '', 'suffix' => 'K'],
        ['name' => 'farm_id', 'label' => 'Farm', 'type' => 'combo', 'required' => false, 'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a farm…', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'payment_method', 'label' => 'Payment Method', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'payment_method'), 'allow_custom' => true, 'category' => 'payment_method', 'placeholder' => 'Cash, Mobile money…', 'value' => $formData['payment_method'] ?? ''],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'value' => $formData['description'] ?? ''],
        ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'value' => $formData['date'] ?? date('Y-m-d')]
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Finance&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Existing Records</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($records as $record): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm"><?php echo htmlspecialchars($record['description']); ?> - <?php echo money($record['amount']); ?> (<?php echo htmlspecialchars($record['type']); ?>) - <?php echo htmlspecialchars($record['date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No finance records registered yet.</p>
    <?php endif; ?>
</div>