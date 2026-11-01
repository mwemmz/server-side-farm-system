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

$mode = $data['mode'] ?? '';
$esc = function ($v) { return htmlspecialchars((string) $v); };
$animalLabel = function ($r) {
    $tag = trim((string) ($r['animal_tag'] ?? $r['tag_no'] ?? ''));
    $parts = [$tag !== '' ? $tag : (ucfirst((string) ($r['animal_type'] ?? $r['type'] ?? 'Animal')) . ' #' . (int) ($r['livestock_id'] ?? $r['id'] ?? 0))];
    $breed = $r['animal_breed'] ?? $r['breed'] ?? '';
    $nm = $r['animal_name'] ?? $r['name'] ?? '';
    if ($breed !== '') $parts[] = $breed;
    if ($nm !== '') $parts[] = $nm;
    return implode(' · ', $parts);
};
$statusCls = ['active' => 'bg-emerald-100 text-emerald-800', 'sold' => 'bg-sky-100 text-sky-800', 'dead' => 'bg-slate-300 text-slate-700', 'culled' => 'bg-amber-100 text-amber-800'];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Livestock Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>

<?php if ($mode === 'edit' || $mode === 'profile'): ?>
    <?php if (!empty($data['notFound'])): ?>
        <div class="glass-card p-6 mb-6"><p class="text-sm text-red-700">That animal was not found.</p>
        <a href="index.php?module=Livestock&amp;action=manage" class="inline-block mt-3 text-sm font-semibold text-green-700 underline">Back to the animal register</a></div>
    <?php return; endif; ?>
    <?php $an = $data['animal']; ?>
    <?php if ($mode === 'edit'): ?>
    <div class="glass-card p-4 sm:p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Edit <?php echo $esc($animalLabel($an)); ?></h2>
        <?php
        $ed = $data['data'] ?? $an;
        $editFields = [
            ['name' => 'tag_no', 'label' => 'Tag number', 'type' => 'text', 'value' => $ed['tag_no'] ?? '', 'placeholder' => 'e.g. COW-004', 'help' => 'Must be unique on the farm. Letters, digits, dashes and spaces.'],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => false, 'value' => $ed['name'] ?? '', 'placeholder' => 'Optional, e.g. Bella'],
            ['name' => 'farm_id', 'label' => 'Farm', 'type' => 'combo', 'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false, 'value' => $ed['farm_id'] ?? ''],
            ['name' => 'type', 'label' => 'Type', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'livestock_type'), 'allow_custom' => true, 'category' => 'livestock_type', 'value' => $ed['type'] ?? ''],
            ['name' => 'breed', 'label' => 'Breed', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'livestock_breed'), 'allow_custom' => true, 'category' => 'livestock_breed', 'depends_on' => 'type', 'value' => $ed['breed'] ?? ''],
            ['name' => 'sex', 'label' => 'Sex', 'type' => 'select', 'required' => false, 'options' => ['' => '—', 'male' => 'Male', 'female' => 'Female'], 'value' => $ed['sex'] ?? ''],
            ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $ed['dob'] ?? ''],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['active' => 'Active', 'sold' => 'Sold', 'dead' => 'Dead', 'culled' => 'Culled'], 'value' => $ed['status'] ?? 'active', 'help' => 'Sold, dead or culled animals leave every picker but keep their history.'],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'value' => $ed['notes'] ?? ''],
        ];
        echo FormHelper::generateForm($editFields, 'index.php?module=Livestock&action=edit&id=' . (int) $an['id'], 'POST', $data['errors'] ?? []);
        ?>
        <a href="index.php?module=Livestock&amp;action=profile&amp;id=<?php echo (int) $an['id']; ?>" class="inline-block mt-3 text-sm text-slate-500 underline">Cancel</a>
    </div>
    <?php else: ?>
    <?php $st = $an['status'] ?? 'active'; ?>
    <div class="glass-card p-4 sm:p-6 mb-6">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-2xl font-extrabold text-slate-900"><?php echo $esc($an['tag_no'] ?? ('#' . $an['id'])); ?></h2>
            <?php if (!empty($an['name'])): ?><span class="text-lg text-slate-600"><?php echo $esc($an['name']); ?></span><?php endif; ?>
            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $statusCls[$st] ?? 'bg-slate-200 text-slate-700'; ?>"><?php echo $esc($st); ?></span>
            <?php if (isset($withdrawals[(int) $an['id']])): ?><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-800">Withdrawal until <?php echo $esc($withdrawals[(int) $an['id']]); ?></span><?php endif; ?>
            <a href="index.php?module=Livestock&amp;action=edit&amp;id=<?php echo (int) $an['id']; ?>" class="ml-auto text-sm font-semibold text-green-700 underline">Edit</a>
        </div>
        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4 text-sm">
            <div><dt class="text-xs text-slate-400">Type</dt><dd class="font-semibold"><?php echo $esc(ucfirst($an['type'] ?? '')); ?></dd></div>
            <div><dt class="text-xs text-slate-400">Breed</dt><dd class="font-semibold"><?php echo $esc($an['breed'] ?? '—'); ?></dd></div>
            <div><dt class="text-xs text-slate-400">Sex</dt><dd class="font-semibold"><?php echo $esc(ucfirst($an['sex'] ?? '—')); ?></dd></div>
            <div><dt class="text-xs text-slate-400">Age</dt><dd class="font-semibold"><?php echo $esc(LivestockModel::age($an['dob'] ?? null)); ?></dd></div>
            <div><dt class="text-xs text-slate-400">Born</dt><dd class="font-semibold"><?php echo $esc($an['dob'] ?? '—'); ?></dd></div>
            <div><dt class="text-xs text-slate-400">Farm</dt><dd class="font-semibold"><?php echo $esc($an['farm_name'] ?? '—'); ?></dd></div>
        </dl>
        <?php if (!empty($an['notes'])): ?><p class="mt-3 text-sm text-slate-600"><?php echo nl2br($esc($an['notes'])); ?></p><?php endif; ?>
        <div class="flex flex-wrap gap-2 mt-4">
            <a href="index.php?module=Livestock&amp;action=manage&amp;animal=<?php echo (int) $an['id']; ?>#vaccinate" class="text-sm font-bold px-3 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">Record vaccination</a>
            <a href="index.php?module=Livestock&amp;action=manage&amp;animal=<?php echo (int) $an['id']; ?>#treatment" class="text-sm font-bold px-3 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700">Record treatment</a>
            <a href="index.php?module=Livestock&amp;action=manage&amp;animal=<?php echo (int) $an['id']; ?>#health" class="text-sm font-bold px-3 py-2 rounded-lg bg-amber-500 text-white hover:bg-amber-600">Report a problem</a>
            <a href="index.php?module=Livestock&amp;action=manage" class="text-sm font-semibold px-3 py-2 text-slate-500 underline">Back to register</a>
        </div>
    </div>
    <?php foreach ([
        'Vaccinations' => [$data['pVacc'] ?? [], function ($r) { return [$r['vaccine_name'], $r['vaccination_date'], 'Next due: ' . ($r['next_due_date'] ?: '—')]; }],
        'Treatments' => [$data['pTreat'] ?? [], function ($r) { return [$r['treatment_name'], $r['treatment_date'], !empty($r['withdrawal_ends']) ? 'Withdrawal ends ' . $r['withdrawal_ends'] : '—']; }],
        'Health incidents' => [$data['pIncidents'] ?? [], function ($r) { return [$r['pest_name'], $r['detected_date'], ucfirst((string) ($r['status'] ?? 'open'))]; }],
    ] as $title => $sec): ?>
    <div class="glass-card p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-semibold mb-3"><?php echo $esc($title); ?></h3>
        <?php if (empty($sec[0])): ?><p class="text-sm text-slate-400">None recorded.</p><?php else: ?>
        <ul class="space-y-2">
            <?php foreach ($sec[0] as $r): $c = $sec[1]($r); ?>
            <li class="bg-white/50 border border-white/60 rounded-xl px-4 py-2.5 text-sm flex flex-wrap gap-x-3"><span class="font-semibold"><?php echo $esc($c[0]); ?></span><span class="text-slate-500"><?php echo $esc($c[1]); ?></span><span class="text-slate-500"><?php echo $esc($c[2]); ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php return; ?>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Livestock</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm', 'type' => 'combo', 'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a farm…', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'type', 'label' => 'Type', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'livestock_type'), 'allow_custom' => true, 'category' => 'livestock_type', 'placeholder' => 'e.g. Cattle, Goats, Poultry…', 'value' => $formData['type'] ?? ''],
        ['name' => 'breed', 'label' => 'Breed', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'livestock_breed'), 'allow_custom' => true, 'category' => 'livestock_breed', 'depends_on' => 'type', 'placeholder' => 'Pick a type first, then choose or type a breed…', 'value' => $formData['breed'] ?? '', 'help' => 'Common breeds are suggested after you choose a type. Type a new one if it is not listed.'],
        ['name' => 'tag_no', 'label' => 'Tag number', 'type' => 'text', 'required' => false, 'value' => $formData['tag_no'] ?? '', 'placeholder' => 'Leave empty to auto-generate (e.g. COW-004)', 'help' => 'Leave empty to auto-generate, or type the number from the animal\'s ear tag.'],
        ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => false, 'value' => $formData['name'] ?? '', 'placeholder' => 'Optional, e.g. Bella'],
        ['name' => 'sex', 'label' => 'Sex', 'type' => 'select', 'required' => false, 'options' => ['' => '—', 'male' => 'Male', 'female' => 'Female'], 'value' => $formData['sex'] ?? ''],
        ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $formData['dob'] ?? '']
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Livestock&action=add', 'POST', $errors);
    ?>
    <script>
    (function () {
      var tagInput = document.getElementById('tag_no'); if (!tagInput) return;
      var hint = document.createElement('span'); hint.id = 'next-tag-hint'; hint.className = 'text-xs font-semibold text-green-700'; tagInput.parentNode.appendChild(hint);
      var timer = null;
      function val(n) { var e = document.querySelector('form[action*="module=Livestock&action=add"] [name="' + n + '"]'); return e ? e.value : ''; }
      function refresh() {
        var t = val('type'); if (!t) { hint.textContent = ''; return; }
        fetch('index.php?module=Livestock&action=nextTag&farm_id=' + encodeURIComponent(val('farm_id')) + '&type=' + encodeURIComponent(t), {headers: {'Accept': 'application/json'}})
          .then(function (r) { return r.json(); }).then(function (j) { if (j && j.tag) hint.textContent = 'Next automatic tag: ' + j.tag + '.'; }).catch(function () {});
      }
      document.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(refresh, 300); });
      document.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(refresh, 300); });
    })();
    </script>
</div>

<?php
$fSpecies = is_array($_GET['species'] ?? '') ? '' : strtolower(trim((string) ($_GET['species'] ?? '')));
$fFarm    = is_array($_GET['farm'] ?? '') ? '' : (string) ($_GET['farm'] ?? '');
$fStatus  = is_array($_GET['status'] ?? '') ? '' : strtolower(trim((string) ($_GET['status'] ?? '')));
$fQ       = is_array($_GET['q'] ?? '') ? '' : strtolower(trim((string) ($_GET['q'] ?? '')));
$farmNames = [];
foreach ($pdo->query("SELECT id, name FROM farms")->fetchAll() as $__f) { $farmNames[(int) $__f['id']] = $__f['name']; }
$nextDue = [];
foreach ($vaccinations as $__v) {
    if (!empty($__v['next_due_date'])) {
        $__id = (int) $__v['livestock_id'];
        if (!isset($nextDue[$__id]) || $__v['next_due_date'] < $nextDue[$__id]) $nextDue[$__id] = $__v['next_due_date'];
    }
}
$register = array_values(array_filter($animals, function ($a) use ($fSpecies, $fFarm, $fStatus, $fQ) {
    if ($fSpecies !== '' && strtolower((string) $a['type']) !== $fSpecies) return false;
    if ($fFarm !== '' && (string) $a['farm_id'] !== $fFarm) return false;
    if ($fStatus !== '' && strtolower((string) ($a['status'] ?? 'active')) !== $fStatus) return false;
    if ($fQ !== '' && strpos(strtolower(($a['tag_no'] ?? '') . ' ' . ($a['name'] ?? '') . ' ' . ($a['breed'] ?? '') . ' ' . $a['id']), $fQ) === false) return false;
    return true;
}));
?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Animal Register <span class="text-sm font-normal text-slate-400">(<?php echo count($register); ?> of <?php echo count($animals); ?>)</span></h2>
    <form method="get" action="index.php" class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-4 text-sm">
        <input type="hidden" name="module" value="Livestock"><input type="hidden" name="action" value="manage">
        <input type="text" name="q" value="<?php echo $esc($_GET['q'] ?? ''); ?>" placeholder="Search tag, name, breed…" class="border border-slate-300 rounded-lg px-3 py-2 bg-white col-span-2 sm:col-span-1">
        <select name="species" class="border border-slate-300 rounded-lg px-3 py-2 bg-white"><option value="">All species</option>
            <?php foreach (array_unique(array_map(function ($a) { return $a['type']; }, $animals)) as $__t): ?><option value="<?php echo $esc(strtolower($__t)); ?>"<?php echo strtolower($__t) === $fSpecies ? ' selected' : ''; ?>><?php echo $esc(ucfirst($__t)); ?></option><?php endforeach; ?></select>
        <select name="farm" class="border border-slate-300 rounded-lg px-3 py-2 bg-white"><option value="">All farms</option>
            <?php foreach ($farmNames as $__id => $__n): ?><option value="<?php echo $__id; ?>"<?php echo (string) $__id === $fFarm ? ' selected' : ''; ?>><?php echo $esc($__n); ?></option><?php endforeach; ?></select>
        <select name="status" class="border border-slate-300 rounded-lg px-3 py-2 bg-white"><option value="">Any status</option>
            <?php foreach (['active', 'sold', 'dead', 'culled'] as $__s): ?><option value="<?php echo $__s; ?>"<?php echo $__s === $fStatus ? ' selected' : ''; ?>><?php echo ucfirst($__s); ?></option><?php endforeach; ?></select>
        <button type="submit" class="bg-green-700 hover:bg-green-800 text-white font-semibold rounded-lg px-3 py-2">Filter</button>
    </form>
    <?php if (!empty($register)): ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead><tr class="text-xs uppercase text-slate-400 border-b border-slate-200">
                <th class="py-2 pr-3">Tag</th><th class="py-2 pr-3">Name</th><th class="py-2 pr-3">Type</th><th class="py-2 pr-3">Breed</th><th class="py-2 pr-3">Sex</th><th class="py-2 pr-3">Age</th><th class="py-2 pr-3">Farm</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Next vaccination</th><th class="py-2"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($register as $item): $wUntil = $withdrawals[(int) $item['id']] ?? null; $st = $item['status'] ?? 'active'; $nd = $nextDue[(int) $item['id']] ?? null; ?>
                <tr class="border-b border-slate-100 last:border-0">
                    <td class="py-2.5 pr-3 font-bold"><a class="text-green-800 hover:underline" href="index.php?module=Livestock&amp;action=profile&amp;id=<?php echo (int) $item['id']; ?>"><?php echo $esc($item['tag_no'] ?? ('#' . $item['id'])); ?></a></td>
                    <td class="py-2.5 pr-3"><?php echo $esc($item['name'] ?? '—'); ?></td>
                    <td class="py-2.5 pr-3"><?php echo $esc(ucfirst($item['type'] ?? '')); ?></td>
                    <td class="py-2.5 pr-3"><?php echo $esc($item['breed'] ?? ''); ?></td>
                    <td class="py-2.5 pr-3"><?php echo $esc(ucfirst($item['sex'] ?? '—')); ?></td>
                    <td class="py-2.5 pr-3"><?php echo $esc(LivestockModel::age($item['dob'] ?? null)); ?></td>
                    <td class="py-2.5 pr-3"><?php echo $esc($farmNames[(int) $item['farm_id']] ?? '—'); ?></td>
                    <td class="py-2.5 pr-3"><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $statusCls[$st] ?? 'bg-slate-200 text-slate-700'; ?>"><?php echo $esc($st); ?></span>
                        <?php if ($wUntil): ?><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-800">Withdrawal until <?php echo $esc($wUntil); ?></span><?php endif; ?></td>
                    <td class="py-2.5 pr-3"><?php echo $nd ? '<span class="' . ($nd < date('Y-m-d') ? 'text-red-700 font-semibold' : '') . '">' . $esc($nd) . '</span>' : '—'; ?></td>
                    <td class="py-2.5 whitespace-nowrap"><a class="text-xs font-semibold text-green-700 underline" href="index.php?module=Livestock&amp;action=profile&amp;id=<?php echo (int) $item['id']; ?>">History</a> · <a class="text-xs font-semibold text-green-700 underline" href="index.php?module=Livestock&amp;action=edit&amp;id=<?php echo (int) $item['id']; ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-sm text-slate-500">No animals match.</p>
    <?php endif; ?>
</div>

<div class="glass-card p-4 sm:p-6 mb-6" id="vaccinate">
    <h2 class="text-xl font-semibold mb-4">Record a Vaccination</h2>
    <?php
    $vaccAnimalsOptions = [];
    foreach ($vaccAnimals as $a) {
        $vaccAnimalsOptions[(string) $a['id']] = LivestockModel::label($a);
    }
    $vaccFields = [
        ['name' => 'livestock_id', 'label' => 'Animal', 'type' => 'combo', 'options' => $vaccAnimalsOptions, 'allow_custom' => false, 'placeholder' => 'Type a tag, breed or name…', 'value' => $formData['livestock_id'] ?? ($_GET['animal'] ?? ''), 'help' => 'Pick the animal by its tag number.'],
        ['name' => 'species', 'label' => 'Species', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'livestock_type'), 'allow_custom' => false, 'placeholder' => 'e.g. Cattle, Poultry…', 'value' => $formData['species'] ?? '', 'help' => 'Choose the species to filter the vaccine list.'],
        ['name' => 'vaccine_name', 'label' => 'Vaccine', 'type' => 'combo', 'options' => LookupHelper::vaccineOptions($pdo), 'allow_custom' => true, 'category' => 'vaccine_name', 'depends_on' => 'species', 'placeholder' => 'Pick a species first, then a vaccine…', 'value' => $formData['vaccine_name'] ?? '', 'help' => 'Starter schedule values — confirm intervals with a veterinarian.'],
        ['name' => 'vaccination_date', 'label' => 'Date Vaccinated', 'type' => 'date', 'max' => date('Y-m-d'), 'value' => $formData['vaccination_date'] ?? date('Y-m-d')],
        ['name' => 'next_due_date', 'label' => 'Next Due Date', 'type' => 'date', 'required' => false, 'value' => $formData['next_due_date'] ?? '', 'help' => 'Auto-filled from the vaccine schedule. Single-dose vaccines (e.g. Marek\'s at day-old) never carry a next due date.'],
        ['name' => 'dose_number', 'label' => 'Dose number', 'type' => 'number', 'required' => false, 'value' => $formData['dose_number'] ?? ''],
        ['name' => 'batch_number', 'label' => 'Batch / lot no.', 'type' => 'text', 'required' => false, 'value' => $formData['batch_number'] ?? ''],
        ['name' => 'cost', 'label' => 'Cost (K)', 'type' => 'number', 'required' => false, 'value' => $formData['cost'] ?? ''],
    ];
    echo FormHelper::generateForm($vaccFields, 'index.php?module=Livestock&action=vaccinate', 'POST', $errors);
    ?>
</div>

<script>
(function () {
  var cbs = document.querySelectorAll('.batch-cb'), cnt = document.getElementById('batch-count');
  function recount() { var n = 0; cbs.forEach(function (c) { if (c.checked) n++; }); if (cnt) cnt.textContent = n; }
  cbs.forEach(function (c) { c.addEventListener('change', recount); });
  document.querySelectorAll('.batch-all').forEach(function (b) {
    b.addEventListener('click', function () {
      var g = b.getAttribute('data-grp'), boxes = Array.prototype.filter.call(cbs, function (c) { return c.getAttribute('data-grp') === g; });
      var all = boxes.every(function (c) { return c.checked; });
      boxes.forEach(function (c) { c.checked = !all; }); b.textContent = all ? 'Select all' : 'Clear'; recount();
    });
  });
})();
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
    $batchAnimals = $vaccAnimals;
    $batchGroups = [];
    foreach ($batchAnimals as $a) { $batchGroups[ucfirst((string) ($a['type'] ?? 'Other'))][] = $a; }
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
            <div class="max-h-64 overflow-y-auto border border-slate-200 rounded-lg p-2 bg-white/60 space-y-2" id="batch-box">
                <?php foreach ($batchGroups as $grp => $list): ?>
                <div>
                    <div class="flex items-center gap-2 text-xs font-bold uppercase text-slate-500 mb-1"><?php echo $esc($grp); ?> (<?php echo count($list); ?>)
                        <button type="button" class="batch-all normal-case font-semibold text-green-700 underline" data-grp="<?php echo $esc($grp); ?>">Select all</button></div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                    <?php foreach ($list as $a): ?>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" class="batch-cb" data-grp="<?php echo $esc($grp); ?>" name="animal_ids[]" value="<?php echo (int) $a['id']; ?>">
                            <span><?php echo $esc(LivestockModel::label($a)); ?></span>
                        </label>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-slate-500 mt-1"><span id="batch-count">0</span> selected</p>
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

<div class="glass-card p-4 sm:p-6 mb-6" id="treatment">
    <h2 class="text-xl font-semibold mb-1 text-red-800">Record a Treatment</h2>
    <p class="text-xs text-slate-500 mb-4">Dewormers, antibiotics, dips and other treatments. A withdrawal period blocks sale/slaughter until it ends.</p>
    <?php
    $treatmentErrors = $data['treatmentErrors'] ?? [];
    $treatmentData = $data['treatmentData'] ?? [];
    $treatAnimalOptions = [];
    foreach ($vaccAnimals as $a) {
        $treatAnimalOptions[(string) $a['id']] = LivestockModel::label($a);
    }
    $itemOpts = [];
    foreach (FormHelper::lookupOptions($pdo, 'inventory_item') as $k => $v) { $itemOpts[$k] = $v; }
    $treatmentFields = [
        ['name' => 'livestock_id', 'label' => 'Animal', 'type' => 'combo', 'options' => $treatAnimalOptions, 'allow_custom' => false, 'placeholder' => 'Type a tag, breed or name…', 'value' => $treatmentData['livestock_id'] ?? ($_GET['animal'] ?? '')],
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
                        $animal = $animalLabel($t);
                        $until = $t['withdrawal_ends'] ?? null;
                        $active = $until && strtotime((string) $until) >= strtotime('today');
                    ?>
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-3 pr-3 font-semibold"><?php echo htmlspecialchars($animal); ?></td>
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
                        $animal = $animalLabel($v);
                        $next = $v['next_due_date'] ?? null;
                        if ($next) {
                            $ts = strtotime((string) $next);
                            if ($ts < strtotime('today')) { $status = 'Overdue'; $cls = 'text-red-700 bg-red-100'; }
                            elseif ($ts < strtotime('+30 days')) { $status = 'Due soon'; $cls = 'text-amber-700 bg-amber-100'; }
                            else { $status = 'Scheduled'; $cls = 'text-emerald-700 bg-emerald-100'; }
                        } else { $status = 'No next date'; $cls = 'text-slate-600 bg-slate-100'; }
                    ?>
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-3 pr-3 font-semibold"><?php echo htmlspecialchars($animal); ?></td>
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

<div id="health" class="glass-card p-4 sm:p-6 mt-6">
    <h2 class="text-xl font-semibold mb-1 text-red-800">Pests &amp; Health Problems</h2>
    <p class="text-xs text-slate-500 mb-4">Report a pest, parasite or disease on an animal. The record is stored against the animal (and its farm).</p>
    <?php
    $pestErrors = $data['pestErrors'] ?? [];
    $pestData = $data['pestData'] ?? [];
    $livestockPestOptions = [];
    foreach ($vaccAnimals as $a) {
        $livestockPestOptions[(string) $a['id']] = LivestockModel::label($a);
    }
    $issueOptions = [];
    foreach (($data['issueCatalog'] ?? []) as $ic) {
        $issueOptions[$ic['name']] = $ic['name'] . (!empty($ic['kind']) ? ' (' . $ic['kind'] . ')' : '');
    }
    $pestFields = [
        ['name' => 'livestock_id', 'label' => 'Animal', 'type' => 'combo', 'options' => $livestockPestOptions, 'allow_custom' => false, 'placeholder' => 'Type a tag, breed or name…', 'value' => $pestData['livestock_id'] ?? ($_GET['animal'] ?? ''), 'help' => 'Pick the animal by its tag number.'],
        ['name' => 'pest_name', 'label' => 'Pest / Disease', 'type' => 'combo', 'options' => $issueOptions, 'allow_custom' => true, 'category' => 'disease_name', 'placeholder' => 'e.g. Foot rot, Ticks, or type another…', 'help' => 'Pick a common problem or type your own.', 'value' => $pestData['pest_name'] ?? ''],
        ['name' => 'issue_kind', 'label' => 'Type', 'type' => 'combo', 'required' => false, 'allow_custom' => true, 'options' => ['disease' => 'Disease', 'parasite' => 'Parasite', 'injury' => 'Injury', 'deficiency' => 'Deficiency', 'other' => 'Other'], 'value' => $pestData['issue_kind'] ?? 'disease'],
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
                $animalLbl = $animalLabel($p);
                $status = $p['status'] ?? 'open';
                $statusCls = $status === 'resolved' ? 'bg-green-100 text-green-800' : ($status === 'treated' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700');
            ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="font-semibold"><?php echo htmlspecialchars($p['pest_name']); ?></span>
                        <span class="text-slate-400">on</span>
                        <span><?php echo htmlspecialchars($animalLbl); ?></span>
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