<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-3xl font-bold mb-6 text-green-800">Storage Records</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Storage Record</h2>
    <?php
    $fields = [
        ['name' => 'name', 'label' => 'Name', 'value' => $formData['name'] ?? ''],
        ['name' => 'capacity', 'label' => 'Capacity', 'value' => $formData['capacity'] ?? ''],
        ['name' => 'current_stock', 'label' => 'Current Stock', 'value' => $formData['current_stock'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Storage&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Existing Storage</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($records as $record): ?>
                <li class="mb-2"><?php echo htmlspecialchars($record['name']); ?>: <?php echo htmlspecialchars($record['current_stock']); ?> / <?php echo htmlspecialchars($record['capacity']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No storage records found.</p>
    <?php endif; ?>
</div>