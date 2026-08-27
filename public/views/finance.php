<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../config/env.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-3xl font-bold mb-6 text-green-800">Finance Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Finance Record</h2>
    <?php
    $fields = [
        ['name' => 'type', 'label' => 'Type (Income/Expense)', 'value' => $formData['type'] ?? ''],
        ['name' => 'amount', 'label' => 'Amount (K)', 'value' => $formData['amount'] ?? ''],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'value' => $formData['description'] ?? ''],
        ['name' => 'date', 'label' => 'Date', 'type' => 'date', 'value' => $formData['date'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Finance&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Existing Records</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($records as $record): ?>
                <li class="mb-2"><?php echo htmlspecialchars($record['description']); ?> - <?php echo money($record['amount']); ?> (<?php echo htmlspecialchars($record['type']); ?>) - <?php echo htmlspecialchars($record['date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No finance records registered yet.</p>
    <?php endif; ?>
</div>