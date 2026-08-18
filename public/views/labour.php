<!DOCTYPE html>
<html>
<head><title>Labour Management</title></head>
<body>
    <h1>Labour Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Name: <?php echo htmlspecialchars($item['name']); ?>, Role: <?php echo htmlspecialchars($item['role']); ?>, Date: <?php echo htmlspecialchars($item['attendance_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
