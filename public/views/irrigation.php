<h1 class="text-3xl font-bold mb-6 text-green-800">Irrigation Management</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $item): ?>
                <li class="mb-2">Type: <?php echo htmlspecialchars($item['type']); ?>, Status: <?php echo htmlspecialchars($item['status']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No irrigation systems registered.</p>
    <?php endif; ?>
</div>
