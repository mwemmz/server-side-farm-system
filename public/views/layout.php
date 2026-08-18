<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFMS - Intelligent Farm Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
    <aside class="w-64 bg-green-800 text-white p-4">
        <h1 class="text-xl font-bold mb-6">FFMS</h1>
        <nav class="space-y-2">
            <a href="index.php?module=Dashboard" class="block hover:text-green-200">Dashboard</a>
            <?php
            $modules = ['Analytics', 'Crop', 'Equipment', 'Farm', 'Field', 'Finance', 'Harvest', 'Inventory', 'Irrigation', 'Labour', 'Livestock', 'Market', 'Notifications', 'Pest', 'Procurement', 'Reports', 'Sales', 'Security', 'Storage', 'Supplier', 'Weather'];
            foreach ($modules as $mod) {
                echo "<a href='index.php?module={$mod}' class='block hover:text-green-200'>{$mod}</a>";
            }
            ?>
        </nav>
    </aside>
    <div class="flex-grow flex flex-col">
        <header class="bg-white shadow-sm p-4">
            <h2 class="text-xl font-semibold">Farm Management System</h2>
        </header>
        <main class="flex-grow p-6">
            <?php
            require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
            $success = SessionHelper::getFlash('success');
            if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php echo $content; ?>
        </main>
        <footer class="bg-gray-800 text-white text-center p-4">
            &copy; 2026 Intelligent Farm Management System
        </footer>
    </div>
</body>
</html>
