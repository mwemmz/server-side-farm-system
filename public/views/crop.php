<h1 class="text-3xl font-bold mb-6 text-green-800">Crop Management</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $crop): ?>
                <li class="mb-2"><?php echo htmlspecialchars($crop['name']); ?> - <?php echo htmlspecialchars($crop['variety']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No crops registered yet.</p>
    <?php endif; ?>
</div>
