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
    <a href="index.php?module=Security&action=change_password" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-shadow">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Change Password
    </a>
    <?php if ($isAdmin): ?>
    <a href="index.php?module=Security&action=users" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold shadow-sm hover:bg-slate-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
        Manage Users
    </a>
    <?php endif; ?>
    <a href="index.php?module=Security&action=audit" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold shadow-sm hover:bg-slate-50 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
        Audit Log
    </a>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Recent login activity</h2>
    <?php if (!empty($logs)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($logs as $log): ?>
                <?php $failed = strpos((string) $log['action'], 'failed') !== false; ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full <?php echo $failed ? 'bg-red-500' : 'bg-emerald-500'; ?>"></span>
                        <strong><?php echo htmlspecialchars($log['username'] ?? 'unknown'); ?></strong>
                        <?php echo htmlspecialchars($log['action']); ?>
                    </span>
                    <span class="text-[11px] text-slate-400 whitespace-nowrap"><?php echo htmlspecialchars($log['log_time'] ?? ''); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500">No login activity recorded yet.</p>
    <?php endif; ?>
</div>
