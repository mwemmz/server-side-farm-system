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
        ['name' => 'crop_name', 'label' => 'Product Sold', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'crop_name'), 'allow_custom' => true, 'category' => 'crop_name', 'placeholder' => 'Maize, Livestock…', 'value' => $formData['crop_name'] ?? ''],
        ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => false, 'value' => $formData['quantity'] ?? ''],
        ['name' => 'unit', 'label' => 'Unit', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'unit'), 'allow_custom' => true, 'category' => 'unit', 'placeholder' => 'kg, bag, head…', 'value' => $formData['unit'] ?? ''],
        ['name' => 'amount', 'label' => 'Total Amount', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'value' => $formData['amount'] ?? '', 'suffix' => 'K'],
        ['name' => 'payment_method', 'label' => 'Payment Method', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'payment_method'), 'allow_custom' => true, 'category' => 'payment_method', 'placeholder' => 'Cash, Mobile money…', 'value' => $formData['payment_method'] ?? ''],
        ['name' => 'sale_date', 'label' => 'Sale Date', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $formData['sale_date'] ?? date('Y-m-d')]
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