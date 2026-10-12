<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/LookupHelper.php';

$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$result = $data['result'] ?? null;
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-1 text-green-800">Smart Forms — acceptance test</h1>
<p class="text-sm text-slate-500 mb-5">Proves: record picker (closed list), open list with a remembered custom value, and server-side rejection of a bad farm.</p>

<?php if ($result): ?>
    <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded-lg mb-5 text-sm">
        Saved. Breed = <strong><?php echo htmlspecialchars($result['breed']); ?></strong>,
        Farm ID = <strong><?php echo (int) $result['farm_id']; ?></strong>.
        The breed now appears in the list next time.
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 max-w-xl">
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm (closed list)', 'type' => 'combo',
         'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false,
         'placeholder' => 'Type to find a farm…', 'value' => $formData['farm_id'] ?? '',
         'help' => 'Typing only filters — free text is rejected.'],
        ['name' => 'breed', 'label' => 'Breed (open list — pick or type)', 'type' => 'combo',
         'options' => LookupHelper::options($pdo, 'livestock_breed'),
         'allow_custom' => true, 'category' => 'livestock_breed',
         'placeholder' => 'e.g. Brahman, or type a new breed…', 'value' => $formData['breed'] ?? '',
         'help' => 'A value that is not listed is accepted and remembered.'],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Lookup&action=test', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6 mt-6 text-sm text-slate-600 max-w-xl">
    <h2 class="text-base font-semibold mb-2">What to try</h2>
    <ul class="list-disc pl-5 space-y-1">
        <li>Type "bra" in Breed → the list filters to Brahman (type-to-filter).</li>
        <li>Type "Auschwit" (a new breed) → it saves and is offered next time.</li>
        <li>Type "nonsense" in Farm → rejected with "Pick an existing farm".</li>
        <li>Disable JavaScript → a plain dropdown still submits the form.</li>
    </ul>
</div>
