<!DOCTYPE html>
<html>
<head><title>Market Management</title></head>
<body>
    <h1>Market Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Crop: <?php echo htmlspecialchars($item['crop_name']); ?>, Price: <?php echo htmlspecialchars($item['price']); ?>, Date: <?php echo htmlspecialchars($item['market_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
