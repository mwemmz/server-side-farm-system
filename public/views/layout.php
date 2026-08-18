<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFMS - Intelligent Farm Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        darkMode: 'class',
      }
    </script>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="min-h-screen">
    <aside class="w-64 glass-card fixed left-4 top-4 bottom-4 z-40 p-4 overflow-y-auto">
        <div class="p-4 mb-4">
            <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-white">FFMS</h1>
        </div>
        <nav class="flex flex-col space-y-1">
            <?php
            $currentModule = $_GET['module'] ?? 'Dashboard';
            $modules = ['Dashboard', 'Analytics', 'Crop', 'Equipment', 'Farm', 'Field', 'Finance', 'Harvest', 'Inventory', 'Irrigation', 'Labour', 'Livestock', 'Market', 'Notifications', 'Pest', 'Procurement', 'Reports', 'Sales', 'Security', 'Storage', 'Supplier', 'Weather'];
            foreach ($modules as $mod) {
                $active = ($currentModule === $mod) ? 'active' : '';
                echo "<a href='index.php?module={$mod}' class='nav-link {$active}'>{$mod}</a>";
            }
            ?>
        </nav>
    </aside>
    <div class="ml-72 flex-grow flex flex-col p-4">
        <header class="glass-card py-4 px-8 flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-slate-800 dark:text-white">Farm Management System</h2>
            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="p-2 rounded-full bg-slate-200 dark:bg-slate-700">🌙</button>
                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Administrator</span>
            </div>
        </header>
        <main class="flex-grow">
            <?php
            require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
            $success = SessionHelper::getFlash('success');
            if ($success): ?>
                <div class="glass-card bg-green-100/50 p-4 mb-6 text-green-900" role="alert">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            <?php echo $content; ?>
        </main>
    </div>
        <footer class="text-center p-4 text-sm opacity-70">
            &copy; 2026 Intelligent Farm Management System
        </footer>
    </div>
    <script>
        const toggle = document.getElementById('theme-toggle');
        const body = document.body;
        // Apply dark mode class to body if saved
        if (localStorage.getItem('theme') === 'dark') body.classList.add('dark');
        toggle.addEventListener('click', () => {
            body.classList.toggle('dark');
            localStorage.setItem('theme', body.classList.contains('dark') ? 'dark' : 'light');
        });
    </script>
</body>
</html>
