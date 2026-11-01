<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Storage Records</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Storage Record</h2>
    <?php
    $fields = [
        ['name' => 'name', 'label' => 'Storage Name', 'value' => $formData['name'] ?? ''],
        ['name' => 'facility_type', 'label' => 'Facility Type', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'storage_facility_type'), 'allow_custom' => true, 'category' => 'storage_facility_type', 'placeholder' => 'Grain silo, Cold store, Warehouse…', 'value' => $formData['facility_type'] ?? ''],
        ['name' => 'capacity', 'label' => 'Capacity', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'value' => $formData['capacity'] ?? ''],
        ['name' => 'current_stock', 'label' => 'Current Stock', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'value' => $formData['current_stock'] ?? ''],
        ['name' => 'unit', 'label' => 'Unit', 'type' => 'combo', 'required' => true, 'options' => FormHelper::lookupOptions($pdo, 'unit'), 'allow_custom' => true, 'category' => 'unit', 'placeholder' => 'kg, tonne…', 'value' => $formData['unit'] ?? 'kg']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Storage&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Existing Storage</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($records as $record): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1">
                        <span><?php echo htmlspecialchars($record['name']); ?>: <?php echo htmlspecialchars(strpos((string) $record['current_stock'], '.') !== false ? rtrim(rtrim((string) $record['current_stock'], '0'), '.') : $record['current_stock']); ?> / <?php echo htmlspecialchars(strpos((string) $record['capacity'], '.') !== false ? rtrim(rtrim((string) $record['capacity'], '0'), '.') : $record['capacity']); ?> <?php echo htmlspecialchars($record['unit'] ?? 'kg'); ?></span>
                        <details class="text-xs">
                            <summary class="cursor-pointer font-semibold text-green-700 hover:text-green-800 select-none">Edit</summary>
                            <form method="POST" action="/index.php?module=Storage&action=edit" class="mt-2 grid grid-cols-1 sm:grid-cols-[1fr_1fr_1fr_auto] gap-2 items-end">
                                <?php echo \SessionHelper::csrfField(); ?>
                                <?php if (!empty($errors['general'])): ?>
                                    <p class="col-span-full text-xs text-red-600"><?php echo htmlspecialchars($errors['general']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($errors['capacity'])): ?>
                                    <p class="col-span-full text-xs text-red-600"><?php echo htmlspecialchars($errors['capacity']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($errors['unit'])): ?>
                                    <p class="col-span-full text-xs text-red-600"><?php echo htmlspecialchars($errors['unit']); ?></p>
                                <?php endif; ?>
                                <input type="hidden" name="id" value="<?php echo (int) $record['id']; ?>">
                                <label class="block">
                                    <span class="block text-[11px] font-semibold text-slate-500 mb-1">Name</span>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($record['name']); ?>" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" required>
                                </label>
                                <label class="block">
                                    <span class="block text-[11px] font-semibold text-slate-500 mb-1">Capacity</span>
                                    <input type="number" step="0.01" min="0" name="capacity" value="<?php echo htmlspecialchars(strpos((string) $record['capacity'], '.') !== false ? rtrim(rtrim((string) $record['capacity'], '0'), '.') : $record['capacity']); ?>" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" required>
                                </label>
                                <label class="block">
                                    <span class="block text-[11px] font-semibold text-slate-500 mb-1">Unit</span>
                                    <input type="text" name="unit" value="<?php echo htmlspecialchars($record['unit'] ?? 'kg'); ?>" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                </label>
                                <button type="submit" class="rounded-lg bg-green-600 text-white text-xs font-bold px-3 py-2">Save</button>
                            </form>
                        </details>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No storage records found.</p>
    <?php endif; ?>
</div>