<!DOCTYPE html>
<html>
<head><title>Equipment Management</title></head>
<body>
    <h1>Equipment Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Name: <?php echo htmlspecialchars($item['name']); ?>, Status: <?php echo htmlspecialchars($item['maintenance_status']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
