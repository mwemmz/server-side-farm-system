<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../config/env.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Sales Records</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Sale</h2>
    <?php
    $fields = [
        ['name' => 'customer_name', 'label' => 'Customer Name', 'value' => $formData['customer_name'] ?? ''],
        ['name' => 'amount', 'label' => 'Amount (K)', 'value' => $formData['amount'] ?? ''],
        ['name' => 'sale_date', 'label' => 'Sale Date', 'type' => 'date', 'value' => $formData['sale_date'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Sales&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Sales Records</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($records as $record): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm"><?php echo htmlspecialchars($record['customer_name']); ?>: <?php echo money($record['amount']); ?> on <?php echo htmlspecialchars($record['sale_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No sales records found.</p>
    <?php endif; ?>
</div>