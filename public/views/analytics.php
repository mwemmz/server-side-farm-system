<!DOCTYPE html>
<html>
<head><title>Analytics Management</title></head>
<body>
    <h1>Analytics Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Module: <?php echo htmlspecialchars($item['module_name']); ?>, Data: <?php echo htmlspecialchars($item['data_points']); ?>, Date: <?php echo htmlspecialchars($item['recorded_at']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
