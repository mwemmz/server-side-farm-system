<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Equipment Management</h1>
<div class="glass-card p-4 sm:p-6">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($data as $item): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm">Name: <?php echo htmlspecialchars($item['name']); ?>, Status: <?php echo htmlspecialchars($item['maintenance_status']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No equipment registered yet.</p>
    <?php endif; ?>
</div>
