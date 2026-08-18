<h1 class="text-3xl font-bold mb-6 text-green-800">Weather Records</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $item): ?>
                <li class="mb-2">Temp: <?php echo htmlspecialchars($item['temperature']); ?>, Humidity: <?php echo htmlspecialchars($item['humidity']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No weather data found.</p>
    <?php endif; ?>
</div>
