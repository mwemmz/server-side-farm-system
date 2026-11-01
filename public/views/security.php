<?php
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
$logs = $data ?? [];
$flash = SessionHelper::getFlash('success');
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-1 text-green-800">Security &amp; Access</h1>
<p class="text-sm text-slate-500 mb-5">Login activity, access control and the audit trail.</p>

<?php if ($flash): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="flex flex-wrap items-center gap-3 mb-6">
    <a href="index.php?module=Security&action=change_password" class="px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold">Change Password</a>
    <?php if ($isAdmin): ?>
    <a href="index.php?module=Security&action=users" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold">Manage Users</a>
    <a href="index.php?module=Security&action=audit" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold">Audit Log</a>
    <?php endif; ?>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="font-bold text-slate-800 mb-2">Supported roles</h2>
    <p class="text-sm text-slate-600"><?php echo htmlspecialchars(implode(', ', RbacHelper::roles())); ?></p>
</div>

<?php if ($isAdmin): ?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="font-bold text-slate-800 mb-2">Data backup</h2>
    <p class="text-sm text-slate-600 mb-3">Download a JSON backup of the current PostgreSQL data.</p>
    <form method="post" action="index.php?module=Security&action=backup">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
        <button class="bg-green-700 text-white rounded-lg px-4 py-2" type="submit">Download backup</button>
    </form>
</div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Recent login activity</h2>
    <?php if (!empty($logs)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($logs as $log): ?>
                <?php $failed = strpos((string) $log['action'], 'failed') !== false; ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex items-center justify-between gap-3">
                    <span><strong><?php echo htmlspecialchars($log['username'] ?? 'unknown'); ?></strong> <?php echo htmlspecialchars($log['action']); ?></span>
                    <span class="text-[11px] text-slate-400 whitespace-nowrap"><?php echo htmlspecialchars($log['log_time'] ?? ''); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500">No login activity recorded yet.</p>
    <?php endif; ?>
</div>
