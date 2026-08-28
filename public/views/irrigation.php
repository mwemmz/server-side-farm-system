<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$systems = $data['systems'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Irrigation Management</h1>

<div class="glass-card p-4 sm:p-6 mb-6">
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

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Existing Systems</h2>
    <?php if (isset($systems) && !empty($systems)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($systems as $item): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">Type: <?php echo htmlspecialchars($item['type']); ?>, Status: <?php echo htmlspecialchars($item['status']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No irrigation systems registered.</p>
    <?php endif; ?>
</div>
