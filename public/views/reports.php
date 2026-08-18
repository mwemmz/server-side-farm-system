<!DOCTYPE html>
<html>
<head><title>Reports Management</title></head>
<body>
    <h1>Reports Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Type: <?php echo htmlspecialchars($item['report_type']); ?>, Generated: <?php echo htmlspecialchars($item['generated_at']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
