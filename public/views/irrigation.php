<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$systems = $data['systems'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>
<h1 class="text-3xl font-bold mb-6 text-green-800">Irrigation Management</h1>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Irrigation System</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm ID', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'type', 'label' => 'Type', 'value' => $formData['type'] ?? ''],
        ['name' => 'status', 'label' => 'Status', 'value' => $formData['status'] ?? '']
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Irrigation&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Existing Systems</h2>
    <?php if (isset($systems) && !empty($systems)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($systems as $item): ?>
                <li class="mb-2">Type: <?php echo htmlspecialchars($item['type']); ?>, Status: <?php echo htmlspecialchars($item['status']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No irrigation systems registered.</p>
    <?php endif; ?>
</div>
