<h1 class="text-3xl font-bold mb-6 text-green-800">Harvest Management</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $item): ?>
                <li class="mb-2">Quantity: <?php echo htmlspecialchars($item['quantity']); ?>, Quality: <?php echo htmlspecialchars($item['quality']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No harvest records yet.</p>
    <?php endif; ?>
</div>
