<h1 class="text-3xl font-bold mb-6 text-green-800">Reports</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $item): ?>
                <li class="mb-2"><?php echo htmlspecialchars($item['report_type']); ?> generated at <?php echo htmlspecialchars($item['generated_at']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No reports generated.</p>
    <?php endif; ?>
</div>
