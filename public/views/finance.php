<h1 class="text-3xl font-bold mb-6 text-green-800">Finance Management</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $item): ?>
                <li class="mb-2"><?php echo htmlspecialchars($item['description']); ?> - <?php echo htmlspecialchars($item['amount']); ?> (<?php echo htmlspecialchars($item['type']); ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No finance records yet.</p>
    <?php endif; ?>
</div>
