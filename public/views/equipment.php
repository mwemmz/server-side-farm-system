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
        ['name' => 'equipment_type', 'label' => 'Type', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'equipment_type'), 'allow_custom' => true, 'category' => 'equipment_type', 'placeholder' => 'Tractor, Pump, Sprayer…', 'value' => $formData['equipment_type'] ?? ''],
        ['name' => 'status', 'label' => 'Status', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'equipment_status'), 'allow_custom' => true, 'category' => 'equipment_status', 'placeholder' => 'Operational, Under maintenance…', 'value' => $formData['status'] ?? ''],
        ['name' => 'farm_id', 'label' => 'Farm', 'type' => 'combo', 'required' => false, 'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a farm…', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'purchase_date', 'label' => 'Purchase Date', 'type' => 'date', 'max' => date('Y-m-d'), 'required' => false, 'value' => $formData['purchase_date'] ?? '']
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
