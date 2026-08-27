<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-3xl font-bold mb-6 text-red-800">Pest Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Pest Record</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm ID', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'pest_name', 'label' => 'Pest Name', 'value' => $formData['pest_name'] ?? ''],
        ['name' => 'detected_date', 'label' => 'Detected Date', 'type' => 'date', 'value' => $formData['detected_date'] ?? ''],
        ['name' => 'action_taken', 'label' => 'Action Taken', 'type' => 'textarea', 'value' => $formData['action_taken'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Pest&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Existing Records</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($records as $record): ?>
                <li class="mb-2"><?php echo htmlspecialchars($record['pest_name']); ?> - <?php echo htmlspecialchars($record['detected_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No pest records registered yet.</p>
    <?php endif; ?>
</div>