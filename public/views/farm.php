<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Farm Management</h1>

<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$fields = [
    ['name' => 'name', 'label' => 'Farm Name'],
    ['name' => 'location', 'label' => 'Location'],
];
?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Farm</h2>
    <?php echo FormHelper::generateForm($fields, '?module=Farm&action=create'); ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($data as $farm): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm"><?php echo htmlspecialchars($farm['name']); ?> <span class="text-gray-500">- <?php echo htmlspecialchars($farm['location']); ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No farms registered yet.</p>
    <?php endif; ?>
</div>
