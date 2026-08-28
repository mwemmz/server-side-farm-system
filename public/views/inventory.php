<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$items = $data['items'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Inventory Management</h1>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Inventory Item</h2>
    <?php
    $fields = [
        ['name' => 'name', 'label' => 'Item Name', 'value' => $formData['name'] ?? ''],
        ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'value' => $formData['quantity'] ?? ''],
        ['name' => 'unit', 'label' => 'Unit', 'value' => $formData['unit'] ?? '']
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Inventory&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Inventory Items</h2>
    <?php if (isset($items) && !empty($items)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($items as $item): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm"><?php echo htmlspecialchars($item['name']); ?>: <?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Inventory is empty.</p>
    <?php endif; ?>
</div>
