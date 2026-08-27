<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$crops = $data['crops'] ?? ($data ?? []); // Handle both cases for index and add
?>

<h1 class="text-3xl font-bold mb-6 text-green-800">Crop Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Crop</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm ID', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'name', 'label' => 'Name', 'value' => $formData['name'] ?? ''],
        ['name' => 'variety', 'label' => 'Variety', 'value' => $formData['variety'] ?? ''],
        ['name' => 'planting_date', 'label' => 'Planting Date', 'type' => 'date', 'value' => $formData['planting_date'] ?? ''],
        ['name' => 'expected_harvest_date', 'label' => 'Expected Harvest Date', 'type' => 'date', 'value' => $formData['expected_harvest_date'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Crop&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Existing Crops</h2>
    <?php if (isset($crops) && !empty($crops)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($crops as $crop): ?>
                <li class="mb-2"><?php echo htmlspecialchars($crop['name']); ?> - <?php echo htmlspecialchars($crop['variety']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No crops registered yet.</p>
    <?php endif; ?>
</div>
