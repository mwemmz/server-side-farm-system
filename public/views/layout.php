<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFMS - Intelligent Farm Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .active-link { background-color: rgba(255, 255, 255, 0.2); border-left: 4px solid #fff; }
    </style>
</head>
<body class="min-h-screen flex text-gray-900 dark:text-gray-100">
    <div class="bg-animate">
        <div class="shape" style="top: -50px; left: -50px;"></div>
        <div class="shape" style="bottom: -50px; right: -50px; animation-delay: -5s;"></div>
    </div>
    <aside class="w-64 bg-green-900/80 backdrop-blur-md text-white flex-shrink-0">
        <div class="p-6">
            <h1 class="text-2xl font-bold tracking-tight">FFMS</h1>
        </div>
        <nav class="px-4 space-y-1">
            <?php
            $currentModule = $_GET['module'] ?? 'Dashboard';
            $modules = ['Dashboard', 'Analytics', 'Crop', 'Equipment', 'Farm', 'Field', 'Finance', 'Harvest', 'Inventory', 'Irrigation', 'Labour', 'Livestock', 'Market', 'Notifications', 'Pest', 'Procurement', 'Reports', 'Sales', 'Security', 'Storage', 'Supplier', 'Weather'];
            foreach ($modules as $mod) {
                $active = ($currentModule === $mod) ? 'active-link' : '';
                echo "<a href='index.php?module={$mod}' class='block px-4 py-2 rounded transition duration-200 hover:bg-green-800/50 {$active}'>{$mod}</a>";
            }
            ?>
        </nav>
    </aside>
    <div class="flex-grow flex flex-col">
        <header class="glass py-4 px-8 flex justify-between items-center sticky top-0 z-10">
            <h2 class="text-xl font-semibold">Farm Management System</h2>
            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">🌙</button>
                <span class="text-sm">Administrator</span>
                <div class="w-8 h-8 rounded-full bg-green-600 flex items-center justify-center text-white font-bold">A</div>
            </div>
        </header>
        <main class="flex-grow p-8">
            <?php
            require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
            $success = SessionHelper::getFlash('success');
            if ($success): ?>
                <div class="glass bg-green-100/50 border border-green-200 text-green-900 dark:text-green-100 px-4 py-3 rounded-md mb-6" role="alert">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php echo $content; ?>
        </main>
        <footer class="text-center p-4 text-sm opacity-70">
            &copy; 2026 Intelligent Farm Management System
        </footer>
    </div>
    <script>
        const toggle = document.getElementById('theme-toggle');
        const body = document.body;
        if (localStorage.getItem('theme') === 'dark') body.classList.add('dark-mode');
        toggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            localStorage.setItem('theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
        });
    </script>
</body>
</html>
