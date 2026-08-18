<!DOCTYPE html>
<html>
<head><title>Supplier Management</title></head>
<body>
    <h1>Supplier Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Name: <?php echo htmlspecialchars($item['name']); ?>, Contact: <?php echo htmlspecialchars($item['contact_info']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
