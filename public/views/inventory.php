<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$items = $data['items'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>
<h1 class="text-3xl font-bold mb-6 text-green-800">Inventory Management</h1>

<div class="bg-white p-6 rounded-lg shadow mb-6">
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

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Inventory Items</h2>
    <?php if (isset($items) && !empty($items)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($items as $item): ?>
                <li class="mb-2"><?php echo htmlspecialchars($item['name']); ?>: <?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Inventory is empty.</p>
    <?php endif; ?>
</div>
