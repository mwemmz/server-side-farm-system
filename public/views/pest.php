<!DOCTYPE html>
<html>
<head><title>Pest Management</title></head>
<body>
    <h1>Pest Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Pest: <?php echo htmlspecialchars($item['pest_name']); ?>, Date: <?php echo htmlspecialchars($item['detected_date']); ?>, Action: <?php echo htmlspecialchars($item['action_taken']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
