<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-3xl font-bold mb-6 text-green-800">Analytics Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Analytics Entry</h2>
    <?php
    $fields = [
        ['name' => 'module_name', 'label' => 'Module Name', 'value' => $formData['module_name'] ?? ''],
        ['name' => 'data_points', 'label' => 'Data Points', 'type' => 'textarea', 'value' => $formData['data_points'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Analytics&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Analytics Data</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($records as $record): ?>
                <li class="mb-2">Module: <?php echo htmlspecialchars($record['module_name']); ?>, Data: <?php echo htmlspecialchars($record['data_points']); ?>, Date: <?php echo htmlspecialchars($record['recorded_at']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No analytics data available.</p>
    <?php endif; ?>
</div>