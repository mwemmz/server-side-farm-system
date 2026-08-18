<!DOCTYPE html>
<html>
<head><title>Finance Management</title></head>
<body>
    <h1>Finance Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Type: <?php echo htmlspecialchars($item['type']); ?>, Amount: <?php echo htmlspecialchars($item['amount']); ?>, Description: <?php echo htmlspecialchars($item['description']); ?>, Date: <?php echo htmlspecialchars($item['date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
