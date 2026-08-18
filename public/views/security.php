<!DOCTYPE html>
<html>
<head><title>Security Management</title></head>
<body>
    <h1>Security Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>User: <?php echo htmlspecialchars($item['user_id']); ?>, Action: <?php echo htmlspecialchars($item['action']); ?>, Time: <?php echo htmlspecialchars($item['log_time']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
