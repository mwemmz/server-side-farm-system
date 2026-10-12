<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);
$isRead = function ($v) { return in_array($v, [true, 1, '1', 't', 'true'], true); };
$unread = 0;
foreach ($records as $r) { if (!$isRead($r['is_read'] ?? false)) $unread++; }
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Notifications</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($successMessage); ?></div>
<?php endif; ?>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <span class="text-sm font-semibold text-slate-600"><?php echo $unread; ?> unread</span>
    <a href="index.php?module=Notifications&action=mark_all_read&back=<?php echo urlencode('index.php?module=Notifications&action=manage'); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold shadow-sm hover:bg-slate-50 transition-colors">Mark all read</a>
    <a href="index.php?module=Notifications&action=rules" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-shadow">Alert Rules</a>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Notification</h2>
    <?php
    $fields = [
        ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'value' => $formData['message'] ?? '']
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Notifications&action=add', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">All Notifications</h2>
    <?php if (!empty($records)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($records as $record): $read = $isRead($record['is_read'] ?? false); ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex items-center justify-between gap-3 <?php echo $read ? 'opacity-60' : ''; ?>">
                    <span class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full <?php echo $read ? 'bg-slate-300' : 'bg-green-500'; ?>"></span>
                        <span><?php echo htmlspecialchars($record['message']); ?></span>
                        <span class="text-[11px] text-slate-400"><?php echo htmlspecialchars($record['created_at']); ?></span>
                    </span>
                    <?php if (!$read): ?>
                        <a href="index.php?module=Notifications&action=mark_read&id=<?php echo (int) $record['id']; ?>&back=<?php echo urlencode('index.php?module=Notifications&action=manage'); ?>" class="text-[11px] font-bold text-green-700 hover:text-green-800 whitespace-nowrap">Mark read</a>
                    <?php else: ?>
                        <span class="text-[11px] font-bold text-slate-400 whitespace-nowrap">Read</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500">No notifications yet.</p>
    <?php endif; ?>
</div>
