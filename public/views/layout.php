<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFMS - Intelligent Farm Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .active-link { background-color: rgba(255, 255, 255, 0.1); border-left: 4px solid #fff; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex text-gray-900">
    <aside class="w-64 bg-green-900 text-white flex-shrink-0">
        <div class="p-6">
            <h1 class="text-2xl font-bold tracking-tight">FFMS</h1>
        </div>
        <nav class="px-4 space-y-1">
            <?php
            $currentModule = $_GET['module'] ?? 'Dashboard';
            $modules = ['Dashboard', 'Analytics', 'Crop', 'Equipment', 'Farm', 'Field', 'Finance', 'Harvest', 'Inventory', 'Irrigation', 'Labour', 'Livestock', 'Market', 'Notifications', 'Pest', 'Procurement', 'Reports', 'Sales', 'Security', 'Storage', 'Supplier', 'Weather'];
            foreach ($modules as $mod) {
                $active = ($currentModule === $mod) ? 'active-link' : '';
                echo "<a href='index.php?module={$mod}' class='block px-4 py-2 rounded transition duration-200 hover:bg-green-800 {$active}'>{$mod}</a>";
            }
            ?>
        </nav>
    </aside>
    <div class="flex-grow flex flex-col">
        <header class="bg-white shadow-sm py-4 px-8 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-gray-800">Farm Management System</h2>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">Administrator</span>
                <div class="w-8 h-8 rounded-full bg-green-600 flex items-center justify-center text-white font-bold">A</div>
            </div>
        </header>
        <main class="flex-grow p-8">
            <?php
            require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
            $success = SessionHelper::getFlash('success');
            if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md mb-6" role="alert">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php echo $content; ?>
        </main>
        <footer class="bg-white border-t border-gray-200 text-gray-600 text-center p-4 text-sm">
            &copy; 2026 Intelligent Farm Management System
        </footer>
    </div>
</body>
</html>
