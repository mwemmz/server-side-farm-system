<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-3xl font-bold mb-6 text-green-800">Sales Records</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Sale</h2>
    <?php
    $fields = [
        ['name' => 'customer_name', 'label' => 'Customer Name', 'value' => $formData['customer_name'] ?? ''],
        ['name' => 'amount', 'label' => 'Amount', 'value' => $formData['amount'] ?? ''],
        ['name' => 'sale_date', 'label' => 'Sale Date', 'type' => 'date', 'value' => $formData['sale_date'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Sales&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Sales Records</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($records as $record): ?>
                <li class="mb-2"><?php echo htmlspecialchars($record['customer_name']); ?>: <?php echo htmlspecialchars($record['amount']); ?> on <?php echo htmlspecialchars($record['sale_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No sales records found.</p>
    <?php endif; ?>
</div>