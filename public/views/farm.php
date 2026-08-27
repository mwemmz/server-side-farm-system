<h1 class="text-3xl font-bold mb-6 text-green-800">Farm Management</h1>

<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
$fields = [
    ['name' => 'name', 'label' => 'Farm Name'],
    ['name' => 'location', 'label' => 'Location'],
];
?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Farm</h2>
    <?php echo FormHelper::generateForm($fields, '?module=Farm&action=create'); ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $farm): ?>
                <li class="mb-2"><?php echo htmlspecialchars($farm['name']); ?> <span class="text-gray-500">- <?php echo htmlspecialchars($farm['location']); ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No farms registered yet.</p>
    <?php endif; ?>
</div>
