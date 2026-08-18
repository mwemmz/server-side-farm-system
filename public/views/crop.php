<!DOCTYPE html>
<html>
<head><title>Crop Management</title></head>
<body>
    <h1>Crop Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $crop): ?>
                <li><?php echo htmlspecialchars($crop['name']); ?> - <?php echo htmlspecialchars($crop['variety']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</body>
</html>
