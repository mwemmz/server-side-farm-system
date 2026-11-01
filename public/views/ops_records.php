<?php
/**
 * Ops records — list + add/edit form for one registry definition.
 *
 * Expected $data: key, def, records, mode, errors, data, edit_id, options.
 */
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$opsKey     = (string) ($data['key'] ?? '');
$opsDef     = $data['def'] ?? [];
$opsRecords = $data['records'] ?? [];
$opsMode    = (string) ($data['mode'] ?? 'list');
$opsErrors  = $data['errors'] ?? [];
$opsForm    = $data['data'] ?? [];
$opsEditId  = $data['edit_id'] ?? null;
$opsOptions = $data['options'] ?? [];
$opsFields  = $opsDef['fields'] ?? [];
$opsList    = $opsDef['list'] ?? array_keys($opsFields);

function opsLabel($col, $field) {
    if (!empty($field['label'])) return (string) $field['label'];
    return ucfirst(str_replace('_', ' ', preg_replace('/_id$/', '', $col)));
}
function opsValue($row, $col) {
    $v = $row[$col . '_label'] ?? $row[$col] ?? '';
    if ($v === null || $v === '') return '—';
    return (string) $v;
}
function opsInput($row, $col) {
    $v = $row[$col] ?? '';
    return is_scalar($v) ? (string) $v : '';
}

$opsFormFields = [];
foreach ($opsFields as $col => $field) {
    $type = (string) ($field['type'] ?? 'text');
    $f = [
        'name'     => $col,
        'label'    => opsLabel($col, $field),
        'required' => !empty($field['required']),
        'value'    => opsInput($opsForm, $col),
    ];
    switch ($type) {
        case 'fk':
            $f['type'] = 'combo';
            $f['options'] = $opsOptions[$col] ?? [];
            $f['allow_custom'] = false;
            $f['placeholder'] = 'Search ' . strtolower($f['label']) . '…';
            break;
        case 'select':
            $f['type'] = 'select';
            $f['options'] = $field['options'] ?? [];
            $f['placeholder'] = '— select —';
            break;
        case 'number':
            $f['type'] = 'number';
            if (isset($field['step'])) $f['step'] = $field['step'];
            if (isset($field['min']))  $f['min'] = $field['min'];
            if (isset($field['max']))  $f['max'] = $field['max'];
            break;
        case 'textarea':
            $f['type'] = 'textarea';
            break;
        case 'email':
            $f['type'] = 'email';
            break;
        case 'date':
            $f['type'] = 'date';
            break;
        case 'hidden':
            $f['type'] = 'hidden';
            break;
        default:
            $f['type'] = 'text';
    }
    $opsFormFields[] = $f;
}
if ($opsEditId) {
    $opsFormFields[] = ['name' => 'id', 'type' => 'hidden', 'value' => (string) $opsEditId];
}
$opsFormFields[] = ['name' => 'view', 'type' => 'hidden', 'value' => $opsKey];

$opsAction = $opsEditId
    ? 'index.php?module=Ops&view=' . urlencode($opsKey) . '&action=edit'
    : 'index.php?module=Ops&view=' . urlencode($opsKey) . '&action=add';
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 animate-fade-in">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-700"><?php echo htmlspecialchars((string) ($opsDef['group'] ?? 'Operations')); ?></div>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900"><?php echo htmlspecialchars((string) ($opsDef['label'] ?? 'Records')); ?></h1>
            <p class="text-sm text-slate-500 mt-1.5 max-w-3xl"><?php echo htmlspecialchars((string) ($opsDef['summary'] ?? '')); ?></p>
        </div>
        <a href="index.php?module=Ops" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">&larr; All operations</a>
    </div>

    <?php if ($opsMode === 'add' || $opsMode === 'edit'): ?>
        <div class="glass-card p-4 sm:p-6 animate-fade-in">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-slate-800"><?php echo $opsMode === 'edit' ? 'Edit record' : 'Add record'; ?></h2>
                <a href="index.php?module=Ops&amp;view=<?php echo urlencode($opsKey); ?>" class="text-xs font-bold text-slate-500 hover:text-slate-700">Cancel</a>
            </div>
            <?php echo FormHelper::generateForm($opsFormFields, $opsAction, 'POST', $opsErrors); ?>
        </div>
    <?php else: ?>
        <div class="flex flex-wrap items-center gap-2">
            <a href="index.php?module=Ops&amp;view=<?php echo urlencode($opsKey); ?>&amp;action=add"
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:opacity-90 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                Add <?php echo htmlspecialchars((string) ($opsDef['label'] ?? 'record')); ?>
            </a>
            <?php if (!empty($data['undo_available'])): ?>
            <form method="post" action="index.php?module=Ops&amp;view=<?php echo urlencode($opsKey); ?>&amp;action=restore" class="inline"
                  onsubmit="return confirm('Restore the last deleted record?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($opsKey); ?>">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 text-white text-sm font-bold shadow-lg hover:opacity-90 transition">
                    Undo last delete
                </button>
            </form>
            <?php endif; ?>
            <span class="text-xs text-slate-500"><?php echo count($opsRecords); ?> recent record<?php echo count($opsRecords) === 1 ? '' : 's'; ?></span>
        </div>
    <?php endif; ?>

    <div class="glass-card p-0 overflow-hidden animate-fade-in">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50/90 text-slate-500">
                    <tr>
                        <th class="text-left font-bold uppercase tracking-wide text-[10px] px-4 py-3">#</th>
                        <?php foreach ($opsList as $col): ?>
                            <th class="text-left font-bold uppercase tracking-wide text-[10px] px-4 py-3 whitespace-nowrap">
                                <?php echo htmlspecialchars(opsLabel($col, $opsFields[$col] ?? [])); ?>
                            </th>
                        <?php endforeach; ?>
                        <th class="text-right font-bold uppercase tracking-wide text-[10px] px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($opsRecords)): ?>
                        <tr><td colspan="<?php echo count($opsList) + 2; ?>" class="px-4 py-10 text-center text-slate-500">No records yet. Add the first one above.</td></tr>
                    <?php else: ?>
                        <?php foreach ($opsRecords as $row): ?>
                            <tr class="hover:bg-emerald-50/40 transition">
                                <td class="px-4 py-3 text-slate-400 font-mono text-xs"><?php echo (int) ($row['id'] ?? 0); ?></td>
                                <?php foreach ($opsList as $col):
                                    $val = opsValue($row, $col);
                                    if (mb_strlen($val) > 90) $val = mb_substr($val, 0, 87) . '…';
                                ?>
                                    <td class="px-4 py-3 text-slate-700 whitespace-nowrap"><?php echo htmlspecialchars($val); ?></td>
                                <?php endforeach; ?>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="index.php?module=Ops&amp;view=<?php echo urlencode($opsKey); ?>&amp;action=edit&amp;id=<?php echo (int) $row['id']; ?>"
                                           class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800">Edit</a>
                                        <form method="post" action="index.php?module=Ops&amp;view=<?php echo urlencode($opsKey); ?>&amp;action=delete" class="inline"
                                              onsubmit="return confirm('Delete this record? You can undo within 5 minutes.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                                            <input type="hidden" name="view" value="<?php echo htmlspecialchars($opsKey); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                            <button type="submit" class="text-[11px] font-bold text-red-600 hover:text-red-700">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
