<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors   = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$items    = $data['items'] ?? ($data ?? []);
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Equipment Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Equipment</h2>
    <?php
    $fields = [
        ['name' => 'name', 'label' => 'Equipment Name', 'value' => $formData['name'] ?? ''],
        ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Operational' => 'Operational', 'Needs Maintenance' => 'Needs Maintenance', 'Under Repair' => 'Under Repair', 'Retired' => 'Retired'], 'placeholder' => 'Select a status…', 'value' => $formData['status'] ?? ''],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Equipment&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Registered Equipment</h2>
    <?php if (isset($items) && !empty($items)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($items as $item): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex items-center justify-between gap-3">
                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                    <span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800"><?php echo htmlspecialchars($item['maintenance_status']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500">No equipment registered yet.</p>
    <?php endif; ?>
</div>
