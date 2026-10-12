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
            <?php foreach ($crops as $crop):
                $dueHtml = '';
                if (!empty($crop['expected_harvest_date'])) {
                    $today = new DateTimeImmutable('today');
                    try {
                        $due = new DateTimeImmutable($crop['expected_harvest_date']);
                        $days = (int) $today->diff($due)->format('%r%a');
                        if ($days < 0) {
                            $dueHtml = '<span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-800">Harvest ' . abs($days) . 'd overdue</span>';
                        } elseif ($days <= 7) {
                            $dueHtml = '<span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Harvest in ' . $days . ' day' . ($days === 1 ? '' : 's') . '</span>';
                        } else {
                            $dueHtml = '<span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">Harvest in ' . $days . ' days</span>';
                        }
                    } catch (\Throwable $e) { $dueHtml = ''; }
                }
            ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span><?php echo htmlspecialchars($crop['name']); ?></span>
                    <?php if (!empty($crop['variety'])): ?><span class="text-slate-400">- <?php echo htmlspecialchars($crop['variety']); ?></span><?php endif; ?>
                    <?php echo $dueHtml; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No crops registered yet.</p>
    <?php endif; ?>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-1 text-red-800">Pests &amp; Problems</h2>
    <p class="text-xs text-slate-500 mb-4">Report a pest or disease on a crop. The record is stored against the crop (and its farm).</p>
    <?php
    $pestErrors = $data['pestErrors'] ?? [];
    $pestData = $data['pestData'] ?? [];
    $issueOptions = [];
    foreach (($data['issueCatalog'] ?? []) as $ic) {
        $issueOptions[] = ['value' => $ic['name'], 'label' => $ic['name'] . (!empty($ic['kind']) ? ' (' . $ic['kind'] . ')' : '')];
    }
    $pestFields = [
        ['name' => 'crop_id', 'label' => 'Crop', 'type' => 'combo', 'options' => FormHelper::cropOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a crop…', 'value' => $pestData['crop_id'] ?? ''],
        ['name' => 'pest_name', 'label' => 'Pest / Disease', 'type' => 'combo', 'options' => $issueOptions, 'allow_custom' => true, 'category' => 'pest_name', 'placeholder' => 'e.g. Fall armyworm, or type another…', 'help' => 'Pick a common problem or type your own.', 'value' => $pestData['pest_name'] ?? ''],
        ['name' => 'issue_kind', 'label' => 'Type', 'type' => 'combo', 'required' => false, 'allow_custom' => true, 'options' => [['value' => 'pest', 'label' => 'Pest'], ['value' => 'disease', 'label' => 'Disease'], ['value' => 'deficiency', 'label' => 'Nutrient deficiency'], ['value' => 'disorder', 'label' => 'Disorder'], ['value' => 'other', 'label' => 'Other']], 'value' => $pestData['issue_kind'] ?? 'pest'],
        ['name' => 'severity', 'label' => 'Severity', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'pest_severity'), 'allow_custom' => true, 'category' => 'pest_severity', 'placeholder' => 'Low, Moderate, High…', 'value' => $pestData['severity'] ?? ''],
        ['name' => 'detected_date', 'label' => 'Detected Date', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $pestData['detected_date'] ?? date('Y-m-d')],
        ['name' => 'action_taken', 'label' => 'Action Taken', 'type' => 'textarea', 'value' => $pestData['action_taken'] ?? ''],
    ];
    echo FormHelper::generateForm($pestFields, '/index.php?module=Crop&action=addPest', 'POST', $pestErrors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Crop Pest Records</h2>
    <?php $cropPests = $data['pests'] ?? []; ?>
    <?php if (!empty($cropPests)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($cropPests as $p): ?>
                <?php
                $status = $p['status'] ?? 'open';
                $statusCls = $status === 'resolved' ? 'bg-green-100 text-green-800' : ($status === 'treated' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700');
                ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="font-semibold"><?php echo htmlspecialchars($p['pest_name']); ?></span>
                        <span class="text-slate-400">on</span>
                        <span><?php echo htmlspecialchars($p['crop_name']); ?></span>
                        <?php if (!empty($p['severity'])): ?>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-800"><?php echo htmlspecialchars($p['severity']); ?></span>
                        <?php endif; ?>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $statusCls; ?>"><?php echo htmlspecialchars($status); ?></span>
                        <span class="text-slate-400 text-xs"><?php echo htmlspecialchars($p['detected_date']); ?></span>
                    </div>
                    <?php if (!empty($p['action_taken'])): ?>
                        <p class="text-xs text-slate-500 mt-1"><?php echo nl2br(htmlspecialchars($p['action_taken'])); ?></p>
                    <?php endif; ?>
                    <?php if ($status !== 'resolved'): ?>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <?php if ($status === 'open'): ?>
                                <form method="POST" action="index.php?module=Crop&action=incidentStatus" class="inline">
                                    <?php echo \SessionHelper::csrfField(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                    <input type="hidden" name="status" value="treated">
                                    <button type="submit" class="text-xs font-semibold px-3 py-1 rounded-lg bg-amber-500 text-white hover:bg-amber-600">Mark treated</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="index.php?module=Crop&action=incidentStatus" class="inline">
                                <?php echo \SessionHelper::csrfField(); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                <input type="hidden" name="status" value="resolved">
                                <button type="submit" class="text-xs font-semibold px-3 py-1 rounded-lg bg-green-600 text-white hover:bg-green-700">Mark resolved</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No crop pest records yet.</p>
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
