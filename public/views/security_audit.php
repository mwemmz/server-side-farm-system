<?php
$logs = $data['logs'] ?? [];
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-1 text-green-800">Audit Log</h1>
<p class="text-sm text-slate-500 mb-5">Human and system decisions recorded across the farm.</p>

<div class="glass-card p-4 sm:p-6">
    <?php if (!empty($logs)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($logs as $log): ?>
                <?php $isSystem = ($log['source'] ?? '') === 'system'; ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $isSystem ? 'bg-slate-200 text-slate-700' : 'bg-emerald-100 text-emerald-800'; ?>"><?php echo htmlspecialchars($log['source'] ?? 'human'); ?></span>
                            <strong><?php echo htmlspecialchars($log['action'] ?? ''); ?></strong>
                            <?php if (!empty($log['entity_type'])): ?>
                                <span class="text-slate-400">· <?php echo htmlspecialchars($log['entity_type'] . ($log['entity_id'] ? ' #' . $log['entity_id'] : '')); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="text-[11px] text-slate-400 whitespace-nowrap"><?php echo htmlspecialchars($log['created_at'] ?? ''); ?></span>
                    </div>
                    <?php if (!empty($log['details'])): ?>
                        <div class="text-xs text-slate-500 mt-1 break-words"><?php echo htmlspecialchars((string) $log['details']); ?></div>
                    <?php endif; ?>
                    <div class="text-[11px] text-slate-400 mt-1">by <?php echo htmlspecialchars($log['username'] ?? 'system'); ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-slate-500">No audit entries recorded yet.</p>
    <?php endif; ?>
</div>

<p class="mt-4"><a href="index.php?module=Security" class="text-sm font-semibold text-green-700 hover:text-green-800">← Back to Security</a></p>
