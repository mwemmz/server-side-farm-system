<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Security Logs</h1>
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
