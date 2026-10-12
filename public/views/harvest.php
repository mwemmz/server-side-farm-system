<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? (is_array($data) ? $data : []);

// B7: storage facilities that can receive this harvest.
$storageRecords = $data['storageRecords'] ?? [];
if (empty($storageRecords)) {
    try {
        $storageRecords = $pdo->query("SELECT id, name, unit, capacity, current_stock FROM storage_records ORDER BY name")->fetchAll();
    } catch (\Throwable $e) {
        $storageRecords = [];
    }
}
$storageOptions = [];
foreach ($storageRecords as $s) {
    $storageOptions[(string) $s['id']] = $s['name'] . ' — ' . rtrim(rtrim(number_format((float) $s['current_stock'], 2), '0'), '.') . '/' . ($s['capacity'] !== null ? rtrim(rtrim(number_format((float) $s['capacity'], 2), '0'), '.') : '∞') . ' ' . ($s['unit'] ?? '');
}
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Harvest Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Harvest Record</h2>
    <?php
    $fields = [
        ['name' => 'crop_id', 'label' => 'Crop', 'type' => 'combo', 'options' => FormHelper::cropOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a crop…', 'value' => $formData['crop_id'] ?? ''],
        ['name' => 'harvest_date', 'label' => 'Harvest Date', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $formData['harvest_date'] ?? date('Y-m-d')],
        ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'value' => $formData['quantity'] ?? '', 'suffix' => 'kg'],
        ['name' => 'quality', 'label' => 'Quality', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'harvest_quality'), 'allow_custom' => true, 'category' => 'harvest_quality', 'placeholder' => 'Grade A / B / C…', 'value' => $formData['quality'] ?? ''],
        ['name' => 'storage_record_id', 'label' => 'Store in', 'type' => 'combo', 'required' => false, 'options' => $storageOptions, 'allow_custom' => false, 'placeholder' => 'Not stored — leave blank', 'help' => 'Optional. Adds the harvested kg to that facility (kg or tonnes only).', 'value' => $formData['storage_record_id'] ?? ''],
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Harvest&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Existing Records</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($records as $record): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span>Crop ID: <?php echo htmlspecialchars($record['crop_id']); ?></span>
                    <span class="text-slate-400">·</span>
                    <span><?php echo htmlspecialchars($record['quantity']); ?> kg</span>
                    <span class="text-slate-400">·</span>
                    <span>Quality: <?php echo htmlspecialchars($record['quality'] ?? '—'); ?></span>
                    <?php if (!empty($record['storage_record_id'])): ?>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">stored</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No harvest records registered yet.</p>
    <?php endif; ?>
</div>
