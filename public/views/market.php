<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../config/env.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-3xl font-bold mb-6 text-green-800">Market Data</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Market Entry</h2>
    <?php
    $fields = [
        ['name' => 'crop_name', 'label' => 'Crop Name', 'value' => $formData['crop_name'] ?? ''],
        ['name' => 'price', 'label' => 'Price (K per kg)', 'value' => $formData['price'] ?? ''],
        ['name' => 'market_date', 'label' => 'Market Date', 'type' => 'date', 'value' => $formData['market_date'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Market&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Market Data</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($records as $record): ?>
                <li class="mb-2"><?php echo htmlspecialchars($record['crop_name']); ?>: <?php echo money($record['price']); ?>/kg on <?php echo htmlspecialchars($record['market_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No market data available.</p>
    <?php endif; ?>
</div>