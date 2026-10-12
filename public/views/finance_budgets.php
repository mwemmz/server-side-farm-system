<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../config/env.php';

$success = SessionHelper::getFlash('success');
$budgets = $data['budgets'] ?? [];
$total   = $data['total'] ?? 0;
$errors  = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Budgets</h1>

<?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="glass-card p-5 mb-6"><div class="text-xs uppercase font-bold text-slate-400">Total planned</div><div class="text-2xl font-bold text-slate-800 mt-1"><?php echo money($total); ?></div></div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Budget Line</h2>
    <?php
    $fields = [
        ['name' => 'category', 'label' => 'Category', 'type' => 'text', 'value' => $formData['category'] ?? ''],
        ['name' => 'period', 'label' => 'Period', 'type' => 'text', 'value' => $formData['period'] ?? '', 'help' => 'e.g. 2026 Q1, October 2026', 'required' => false],
        ['name' => 'planned_amount', 'label' => 'Planned Amount (K)', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'value' => $formData['planned_amount'] ?? ''],
        ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'value' => $formData['notes'] ?? '', 'required' => false],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Finance&action=budgets', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Budget Lines</h2>
    <?php if (!empty($budgets)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($budgets as $b): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex items-center justify-between gap-3">
                    <span><strong><?php echo htmlspecialchars($b['category']); ?></strong> <span class="text-slate-400">· <?php echo htmlspecialchars($b['period'] ?: '—'); ?></span></span>
                    <span class="font-semibold"><?php echo money($b['planned_amount']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500">No budget lines yet.</p>
    <?php endif; ?>
</div>

<p class="mt-4"><a href="index.php?module=Finance&action=manage" class="text-sm font-semibold text-green-700 hover:text-green-800">← Back to Finance</a></p>
