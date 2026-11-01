<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$rules  = $data['rules'] ?? [];
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$flash = SessionHelper::getFlash('success');
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-1 text-green-800">Notification Alert Rules</h1>
<p class="text-sm text-slate-500 mb-5">Raise a notification automatically when a live farm metric crosses a threshold.</p>

<?php if ($flash): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Alert Rule</h2>
    <?php
    $fields = [
        ['name' => 'metric', 'label' => 'Metric', 'type' => 'select', 'options' => [
            'low_stock_items' => 'Low stock items (qty ≤ 10)',
            'pending_leave' => 'Pending leave requests',
            'open_grievances' => 'Open grievances',
            'overdue_harvests' => 'Overdue harvests',
            'purchase_orders_draft' => 'Draft purchase orders',
        ], 'placeholder' => 'Choose a metric…', 'value' => $formData['metric'] ?? ''],
        ['name' => 'operator', 'label' => 'Operator', 'type' => 'select', 'options' => ['>=' => '≥ at least', '>' => '> greater than', '<=' => '≤ at most', '<' => '< less than', '==' => '= equal to'], 'value' => $formData['operator'] ?? '>='],
        ['name' => 'threshold', 'label' => 'Threshold', 'type' => 'number', 'step' => '1', 'value' => $formData['threshold'] ?? ''],
        ['name' => 'message', 'label' => 'Alert Message', 'type' => 'text', 'value' => $formData['message'] ?? '', 'help' => 'e.g. "Low stock — restock soon"'],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Notifications&action=rules', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold">Active Rules (<?php echo count($rules); ?>)</h2>
        <form method="POST" action="index.php?module=Notifications&action=run_rules" class="inline">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
            <button class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-shadow">Run rules now</button>
        </form>
    </div>
    <?php if (!empty($rules)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($rules as $r): $active = in_array($r['is_active'], [true, 1, '1', 't', 'true'], true); ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex items-center justify-between gap-3">
                    <span>
                        <strong><?php echo htmlspecialchars($r['metric']); ?></strong>
                        <?php echo htmlspecialchars($r['operator']); ?> <?php echo htmlspecialchars(rtrim(rtrim(number_format((float) $r['threshold'], 2), '0'), '.')); ?>
                        <span class="text-slate-400">· <?php echo htmlspecialchars($r['message']); ?></span>
                        <span class="ml-2 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'; ?>"><?php echo $active ? 'active' : 'paused'; ?></span>
                    </span>
                    <span class="flex items-center gap-3 whitespace-nowrap">
                        <form method="POST" action="index.php?module=Notifications&action=toggle_rule&id=<?php echo (int) $r['id']; ?>" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                            <button class="text-[11px] font-bold text-slate-600 hover:text-slate-800"><?php echo $active ? 'Pause' : 'Resume'; ?></button>
                        </form>
                        <form method="POST" action="index.php?module=Notifications&action=delete_rule&id=<?php echo (int) $r['id']; ?>" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                            <button class="text-[11px] font-bold text-red-600 hover:text-red-700">Delete</button>
                        </form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500">No alert rules yet — add one above.</p>
    <?php endif; ?>
</div>

<p class="mt-4"><a href="index.php?module=Notifications&action=manage" class="text-sm font-semibold text-green-700 hover:text-green-800">← Back to Notifications</a></p>
