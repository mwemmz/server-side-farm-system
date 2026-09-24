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
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Livestock</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm ID', 'value' => $formData['farm_id'] ?? ''],
        ['name' => 'type', 'label' => 'Type', 'value' => $formData['type'] ?? ''],
        ['name' => 'breed', 'label' => 'Breed', 'value' => $formData['breed'] ?? ''],
        ['name' => 'dob', 'label' => 'Date of Birth', 'type' => 'date', 'value' => $formData['dob'] ?? '']
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
    <form action="index.php?module=Livestock&action=vaccinate" method="POST" class="space-y-6">
        <div class="flex flex-col gap-2">
            <label for="livestock_id" class="text-sm font-semibold text-slate-700">Animal</label>
            <select name="livestock_id" id="livestock_id" class="w-full px-4 py-2.5 text-slate-800 bg-white/70 border rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:bg-white transition duration-150 <?php echo isset($errors['livestock_id']) ? 'border-red-400' : 'border-slate-200'; ?>">
                <option value="">-- Select animal --</option>
                <?php foreach ($vaccAnimals as $a): ?>
                    <option value="<?php echo (int) $a['id']; ?>" <?php echo ((string) ($formData['livestock_id'] ?? '') === (string) $a['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars(ucfirst($a['type']) . ' — ' . ($a['breed'] ?? 'Unknown')); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['livestock_id'])): ?><span class="text-red-600 text-xs font-medium"><?php echo htmlspecialchars($errors['livestock_id']); ?></span><?php endif; ?>
        </div>
        <div class="flex flex-col gap-2">
            <label for="vaccine_name" class="text-sm font-semibold text-slate-700">Vaccine Name</label>
            <input type="text" name="vaccine_name" id="vaccine_name" value="<?php echo htmlspecialchars($formData['vaccine_name'] ?? ''); ?>" autocomplete="off" class="w-full px-4 py-2.5 text-slate-800 bg-white/70 border rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:bg-white transition duration-150 placeholder:text-slate-400 <?php echo isset($errors['vaccine_name']) ? 'border-red-400' : 'border-slate-200'; ?>">
            <?php if (isset($errors['vaccine_name'])): ?><span class="text-red-600 text-xs font-medium"><?php echo htmlspecialchars($errors['vaccine_name']); ?></span><?php endif; ?>
        </div>
        <div class="flex flex-col gap-2">
            <label for="vaccination_date" class="text-sm font-semibold text-slate-700">Date Vaccinated</label>
            <input type="date" name="vaccination_date" id="vaccination_date" value="<?php echo htmlspecialchars($formData['vaccination_date'] ?? ''); ?>" autocomplete="off" class="w-full px-4 py-2.5 text-slate-800 bg-white/70 border rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:bg-white transition duration-150 placeholder:text-slate-400 <?php echo isset($errors['vaccination_date']) ? 'border-red-400' : 'border-slate-200'; ?>">
            <?php if (isset($errors['vaccination_date'])): ?><span class="text-red-600 text-xs font-medium"><?php echo htmlspecialchars($errors['vaccination_date']); ?></span><?php endif; ?>
        </div>
        <div class="flex flex-col gap-2">
            <label for="next_due_date" class="text-sm font-semibold text-slate-700">Next Due Date</label>
            <input type="date" name="next_due_date" id="next_due_date" value="<?php echo htmlspecialchars($formData['next_due_date'] ?? ''); ?>" autocomplete="off" class="w-full px-4 py-2.5 text-slate-800 bg-white/70 border rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:bg-white transition duration-150 placeholder:text-slate-400 border-slate-200">
        </div>
        <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 text-white font-bold py-2.5 px-4 rounded-lg shadow-lg shadow-green-900/30 transition duration-150">Submit</button>
    </form>
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