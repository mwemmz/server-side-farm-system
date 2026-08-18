<h1 class="text-3xl font-bold mb-6 text-green-800">Field Management</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $field): ?>
                <li class="mb-2"><?php echo htmlspecialchars($field['name']); ?> - <?php echo htmlspecialchars($field['soil_type']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No fields registered yet.</p>
    <?php endif; ?>
</div>
