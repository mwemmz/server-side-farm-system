<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$animals = $data['animals'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>
<h1 class="text-3xl font-bold mb-6 text-green-800">Livestock Management</h1>

<div class="bg-white p-6 rounded-lg shadow mb-6">
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

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Existing Animals</h2>
    <?php if (isset($animals) && !empty($animals)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($animals as $item): ?>
                <li class="mb-2">Type: <?php echo htmlspecialchars($item['type']); ?>, Breed: <?php echo htmlspecialchars($item['breed']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No animals registered.</p>
    <?php endif; ?>
</div>
