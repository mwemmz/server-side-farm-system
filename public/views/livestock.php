<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$animals = $data['animals'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Livestock Management</h1>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Livestock</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm ID', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'type', 'label' => 'Type', 'value' => $formData['type'] ?? ''],
        ['name' => 'breed', 'label' => 'Breed', 'value' => $formData['breed'] ?? ''],
        ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'value' => $formData['dob'] ?? '']
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Livestock&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Existing Animals</h2>
    <?php if (isset($animals) && !empty($animals)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($animals as $item): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">Type: <?php echo htmlspecialchars($item['type']); ?>, Breed: <?php echo htmlspecialchars($item['breed']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No animals registered.</p>
    <?php endif; ?>
</div>
