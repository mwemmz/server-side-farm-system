<!DOCTYPE html>
<html>
<head><title>Harvest Management</title></head>
<body>
    <h1>Harvest Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Crop ID: <?php echo htmlspecialchars($item['crop_id']); ?>, Date: <?php echo htmlspecialchars($item['harvest_date']); ?>, Quantity: <?php echo htmlspecialchars($item['quantity']); ?>, Quality: <?php echo htmlspecialchars($item['quality']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
