<h1 class="text-3xl font-bold mb-6 text-green-800">Supplier Records</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $item): ?>
                <li class="mb-2"><?php echo htmlspecialchars($item['name']); ?>: <?php echo htmlspecialchars($item['contact_info']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No suppliers registered.</p>
    <?php endif; ?>
</div>
