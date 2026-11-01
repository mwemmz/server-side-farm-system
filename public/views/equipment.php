<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../src/Modules/Equipment/EquipmentModel.php';

$successMessage = SessionHelper::getFlash('success');
$errorMessage   = SessionHelper::getFlash('error');
$errors   = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$items    = $data['items'] ?? (is_array($data) ? $data : []);

// Spare parts available for stock-out during a service.
$spareOptions = [];
try {
    foreach ($pdo->query("SELECT id, name, unit, quantity FROM inventory ORDER BY name")->fetchAll() as $iv) {
        $spareOptions[(string) $iv['id']] = $iv['name'] . ' — ' . rtrim(rtrim(number_format((float) $iv['quantity'], 2), '0'), '.') . ' ' . ($iv['unit'] ?? '');
    }
} catch (\Throwable $e) {
    error_log('equipment view inventory options: ' . $e->getMessage());
}
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Equipment Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>
<?php if ($errorMessage): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Equipment</h2>
    <?php
    $fields = [
        ['name' => 'name', 'label' => 'Equipment Name', 'value' => $formData['name'] ?? ''],
        ['name' => 'equipment_type', 'label' => 'Type', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'equipment_type'), 'allow_custom' => true, 'category' => 'equipment_type', 'placeholder' => 'Tractor, Pump, Sprayer…', 'value' => $formData['equipment_type'] ?? '', 'help' => 'Picking a type fills a sensible service interval below.'],
        ['name' => 'status', 'label' => 'Status', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'equipment_status'), 'allow_custom' => true, 'category' => 'equipment_status', 'placeholder' => 'Operational, Under maintenance…', 'value' => $formData['status'] ?? ''],
        ['name' => 'farm_id', 'label' => 'Farm', 'type' => 'combo', 'required' => false, 'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a farm…', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'purchase_date', 'label' => 'Purchase Date', 'type' => 'date', 'max' => date('Y-m-d'), 'required' => false, 'value' => $formData['purchase_date'] ?? ''],
        ['name' => 'service_interval_days', 'label' => 'Service every', 'type' => 'number', 'required' => false, 'suffix' => 'days', 'value' => $formData['service_interval_days'] ?? '', 'help' => 'How often it needs servicing. Drives the Due soon / Overdue alerts.'],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Equipment&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Registered Equipment</h2>
    <?php if (isset($items) && !empty($items)): ?>
        <ul class="space-y-3">
            <?php foreach ($items as $item):
                $eff = $item['effective_service_date'] ?? ($item['next_service_date'] ?? null);
                $state = EquipmentModel::serviceState($eff);
                $chip = $state === 'overdue' ? 'bg-red-100 text-red-800' : ($state === 'due' ? 'bg-amber-100 text-amber-800' : ($state === 'ok' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'));
                $label = $state === 'overdue' ? 'Service overdue' : ($state === 'due' ? 'Service due soon' : ($state === 'ok' ? 'Service OK' : 'No schedule'));
            ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="font-semibold"><?php echo htmlspecialchars($item['name']); ?></span>
                        <?php if (!empty($item['equipment_type'])): ?>
                            <span class="text-slate-400 text-xs"><?php echo htmlspecialchars($item['equipment_type']); ?></span>
                        <?php endif; ?>
                        <span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800"><?php echo htmlspecialchars($item['maintenance_status'] ?? ''); ?></span>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $chip; ?>"><?php echo htmlspecialchars($label); ?><?php echo $eff ? ' · ' . htmlspecialchars($eff) : ''; ?></span>
                    </div>
                    <?php if (!empty($item['service_interval_days'])): ?>
                        <p class="text-xs text-slate-500 mt-1">Serviced every <?php echo (int) $item['service_interval_days']; ?> days<?php echo !empty($item['last_service_date']) ? ' · last: ' . htmlspecialchars($item['last_service_date']) : ''; ?>.</p>
                    <?php endif; ?>

                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-semibold text-green-700 hover:text-green-800 select-none">Record a service</summary>
                        <form method="POST" action="index.php?module=Equipment&action=recordService" class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                            <?php echo \SessionHelper::csrfField(); ?>
                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                            <label class="block">
                                <span class="block text-[11px] font-semibold text-slate-500 mb-1">Service date</span>
                                <input type="date" name="service_date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required>
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-semibold text-slate-500 mb-1">Cost (K, optional)</span>
                                <input type="number" step="0.01" min="0" name="cost" placeholder="0.00" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-semibold text-slate-500 mb-1">Spare part used (optional)</span>
                                <select name="inventory_item_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <option value="">— none —</option>
                                    <?php foreach ($spareOptions as $vid => $vlabel): ?>
                                        <option value="<?php echo htmlspecialchars($vid); ?>"><?php echo htmlspecialchars($vlabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-[11px] font-semibold text-slate-500 mb-1">Quantity</span>
                                <input type="number" step="0.01" min="0" name="quantity_used" placeholder="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </label>
                            <label class="block sm:col-span-2 lg:col-span-3">
                                <span class="block text-[11px] font-semibold text-slate-500 mb-1">Note (optional)</span>
                                <input type="text" name="note" maxlength="255" placeholder="e.g. Oil change, new filters" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            </label>
                            <button type="submit" class="rounded-lg bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold px-4 py-2 shadow hover:shadow-emerald-700/40">Save service</button>
                        </form>
                    </details>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500">No equipment registered yet.</p>
    <?php endif; ?>
</div>

<script>
(function () {
    var typeEl = document.getElementById('equipment_type_input') || document.getElementById('equipment_type');
    var ivEl = document.getElementById('service_interval_days');
    if (!typeEl || !ivEl) return;
    var defaults = { tractor: 90, vehicle: 120, truck: 120, pump: 30, generator: 60, sprayer: 45, plough: 90, harvester: 90 };
    function fill() {
        if (ivEl.value) return;
        var k = (typeEl.value || '').trim().toLowerCase();
        if (defaults[k]) ivEl.value = defaults[k];
    }
    typeEl.addEventListener('change', fill);
    typeEl.addEventListener('blur', fill);
})();
</script>
