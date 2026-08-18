<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-3xl font-bold mb-6 text-green-800">Procurement Records</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="bg-white p-6 rounded-lg shadow mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Procurement</h2>
    <?php
    $fields = [
        ['name' => 'supplier_id', 'label' => 'Supplier ID', 'value' => $formData['supplier_id'] ?? ''],
        ['name' => 'item_name', 'label' => 'Item Name', 'value' => $formData['item_name'] ?? ''],
        ['name' => 'quantity', 'label' => 'Quantity', 'value' => $formData['quantity'] ?? ''],
        ['name' => 'cost', 'label' => 'Cost', 'value' => $formData['cost'] ?? ''],
        ['name' => 'purchase_date', 'label' => 'Purchase Date', 'type' => 'date', 'value' => $formData['purchase_date'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Procurement&action=add', 'POST', $errors);
    ?>
</div>

<div class="bg-white p-6 rounded-lg shadow">
    <h2 class="text-xl font-semibold mb-4">Procurement Records</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($records as $record): ?>
                <li class="mb-2"><?php echo htmlspecialchars($record['item_name']); ?>: <?php echo htmlspecialchars($record['quantity']); ?> at <?php echo htmlspecialchars($record['cost']); ?> (Date: <?php echo htmlspecialchars($record['purchase_date']); ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No procurement records found.</p>
    <?php endif; ?>
</div>