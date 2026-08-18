<h1 class="text-3xl font-bold mb-6 text-green-800">Farm Management</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $farm): ?>
                <li class="mb-2"><?php echo htmlspecialchars($farm['name']); ?> <span class="text-gray-500">- <?php echo htmlspecialchars($farm['location']); ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No farms registered yet.</p>
    <?php endif; ?>
</div>

