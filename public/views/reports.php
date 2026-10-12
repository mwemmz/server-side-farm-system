<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Reports</h1>

<?php
$exportSets = [
    'crops' => 'Crops', 'fields' => 'Fields', 'livestock' => 'Livestock', 'harvests' => 'Harvests',
    'inventory' => 'Inventory', 'finance' => 'Finance Records', 'sales' => 'Sales',
    'procurement' => 'Procurement', 'pest' => 'Pest Records', 'suppliers' => 'Suppliers',
    'employees' => 'Employees', 'leave' => 'Leave Requests', 'loans' => 'Loans', 'budgets' => 'Budgets',
];
?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-1">Export Data</h2>
    <p class="text-sm text-slate-500 mb-4">Download a dataset as CSV (Excel-friendly) or open a printable view to save as PDF.</p>
    <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-3 gap-2.5">
        <?php foreach ($exportSets as $key => $label): ?>
            <div class="bg-white/50 border border-white/60 rounded-xl px-3.5 py-2.5 flex items-center justify-between gap-3">
                <span class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($label); ?></span>
                <span class="flex items-center gap-2 whitespace-nowrap">
                    <a href="index.php?module=Reports&action=export&dataset=<?php echo urlencode($key); ?>" class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800" title="Download CSV">CSV</a>
                    <a href="index.php?module=Reports&action=printable&dataset=<?php echo urlencode($key); ?>" target="_blank" rel="noopener" class="text-[11px] font-bold text-slate-600 hover:text-slate-800" title="Printable / PDF">Print</a>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo $successMessage; ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Generate New Report</h2>
    <?php
    $fields = [
        ['name' => 'report_type', 'label' => 'Report Type', 'value' => $formData['report_type'] ?? ''],
        ['name' => 'data', 'label' => 'Data', 'type' => 'textarea', 'value' => $formData['data'] ?? '']
    ];
    echo FormHelper::generateForm($fields, '/index.php?module=Reports&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Generated Reports</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($records as $record): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm"><?php echo htmlspecialchars($record['report_type']); ?> generated at <?php echo htmlspecialchars($record['generated_at']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No reports generated.</p>
    <?php endif; ?>
</div>