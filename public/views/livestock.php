<!DOCTYPE html>
<html>
<head><title>Livestock Management</title></head>
<body>
    <h1>Livestock Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Type: <?php echo htmlspecialchars($item['type']); ?>, Breed: <?php echo htmlspecialchars($item['breed']); ?>, DOB: <?php echo htmlspecialchars($item['dob']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
