<!DOCTYPE html>
<html>
<head><title>Field Management</title></head>
<body>
    <h1>Field Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Name: <?php echo htmlspecialchars($item['name']); ?>, Size: <?php echo htmlspecialchars($item['size']); ?>, Soil: <?php echo htmlspecialchars($item['soil_type']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
