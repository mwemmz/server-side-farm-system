<!DOCTYPE html>
<html>
<head><title>Storage Management</title></head>
<body>
    <h1>Storage Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Name: <?php echo htmlspecialchars($item['name']); ?>, Capacity: <?php echo htmlspecialchars($item['capacity']); ?>, Stock: <?php echo htmlspecialchars($item['current_stock']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
