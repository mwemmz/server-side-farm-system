<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../src/Helpers/LookupHelper.php';

$successMessage = SessionHelper::getFlash('success');
$animals = $data['animals'] ?? $data;
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$vaccinations = $data['vaccinations'] ?? [];
$vaccAnimals = $data['vaccAnimals'] ?? $animals;
$treatments = $data['treatments'] ?? [];
$withdrawals = $data['withdrawals'] ?? [];
$vaccineIntervals = LookupHelper::vaccineIntervals($pdo);
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
                <?php $wUntil = $withdrawals[(int) $item['id']] ?? null; ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span>Type: <?php echo htmlspecialchars($item['type']); ?>, Breed: <?php echo htmlspecialchars($item['breed']); ?></span>
                    <?php if (!empty($item['tag_no'])): ?><span class="text-slate-400">Tag: <span class="font-semibold text-slate-600"><?php echo htmlspecialchars($item['tag_no']); ?></span></span><?php endif; ?>
                    <?php if ($wUntil): ?><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-800">Withdrawal until <?php echo htmlspecialchars($wUntil); ?></span><?php endif; ?>
                </li>
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
        ['name' => 'species', 'label' => 'Species', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'livestock_type'), 'allow_custom' => false, 'placeholder' => 'e.g. Cattle, Poultry…', 'value' => $formData['species'] ?? '', 'help' => 'Choose the species to filter the vaccine list.'],
        ['name' => 'vaccine_name', 'label' => 'Vaccine', 'type' => 'combo', 'options' => LookupHelper::vaccineOptions($pdo), 'allow_custom' => true, 'category' => 'vaccine_name', 'depends_on' => 'species', 'placeholder' => 'Pick a species first, then a vaccine…', 'value' => $formData['vaccine_name'] ?? '', 'help' => 'Starter schedule values — confirm intervals with a veterinarian.'],
        ['name' => 'vaccination_date', 'label' => 'Date Vaccinated', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $formData['vaccination_date'] ?? date('Y-m-d')],
        ['name' => 'next_due_date', 'label' => 'Next Due Date', 'type' => 'date', 'required' => false, 'value' => $formData['next_due_date'] ?? '', 'help' => 'Auto-filled from the vaccine schedule; you can change it.'],
        ['name' => 'dose_number', 'label' => 'Dose number', 'type' => 'number', 'required' => false, 'value' => $formData['dose_number'] ?? ''],
        ['name' => 'batch_number', 'label' => 'Batch / lot no.', 'type' => 'text', 'required' => false, 'value' => $formData['batch_number'] ?? ''],
        ['name' => 'cost', 'label' => 'Cost (K)', 'type' => 'number', 'required' => false, 'value' => $formData['cost'] ?? ''],
    ];
    echo FormHelper::generateForm($vaccFields, 'index.php?module=Livestock&action=vaccinate', 'POST', $errors);
    ?>
</div>

<script>
(function () {
  var intervals = <?php echo json_encode($vaccineIntervals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  var vaccineHidden = document.querySelector('[name="vaccine_name"]');
  var dateInput = document.querySelector('[name="vaccination_date"]');
  var dueInput = document.querySelector('[name="next_due_date"]');
  if (!vaccineHidden || !dateInput || !dueInput) return;
  function recompute() {
    if (dueInput.value !== '' && dueInput.dataset.ffManual === '1') return;
    var info = intervals[vaccineHidden.value];
    if (!info || !info.interval) return;
    var base = dateInput.value || new Date().toISOString().slice(0, 10);
    var d = new Date(base + 'T00:00:00');
    d.setDate(d.getDate() + parseInt(info.interval, 10));
    dueInput.value = d.toISOString().slice(0, 10);
  }
  vaccineHidden.addEventListener('ff-combo-change', recompute);
  vaccineHidden.addEventListener('change', recompute);
  dateInput.addEventListener('change', recompute);
  dueInput.addEventListener('input', function () { dueInput.dataset.ffManual = '1'; });
})();
</script>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Vaccinate a Batch</h2>
    <p class="text-xs text-slate-500 mb-4">Tick several animals and record one vaccine and date for all of them at once.</p>
    <?php
    $batchErrors = $data['batchErrors'] ?? [];
    $batchData = $data['batchData'] ?? [];
    $batchAnimals = [];
    foreach ($animals as $a) {
        $batchAnimals[] = $a;
    }
    $speciesOptions = FormHelper::lookupOptions($pdo, 'livestock_type');
    ?>
    <form method="post" action="index.php?module=Livestock&action=batchVaccinate" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
        <?php if (!empty($batchErrors['general'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm"><?php echo htmlspecialchars($batchErrors['general']); ?></div>
        <?php endif; ?>
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">Animals <span class="text-slate-400 font-normal">(<?php echo count($batchAnimals); ?> available)</span></label>
            <?php if (!empty($batchErrors['animal_ids'])): ?><p class="text-xs text-red-600 mb-1"><?php echo htmlspecialchars($batchErrors['animal_ids']); ?></p><?php endif; ?>
            <div class="max-h-52 overflow-y-auto border border-slate-200 rounded-lg p-2 bg-white/60 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                <?php foreach ($batchAnimals as $a): ?>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="animal_ids[]" value="<?php echo (int) $a['id']; ?>">
                        <span>#<?php echo (int) $a['id']; ?> — <?php echo htmlspecialchars(ucfirst($a['type'] ?? '') . ' ' . ($a['breed'] ?? '')); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Species (filters vaccines)</label>
                <select name="species" id="batch-species" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Any</option>
                    <?php foreach ($speciesOptions as $val => $lab): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($lab); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Vaccine</label>
                <input list="batch-vaccine-opts" id="batch-vaccine" name="vaccine_name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white" placeholder="e.g. FMD vaccine" required>
                <datalist id="batch-vaccine-opts"></datalist>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Date Vaccinated</label>
                <input type="date" name="vaccination_date" id="batch-date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Next Due (auto)</label>
                <input type="date" name="next_due_date" id="batch-due" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
            </div>
        </div>
        <button type="submit" class="bg-green-700 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">Record batch vaccination</button>
    </form>
</div>

<script>
(function () {
  var intervals = <?php echo json_encode($vaccineIntervals, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  var species = document.getElementById('batch-species');
  var list = document.getElementById('batch-vaccine-opts');
  var vaccine = document.getElementById('batch-vaccine');
  var date = document.getElementById('batch-date');
  var due = document.getElementById('batch-due');
  if (!species || !list || !vaccine) return;
  function reload() {
    list.innerHTML = '';
    Object.keys(intervals).forEach(function (name) {
      var info = intervals[name];
      if (species.value && info.species !== species.value) return;
      var opt = document.createElement('option');
      opt.value = name;
      list.appendChild(opt);
    });
  }
  function recompute() {
    var info = intervals[vaccine.value];
    if (!info || !info.interval) return;
    var base = date.value || new Date().toISOString().slice(0, 10);
    var d = new Date(base + 'T00:00:00');
    d.setDate(d.getDate() + parseInt(info.interval, 10));
    due.value = d.toISOString().slice(0, 10);
  }
  species.addEventListener('change', function () { vaccine.value = ''; due.value = ''; reload(); });
  vaccine.addEventListener('change', recompute);
  date.addEventListener('change', recompute);
  reload();
})();
</script>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-1 text-red-800">Record a Treatment</h2>
    <p class="text-xs text-slate-500 mb-4">Dewormers, antibiotics, dips and other treatments. A withdrawal period blocks sale/slaughter until it ends.</p>
    <?php
    $treatmentErrors = $data['treatmentErrors'] ?? [];
    $treatmentData = $data['treatmentData'] ?? [];
    $treatAnimalOptions = [];
    foreach ($animals as $a) {
        $treatAnimalOptions[(string) $a['id']] = '#' . $a['id'] . ' — ' . ucfirst($a['type'] ?? '') . ' ' . ($a['breed'] ?? 'Unknown');
    }
    $itemOpts = [];
    foreach (FormHelper::lookupOptions($pdo, 'inventory_item') as $k => $v) { $itemOpts[$k] = $v; }
    $treatmentFields = [
        ['name' => 'livestock_id', 'label' => 'Animal', 'type' => 'combo', 'options' => $treatAnimalOptions, 'allow_custom' => false, 'placeholder' => 'Type an animal #, type or breed…', 'value' => $treatmentData['livestock_id'] ?? ''],
        ['name' => 'treatment_name', 'label' => 'Treatment', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'vaccine_name'), 'allow_custom' => true, 'category' => 'treatment_name', 'placeholder' => 'e.g. Dewormer, Oxytetracycline…', 'value' => $treatmentData['treatment_name'] ?? ''],
        ['name' => 'treatment_date', 'label' => 'Date', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $treatmentData['treatment_date'] ?? date('Y-m-d')],
        ['name' => 'dose', 'label' => 'Dose', 'type' => 'text', 'required' => false, 'value' => $treatmentData['dose'] ?? ''],
        ['name' => 'dose_unit', 'label' => 'Dose unit', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'unit'), 'allow_custom' => true, 'category' => 'unit', 'value' => $treatmentData['dose_unit'] ?? ''],
        ['name' => 'withdrawal_days', 'label' => 'Withdrawal days', 'type' => 'number', 'required' => false, 'value' => $treatmentData['withdrawal_days'] ?? '', 'help' => 'Meat/milk withdrawal period before safe sale.'],
        ['name' => 'inventory_item_id', 'label' => 'Draw from stock (optional)', 'type' => 'combo', 'required' => false, 'options' => $itemOpts, 'allow_custom' => false, 'placeholder' => 'Pick an item to reduce stock…', 'value' => $treatmentData['inventory_item_id'] ?? ''],
        ['name' => 'quantity_used', 'label' => 'Quantity used', 'type' => 'number', 'required' => false, 'value' => $treatmentData['quantity_used'] ?? ''],
        ['name' => 'cost', 'label' => 'Cost (K, optional)', 'type' => 'number', 'required' => false, 'value' => $treatmentData['cost'] ?? ''],
    ];
    echo FormHelper::generateForm($treatmentFields, 'index.php?module=Livestock&action=addTreatment', 'POST', $treatmentErrors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Treatment Records</h2>
    <?php if (!empty($treatments)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2.5 pr-3">Animal</th>
                        <th class="py-2.5 pr-3">Treatment</th>
                        <th class="py-2.5 pr-3">Date</th>
                        <th class="py-2.5">Withdrawal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($treatments as $t):
                        $animal = trim(ucfirst($t['animal_type'] ?? '') . ' ' . ($t['animal_breed'] ?? ''));
                        $until = $t['withdrawal_ends'] ?? null;
                        $active = $until && strtotime((string) $until) >= strtotime('today');
                    ?>
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-3 pr-3 font-semibold"><?php echo htmlspecialchars($animal ?: ('Animal #' . ($t['livestock_id'] ?? '—'))); ?></td>
                        <td class="py-3 pr-3"><?php echo htmlspecialchars($t['treatment_name']); ?><?php if (!empty($t['dose'])): ?> <span class="text-slate-400">(<?php echo htmlspecialchars($t['dose'] . ' ' . ($t['dose_unit'] ?? '')); ?>)</span><?php endif; ?></td>
                        <td class="py-3 pr-3"><?php echo htmlspecialchars($t['treatment_date']); ?></td>
                        <td class="py-3">
                            <?php if ($until): ?>
                                <span class="text-xs font-bold px-2.5 py-1 rounded-full <?php echo $active ? 'text-red-700 bg-red-100' : 'text-emerald-700 bg-emerald-100'; ?>">
                                    <?php echo $active ? ('Withdrawal until ' . htmlspecialchars($until)) : 'Clear'; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-xs text-slate-500">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p>No treatment records yet.</p>
    <?php endif; ?>
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
    $issueOptions = [];
    foreach (($data['issueCatalog'] ?? []) as $ic) {
        $issueOptions[] = ['value' => $ic['name'], 'label' => $ic['name'] . (!empty($ic['kind']) ? ' (' . $ic['kind'] . ')' : '')];
    }
    $pestFields = [
        ['name' => 'livestock_id', 'label' => 'Animal', 'type' => 'combo', 'options' => $livestockPestOptions, 'allow_custom' => false, 'placeholder' => 'Type an animal #, type or breed…', 'value' => $pestData['livestock_id'] ?? '', 'help' => 'The animal ID is the "#" number shown in the list.'],
        ['name' => 'pest_name', 'label' => 'Pest / Disease', 'type' => 'combo', 'options' => $issueOptions, 'allow_custom' => true, 'category' => 'disease_name', 'placeholder' => 'e.g. Foot rot, Ticks, or type another…', 'help' => 'Pick a common problem or type your own.', 'value' => $pestData['pest_name'] ?? ''],
        ['name' => 'issue_kind', 'label' => 'Type', 'type' => 'combo', 'required' => false, 'allow_custom' => true, 'options' => [['value' => 'disease', 'label' => 'Disease'], ['value' => 'parasite', 'label' => 'Parasite'], ['value' => 'injury', 'label' => 'Injury'], ['value' => 'deficiency', 'label' => 'Deficiency'], ['value' => 'other', 'label' => 'Other']], 'value' => $pestData['issue_kind'] ?? 'disease'],
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
                $status = $p['status'] ?? 'open';
                $statusCls = $status === 'resolved' ? 'bg-green-100 text-green-800' : ($status === 'treated' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700');
            ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="font-semibold"><?php echo htmlspecialchars($p['pest_name']); ?></span>
                        <span class="text-slate-400">on</span>
                        <span><?php echo htmlspecialchars($animalLabel); ?></span>
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
                                <form method="POST" action="index.php?module=Livestock&action=incidentStatus" class="inline">
                                    <?php echo \SessionHelper::csrfField(); ?>
                                    <input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
                                    <input type="hidden" name="status" value="treated">
                                    <select name="inventory_item_id" class="border border-slate-300 rounded px-2 py-1 text-xs bg-white">
                                        <option value="">No stock used</option>
                                        <?php foreach (FormHelper::options($pdo, "SELECT id, name FROM inventory ORDER BY name") as $iv => $ivl): ?>
                                            <option value="<?php echo (int) $iv; ?>"><?php echo htmlspecialchars($ivl); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="quantity_used" min="0.01" step="0.01" placeholder="Qty" class="border border-slate-300 rounded px-2 py-1 text-xs w-20 bg-white">
                                    <button type="submit" class="text-xs font-semibold px-3 py-1 rounded-lg bg-amber-500 text-white hover:bg-amber-600">Mark treated</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" action="index.php?module=Livestock&action=incidentStatus" class="inline">
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
        <p>No animal health records yet.</p>
    <?php endif; ?>
</div>