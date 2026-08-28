<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$labour = $data['labour'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Labour Management</h1>

<div class="glass-card p-4 sm:p-6 mb-6">
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

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Labour Records</h2>
    <?php if (isset($labour) && !empty($labour)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($labour as $item): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">Name: <?php echo htmlspecialchars($item['name']); ?>, Role: <?php echo htmlspecialchars($item['role']); ?> (<?php echo htmlspecialchars($item['attendance_date']); ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No labour records registered.</p>
    <?php endif; ?>
</div>
