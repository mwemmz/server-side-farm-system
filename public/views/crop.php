<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$crops = $data['crops'] ?? ($data ?? []); // Handle both cases for index and add
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Crop Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Crop</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm', 'type' => 'combo', 'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a farm…', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'name', 'label' => 'Crop Name', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'crop_name'), 'allow_custom' => true, 'category' => 'crop_name', 'placeholder' => 'e.g. Maize, or type another crop…', 'value' => $formData['name'] ?? ''],
        ['name' => 'variety', 'label' => 'Variety', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'crop_variety'), 'allow_custom' => true, 'category' => 'crop_variety', 'placeholder' => 'e.g. SC 403 (optional)', 'value' => $formData['variety'] ?? ''],
        ['name' => 'planting_date', 'label' => 'Planting Date', 'type' => 'date', 'help' => 'Defaults to today.', 'value' => $formData['planting_date'] ?? date('Y-m-d')],
        ['name' => 'expected_harvest_date', 'label' => 'Expected Harvest Date', 'type' => 'date', 'required' => false, 'help' => 'Not sure? Leave blank, or enter the crop name to get an estimate.', 'value' => $formData['expected_harvest_date'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Crop&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Existing Crops</h2>
    <?php if (isset($crops) && !empty($crops)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($crops as $crop): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm"><?php echo htmlspecialchars($crop['name']); ?> - <?php echo htmlspecialchars($crop['variety'] ?? ''); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No crops registered yet.</p>
    <?php endif; ?>
</div>

<script>
(function () {
    var nameEl = document.getElementById('name_input') || document.getElementById('name');
    var plantEl = document.getElementById('planting_date');
    var harvEl = document.getElementById('expected_harvest_date');
    if (!nameEl || !plantEl || !harvEl) return;

    var days = { maize: 120, soybeans: 110, wheat: 120, groundnuts: 130, tomato: 90, cabbage: 80, onion: 120 };
    var userTouched = false;

    harvEl.addEventListener('input', function () { userTouched = true; });

    function fill() {
        if (userTouched) return;
        var key = (nameEl.value || '').trim().toLowerCase();
        var d = days[key];
        if (!d || !plantEl.value) return;
        var dt = new Date(plantEl.value + 'T00:00:00');
        if (isNaN(dt.getTime())) return;
        dt.setDate(dt.getDate() + d);
        var m = String(dt.getMonth() + 1).padStart(2, '0');
        var dd = String(dt.getDate()).padStart(2, '0');
        harvEl.value = dt.getFullYear() + '-' + m + '-' + dd;
        harvEl.dispatchEvent(new Event('change'));
    }

    nameEl.addEventListener('blur', fill);
    nameEl.addEventListener('change', fill);
    plantEl.addEventListener('change', fill);
    plantEl.addEventListener('input', fill);
})();
</script>
