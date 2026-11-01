<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Security Logs</h1>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="font-bold text-slate-800 mb-2">Supported roles</h2>
    <p class="text-sm text-slate-600"><?php echo htmlspecialchars(implode(', ', RbacHelper::roles())); ?></p>
</div>
<?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="font-bold text-slate-800 mb-2">Data backup</h2>
    <p class="text-sm text-slate-600 mb-3">Download a JSON backup of the current PostgreSQL data.</p>
    <form method="post" action="index.php?module=Security&action=backup">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(CsrfHelper::token()); ?>">
        <button class="bg-green-700 text-white rounded-lg px-4 py-2" type="submit">Download backup</button>
    </form>
</div>
<?php endif; ?>
<div class="glass-card p-4 sm:p-6">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($data as $log): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm"><?php echo htmlspecialchars($log['action']); ?> at <?php echo htmlspecialchars($log['log_time']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No security logs found.</p>
    <?php endif; ?>
</div>
