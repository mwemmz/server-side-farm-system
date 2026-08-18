<h1 class="text-3xl font-bold mb-6 text-green-800">Equipment Management</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $item): ?>
                <li class="mb-2">Name: <?php echo htmlspecialchars($item['name']); ?>, Status: <?php echo htmlspecialchars($item['maintenance_status']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No equipment registered yet.</p>
    <?php endif; ?>
</div>
