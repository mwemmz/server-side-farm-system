<!DOCTYPE html>
<html>
<head><title>Farm Management</title></head>
<body>
    <h1>Farm Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $farm): ?>
                <li><?php echo htmlspecialchars($farm['name']); ?> (<?php echo htmlspecialchars($farm['location']); ?>)</li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</body>
</html>
