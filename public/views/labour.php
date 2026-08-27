<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$labour = $data['labour'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>
<h1 class="text-3xl font-bold mb-6 text-green-800">Labour Management</h1>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Labour Record</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm ID', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'name', 'label' => 'Name', 'value' => $formData['name'] ?? ''],
        ['name' => 'role', 'label' => 'Role', 'value' => $formData['role'] ?? ''],
        ['name' => 'attendance_date', 'label' => 'Date', 'type' => 'date', 'value' => $formData['attendance_date'] ?? '']
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Labour&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Labour Records</h2>
    <?php if (isset($labour) && !empty($labour)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($labour as $item): ?>
                <li class="mb-2">Name: <?php echo htmlspecialchars($item['name']); ?>, Role: <?php echo htmlspecialchars($item['role']); ?> (<?php echo htmlspecialchars($item['attendance_date']); ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No labour records registered.</p>
    <?php endif; ?>
</div>
