<!DOCTYPE html>
<html>
<head><title>Procurement Management</title></head>
<body>
    <h1>Procurement Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Item: <?php echo htmlspecialchars($item['item_name']); ?>, Quantity: <?php echo htmlspecialchars($item['quantity']); ?>, Cost: <?php echo htmlspecialchars($item['cost']); ?>, Date: <?php echo htmlspecialchars($item['purchase_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
