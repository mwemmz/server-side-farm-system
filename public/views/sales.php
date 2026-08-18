<!DOCTYPE html>
<html>
<head><title>Sales Management</title></head>
<body>
    <h1>Sales Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Customer: <?php echo htmlspecialchars($item['customer_name']); ?>, Amount: <?php echo htmlspecialchars($item['amount']); ?>, Date: <?php echo htmlspecialchars($item['sale_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
