<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-3xl font-bold mb-6 text-green-800">Harvest Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Harvest Record</h2>
    <?php
    $fields = [
        ['name' => 'crop_id', 'label' => 'Crop ID', 'value' => $formData['crop_id'] ?? ''],
        ['name' => 'harvest_date', 'label' => 'Harvest Date', 'type' => 'date', 'value' => $formData['harvest_date'] ?? ''],
        ['name' => 'quantity', 'label' => 'Quantity', 'value' => $formData['quantity'] ?? ''],
        ['name' => 'quality', 'label' => 'Quality', 'value' => $formData['quality'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Harvest&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Existing Records</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($records as $record): ?>
                <li class="mb-2">Crop ID: <?php echo htmlspecialchars($record['crop_id']); ?> - Quantity: <?php echo htmlspecialchars($record['quantity']); ?>, Quality: <?php echo htmlspecialchars($record['quality']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No harvest records registered yet.</p>
    <?php endif; ?>
</div>