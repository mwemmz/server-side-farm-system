<!DOCTYPE html>
<html>
<head><title>Notifications Management</title></head>
<body>
    <h1>Notifications Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Message: <?php echo htmlspecialchars($item['message']); ?>, Read: <?php echo $item['is_read'] ? 'Yes' : 'No'; ?>, Created: <?php echo htmlspecialchars($item['created_at']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
