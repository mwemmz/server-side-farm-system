<h1 class="text-3xl font-bold mb-6 text-green-800">Market Data</h1>
<div class="bg-white p-6 rounded-lg shadow">
    <?php if (isset($data) && !empty($data)): ?>
        <ul class="list-disc pl-6">
            <?php foreach ($data as $item): ?>
                <li class="mb-2"><?php echo htmlspecialchars($item['crop_name']); ?>: <?php echo htmlspecialchars($item['price']); ?> on <?php echo htmlspecialchars($item['market_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No market data available.</p>
    <?php endif; ?>
</div>
