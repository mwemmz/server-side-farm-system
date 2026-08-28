<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Supplier Records</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Supplier</h2>
    <?php
    $fields = [
        ['name' => 'name', 'label' => 'Name', 'value' => $formData['name'] ?? ''],
        ['name' => 'contact_info', 'label' => 'Contact Info', 'value' => $formData['contact_info'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Supplier&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Existing Suppliers</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($records as $record): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm"><?php echo htmlspecialchars($record['name']); ?>: <?php echo htmlspecialchars($record['contact_info']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No suppliers registered.</p>
    <?php endif; ?>
</div>