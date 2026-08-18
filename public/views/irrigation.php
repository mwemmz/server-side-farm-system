<!DOCTYPE html>
<html>
<head><title>Irrigation Management</title></head>
<body>
    <h1>Irrigation Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Type: <?php echo htmlspecialchars($item['type']); ?>, Status: <?php echo htmlspecialchars($item['status']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
