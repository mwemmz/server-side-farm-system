<h1 class="text-3xl font-bold mb-6 text-green-800">Analytics Management</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $item): ?>
                <li class="mb-2">Module: <?php echo htmlspecialchars($item['module_name']); ?>, Data: <?php echo htmlspecialchars($item['data_points']); ?>, Date: <?php echo htmlspecialchars($item['recorded_at']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No analytics data available.</p>
    <?php endif; ?>
</div>
