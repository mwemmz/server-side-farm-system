<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$animals = $data['animals'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$vaccinations = $data['vaccinations'] ?? [];
$vaccAnimals = $data['vaccAnimals'] ?? $animals;
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Livestock Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Livestock</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm', 'type' => 'combo', 'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a farm…', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'type', 'label' => 'Type', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'livestock_type'), 'allow_custom' => true, 'category' => 'livestock_type', 'placeholder' => 'e.g. Cattle, Goats, Poultry…', 'value' => $formData['type'] ?? ''],
        ['name' => 'breed', 'label' => 'Breed', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'livestock_breed'), 'allow_custom' => true, 'category' => 'livestock_breed', 'depends_on' => 'type', 'placeholder' => 'Pick a type first, then choose or type a breed…', 'value' => $formData['breed'] ?? '', 'help' => 'Common breeds are suggested after you choose a type. Type a new one if it is not listed.'],
        ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $formData['dob'] ?? '']
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Livestock&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Existing Animals</h2>
    <?php if (isset($animals) && !empty($animals)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($animals as $item): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">Type: <?php echo htmlspecialchars($item['type']); ?>, Breed: <?php echo htmlspecialchars($item['breed']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No animals registered.</p>
    <?php endif; ?>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Record a Vaccination</h2>
    <?php
    $vaccAnimalsOptions = [];
    foreach ($vaccAnimals as $a) {
        $vaccAnimalsOptions[(string) $a['id']] = '#' . $a['id'] . ' — ' . ucfirst($a['type'] ?? '') . ' ' . ($a['breed'] ?? 'Unknown');
    }
    $vaccFields = [
        ['name' => 'livestock_id', 'label' => 'Animal', 'type' => 'combo', 'options' => $vaccAnimalsOptions, 'allow_custom' => false, 'placeholder' => 'Type an animal #, type or breed…', 'value' => $formData['livestock_id'] ?? '', 'help' => 'The animal ID is the "#" number shown in the list.'],
        ['name' => 'vaccine_name', 'label' => 'Vaccine / Treatment', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'vaccine_name'), 'allow_custom' => true, 'category' => 'vaccine_name', 'placeholder' => 'e.g. FMD vaccine, or type another…', 'value' => $formData['vaccine_name'] ?? ''],
        ['name' => 'vaccination_date', 'label' => 'Date Vaccinated', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $formData['vaccination_date'] ?? date('Y-m-d')],
        ['name' => 'next_due_date', 'label' => 'Next Due Date', 'type' => 'date', 'required' => false, 'value' => $formData['next_due_date'] ?? '', 'help' => 'Leave blank if not a repeat treatment.'],
    ];
    echo FormHelper::generateForm($vaccFields, 'index.php?module=Livestock&action=vaccinate', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Vaccination Records</h2>
    <?php if (isset($vaccinations) && !empty($vaccinations)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2.5 pr-3">Animal</th>
                        <th class="py-2.5 pr-3">Vaccine</th>
                        <th class="py-2.5 pr-3">Date Vaccinated</th>
                        <th class="py-2.5 pr-3">Next Due</th>
                        <th class="py-2.5">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vaccinations as $v):
                        $animal = trim(ucfirst($v['animal_type'] ?? '') . ' ' . ($v['animal_breed'] ?? ''));
                        $next = $v['next_due_date'] ?? null;
                        if ($next) {
                            $ts = strtotime((string) $next);
                            if ($ts < strtotime('today')) { $status = 'Overdue'; $cls = 'text-red-700 bg-red-100'; }
                            elseif ($ts < strtotime('+30 days')) { $status = 'Due soon'; $cls = 'text-amber-700 bg-amber-100'; }
                            else { $status = 'Scheduled'; $cls = 'text-emerald-700 bg-emerald-100'; }
                        } else { $status = 'No next date'; $cls = 'text-slate-600 bg-slate-100'; }
                    ?>
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-3 pr-3 font-semibold"><?php echo htmlspecialchars($animal ?: ('Animal #' . ($v['livestock_id'] ?? '—'))); ?></td>
                        <td class="py-3 pr-3"><?php echo htmlspecialchars($v['vaccine_name']); ?></td>
                        <td class="py-3 pr-3"><?php echo htmlspecialchars($v['vaccination_date']); ?></td>
                        <td class="py-3 pr-3"><?php echo htmlspecialchars($next ? $next : '—'); ?></td>
                        <td class="py-3"><span class="text-xs font-bold px-2.5 py-1 rounded-full <?php echo $cls; ?>"><?php echo $status; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No vaccination records yet.</p>
    <?php endif; ?>
</div>

<div class="glass-card p-4 sm:p-6 mt-6">
    <h2 class="text-xl font-semibold mb-1 text-red-800">Pests &amp; Health Problems</h2>
    <p class="text-xs text-slate-500 mb-4">Report a pest, parasite or disease on an animal. The record is stored against the animal (and its farm).</p>
    <?php
    $pestErrors = $data['pestErrors'] ?? [];
    $pestData = $data['pestData'] ?? [];
    $livestockPestOptions = [];
    foreach ($animals as $a) {
        $livestockPestOptions[(string) $a['id']] = '#' . $a['id'] . ' — ' . ucfirst($a['type'] ?? '') . ' ' . ($a['breed'] ?? 'Unknown');
    }
    $pestFields = [
        ['name' => 'livestock_id', 'label' => 'Animal', 'type' => 'combo', 'options' => $livestockPestOptions, 'allow_custom' => false, 'placeholder' => 'Type an animal #, type or breed…', 'value' => $pestData['livestock_id'] ?? '', 'help' => 'The animal ID is the "#" number shown in the list.'],
        ['name' => 'pest_name', 'label' => 'Pest / Disease', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'disease_name'), 'allow_custom' => true, 'category' => 'disease_name', 'placeholder' => 'e.g. Foot rot, Ticks, or type another…', 'value' => $pestData['pest_name'] ?? ''],
        ['name' => 'severity', 'label' => 'Severity', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'pest_severity'), 'allow_custom' => true, 'category' => 'pest_severity', 'placeholder' => 'Low, Moderate, High…', 'value' => $pestData['severity'] ?? ''],
        ['name' => 'detected_date', 'label' => 'Detected Date', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $pestData['detected_date'] ?? date('Y-m-d')],
        ['name' => 'action_taken', 'label' => 'Action Taken', 'type' => 'textarea', 'value' => $pestData['action_taken'] ?? ''],
    ];
    echo FormHelper::generateForm($pestFields, 'index.php?module=Livestock&action=addPest', 'POST', $pestErrors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6 mt-6">
    <h2 class="text-xl font-semibold mb-4">Animal Health Records</h2>
    <?php $animalPests = $data['pests'] ?? []; ?>
    <?php if (!empty($animalPests)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($animalPests as $p):
                $animalLabel = trim(ucfirst($p['animal_type'] ?? '') . ' ' . ($p['animal_breed'] ?? ''));
                if (empty($animalLabel)) $animalLabel = 'Animal #' . ($p['livestock_id'] ?? '—');
            ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-semibold"><?php echo htmlspecialchars($p['pest_name']); ?></span>
                    <span class="text-slate-400">on</span>
                    <span><?php echo htmlspecialchars($animalLabel); ?></span>
                    <?php if (!empty($p['severity'])): ?>
                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-800"><?php echo htmlspecialchars($p['severity']); ?></span>
                    <?php endif; ?>
                    <span class="text-slate-400 text-xs"><?php echo htmlspecialchars($p['detected_date']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No animal health records yet.</p>
    <?php endif; ?>
</div>