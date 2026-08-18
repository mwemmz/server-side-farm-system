<!DOCTYPE html>
<html>
<head><title>Weather Management</title></head>
<body>
    <h1>Weather Management</h1>
    <?php if (isset($data)): ?>
        <ul>
            <?php foreach ($data as $item): ?>
                <li>Temp: <?php echo htmlspecialchars($item['temperature']); ?>°C, Humidity: <?php echo htmlspecialchars($item['humidity']); ?>%, Date: <?php echo htmlspecialchars($item['weather_date']); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
