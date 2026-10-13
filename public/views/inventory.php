<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
$success = SessionHelper::getFlash('success');
$items = $data['items'] ?? $data;
$movements = $data['movements'] ?? [];
$filters = $data['filters'] ?? [];
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$moveErrors = $data['moveErrors'] ?? [];
$moveData = $data['moveData'] ?? [];
$moveDirection = $data['moveDirection'] ?? 'in';
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Inventory Management</h1>

<?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add Inventory Item</h2>
    <?php
    $fields = [
        ['name' => 'name', 'label' => 'Item Name', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'inventory_item'), 'allow_custom' => true, 'category' => 'inventory_item', 'placeholder' => 'e.g. Fertiliser (Urea), or type a new item…', 'value' => $formData['name'] ?? ''],
        ['name' => 'category', 'label' => 'Category', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'inventory_category'), 'allow_custom' => true, 'category' => 'inventory_category', 'placeholder' => 'Seed, Fertiliser, Pesticide, Fuel…', 'value' => $formData['category'] ?? ''],
        ['name' => 'quantity', 'label' => 'Opening quantity', 'type' => 'number', 'step' => '0.01', 'min' => '0', 'value' => $formData['quantity'] ?? ''],
        ['name' => 'unit', 'label' => 'Unit', 'type' => 'combo', 'options' => FormHelper::lookupOptions($pdo, 'unit'), 'allow_custom' => true, 'category' => 'unit', 'placeholder' => 'kg, L, bag, piece…', 'value' => $formData['unit'] ?? ''],
        ['name' => 'used_for', 'label' => 'Purpose', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'inventory_purpose'), 'allow_custom' => false, 'placeholder' => 'Crops, Livestock, Equipment…', 'value' => $formData['used_for'] ?? ''],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Inventory&action=add', 'POST', $errors);
    ?>
</div>

<?php
$itemOptions = [];
foreach ($items as $it) {
    $itemOptions[(string) $it['id']] = $it['name'] . ' — ' . ($it['category'] ?: 'uncategorised') . ' — ' . rtrim(rtrim(number_format((float) $it['balance'], 2), '0'), '.') . ' ' . ($it['unit'] ?? '');
}
?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-1">Stock Movement</h2>
    <p class="text-xs text-slate-500 mb-4">Receive stock (in) or issue stock (out). Outgoing stock is refused if it would go below zero.</p>
    <?php if (!empty($moveErrors['general'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded text-sm mb-4"><?php echo htmlspecialchars($moveErrors['general']); ?></div>
    <?php endif; ?>
    <?php
    $moveFields = [
        ['name' => 'item_id', 'label' => 'Item', 'type' => 'combo', 'options' => $itemOptions, 'allow_custom' => false, 'placeholder' => 'Type an item name…', 'value' => $moveData['item_id'] ?? ''],
        ['name' => 'direction', 'label' => 'Direction', 'type' => 'select', 'options' => ['in' => 'Stock in (received)', 'out' => 'Stock out (issued/used)'], 'value' => $moveData['direction'] ?? $moveDirection],
        ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'value' => $moveData['quantity'] ?? ''],
        ['name' => 'reason', 'label' => 'Reason', 'type' => 'combo', 'required' => false, 'options' => FormHelper::lookupOptions($pdo, 'stock_move_reason'), 'allow_custom' => true, 'category' => 'stock_move_reason', 'placeholder' => 'Purchase, Used on crop…', 'value' => $moveData['reason'] ?? ''],
        ['name' => 'note', 'label' => 'Note', 'type' => 'text', 'required' => false, 'value' => $moveData['note'] ?? ''],
    ];
    echo FormHelper::generateForm($moveFields, 'index.php?module=Inventory&action=move', 'POST', $moveErrors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Filter</h2>
    <form method="get" action="index.php" class="flex flex-wrap items-end gap-4">
        <input type="hidden" name="module" value="Inventory">
        <input type="hidden" name="action" value="manage">
        <label class="text-sm"><span class="block font-semibold text-slate-700 mb-1">Category</span>
            <select name="category" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">All</option>
                <?php foreach (FormHelper::lookupOptions($pdo, 'inventory_category') as $val => $lab): ?>
                    <option value="<?php echo htmlspecialchars($val); ?>" <?php echo (($filters['category'] ?? '') === $val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="text-sm"><span class="block font-semibold text-slate-700 mb-1">Purpose</span>
            <select name="purpose" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">All</option>
                <?php foreach (FormHelper::lookupOptions($pdo, 'inventory_purpose') as $val => $lab): ?>
                    <option value="<?php echo htmlspecialchars($val); ?>" <?php echo (($filters['purpose'] ?? '') === $val) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lab); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="bg-green-700 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2 rounded-lg">Apply</button>
        <a href="index.php?module=Inventory&action=manage" class="text-sm font-semibold text-slate-600 px-3 py-2">Clear</a>
    </form>
</div>

<?php
$grouped = [];
foreach ($items as $it) { $grouped[$it['category'] ?: 'Uncategorised'][] = $it; }
?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Inventory Items</h2>
    <?php if (!empty($items)): ?>
        <?php foreach ($grouped as $cat => $rows): ?>
            <div class="mb-5">
                <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500 mb-2"><?php echo htmlspecialchars($cat); ?> (<?php echo count($rows); ?>)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-700">
                        <thead>
                            <tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                                <th class="py-2 pr-3">Item</th>
                                <th class="py-2 pr-3">Purpose</th>
                                <th class="py-2 pr-3 text-right">Balance</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2">History</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $it): ?>
                            <tr class="border-b border-slate-100 last:border-0">
                                <td class="py-2.5 pr-3 font-semibold"><?php echo htmlspecialchars($it['name']); ?></td>
                                <td class="py-2.5 pr-3"><?php echo htmlspecialchars($it['used_for'] ?: '—'); ?></td>
                                <td class="py-2.5 pr-3 text-right font-mono"><?php echo rtrim(rtrim(number_format((float) $it['balance'], 2), '0'), '.'); ?> <?php echo htmlspecialchars($it['unit'] ?? ''); ?></td>
                                <td class="py-2.5 pr-3">
                                    <?php if (!empty($it['low'])): ?>
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-800">Low (min <?php echo htmlspecialchars($it['threshold']); ?>)</span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">OK</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5"><a href="index.php?module=Inventory&action=history&id=<?php echo (int) $it['id']; ?>" class="text-xs font-semibold text-green-700 hover:text-green-800">View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-slate-500">Inventory is empty.</p>
    <?php endif; ?>
</div>

<?php if (isset($data['historyItem']) && $data['historyItem']): ?>
<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Movement history — <?php echo htmlspecialchars($data['historyItem']['name']); ?></h2>
    <?php $hist = $data['history'] ?? []; ?>
    <?php if (!empty($hist)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700">
                <thead><tr class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400">
                    <th class="py-2 pr-3">Date</th><th class="py-2 pr-3">Direction</th><th class="py-2 pr-3 text-right">Qty</th><th class="py-2 pr-3 text-right">Balance after</th><th class="py-2 pr-3">Reason</th><th class="py-2">Note</th>
                </tr></thead>
                <tbody>
                <?php foreach ($hist as $h): ?>
                    <tr class="border-b border-slate-100 last:border-0">
                        <td class="py-2 pr-3"><?php echo htmlspecialchars($h['created_at'] ?? ''); ?></td>
                        <td class="py-2 pr-3"><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $h['direction'] === 'in' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>"><?php echo htmlspecialchars($h['direction']); ?></span></td>
                        <td class="py-2 pr-3 text-right font-mono"><?php echo htmlspecialchars($h['quantity']); ?></td>
                        <td class="py-2 pr-3 text-right font-mono"><?php echo $h['balance_after'] !== null ? htmlspecialchars(rtrim(rtrim(number_format((float) $h['balance_after'], 2), '0'), '.')) : '—'; ?></td>
                        <td class="py-2 pr-3"><?php echo htmlspecialchars($h['reason'] ?? '—'); ?></td>
                        <td class="py-2"><?php echo htmlspecialchars($h['note'] ?? '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-slate-500">No movements recorded for this item.</p>
    <?php endif; ?>
    <p class="mt-4"><a href="index.php?module=Inventory&action=manage" class="text-sm font-semibold text-green-700 hover:text-green-800">← Back to Inventory</a></p>
</div>
<?php $items = $data['items'] ?? []; ?>
<?php endif; ?>
