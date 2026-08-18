<h1 class="text-3xl font-bold mb-6 text-green-800">Security Logs</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $log): ?>
                <li class="mb-2"><?php echo htmlspecialchars($log['action']); ?> at <?php echo htmlspecialchars($log['log_time']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No security logs found.</p>
    <?php endif; ?>
</div>
