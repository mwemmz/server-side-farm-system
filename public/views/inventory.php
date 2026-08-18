<!DOCTYPE html>
<html>
<head><title>Inventory Management</title></head>
<body>
    <h1>Inventory Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Name: <?php echo htmlspecialchars($item['name']); ?>, Quantity: <?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
