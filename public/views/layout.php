<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFMS - Intelligent Farm Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
          }
        }
      }
    </script>
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="min-h-screen flex">
    <!-- Desktop sidebar -->
    <aside class="hidden md:flex w-64 bg-slate-950 text-slate-200 flex-col shrink-0 sticky top-0 h-screen">
        <div class="absolute inset-0 bg-grid pointer-events-none opacity-30"></div>
        <!-- Brand -->
        <div class="px-5 py-6 shrink-0 relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-32 h-32 rounded-full bg-green-500/20 blur-2xl"></div>
            <div class="relative flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-900/40">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                </div>
                <div>
                    <div class="text-lg font-extrabold tracking-tight text-white leading-none">FFMS</div>
                    <div class="text-[11px] text-slate-400 mt-1">Farm Management System</div>
                </div>
            </div>
        </div>
        <!-- Navigation -->
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
            <?php
            $currentModule = $_GET['module'] ?? 'Dashboard';
            $modules = [
                'Dashboard' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
                'Farm' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
                'Field' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
                'Crop' => '<path d="M7 20h10"/><path d="M10 20c5.5-2.5 8.7-6.4 9.3-11.1"/><path d="M12 15c3.5-2.5 7-4.5 10-5.5"/><path d="M10 7a4 4 0 0 1 8 0"/><path d="M6 17c-3.5-2.5-7-4.5-10-5.5"/><path d="M14 7a4 4 0 0 0-8 0"/>',
                'Irrigation' => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
                'Livestock' => '<path d="M3 7V5c0-1.1.9-2 2-2h2"/><path d="M17 3h2c1.1 0 2 .9 2 2v2"/><path d="M21 17v2c0 1.1-.9 2-2 2h-2"/><path d="M7 21H5c-1.1 0-2-.9-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/>',
                'Inventory' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
                'Equipment' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
                'Labour' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                'Pest' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
                'Weather' => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
                'Harvest' => '<path d="M2 22 16 8"/><path d="M3.47 12.53 5 11l1.53 1.53a3.5 3.5 0 0 1 0 4.94L5 19l-1.53-1.53a3.5 3.5 0 0 1 0-4.94Z"/><path d="M7.47 8.53 9 7l1.53 1.53a3.5 3.5 0 0 1 0 4.94L9 15l-1.53-1.53a3.5 3.5 0 0 1 0-4.94Z"/><path d="M11.47 4.53 13 3l1.53 1.53a3.5 3.5 0 0 1 0 4.94L13 11l-1.53-1.53a3.5 3.5 0 0 1 0-4.94Z"/><path d="M20 2h2v2a4 4 0 0 1-4 4h-2V6a4 4 0 0 1 4-4Z"/><path d="M11.47 17.47 13 19l-1.53 1.53a3.5 3.5 0 0 1-4.94 0L5 19l1.53-1.53a3.5 3.5 0 0 1 4.94 0Z"/><path d="M15.47 13.47 17 15l-1.53 1.53a3.5 3.5 0 0 1-4.94 0L9 15l1.53-1.53a3.5 3.5 0 0 1 4.94 0Z"/><path d="M19.47 9.47 21 11l-1.53 1.53a3.5 3.5 0 0 1-4.94 0L13 11l1.53-1.53a3.5 3.5 0 0 1 4.94 0Z"/>',
                'Market' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
                'Finance' => '<line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
                'Supplier' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
                'Storage' => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
                'Analytics' => '<line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/>',
                'Notifications' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
                'Reports' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/>',
                'Security' => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
                'Procurement' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
                'Sales' => '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
            ];
            foreach ($modules as $mod => $iconPath) {
                $isActive = ($currentModule === $mod);
                $activeClass = $isActive
                    ? 'bg-gradient-to-r from-green-600 to-emerald-600 text-white shadow-lg shadow-green-900/40'
                    : 'text-slate-400 hover:bg-white/5 hover:text-white';
                $dot = $isActive ? '<span class="ml-auto h-1.5 w-1.5 rounded-full bg-white/80"></span>' : '';
                echo "<a href='index.php?module={$mod}' class='group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200 {$activeClass}'>
                    <svg class='w-5 h-5 shrink-0 transition-transform duration-200 group-hover:scale-110' fill='none' stroke='currentColor' stroke-width='2' viewBox='0 0 24 24'>{$iconPath}</svg>
                    <span>{$mod}</span>
                    {$dot}
                </a>";
            }
            ?>
        </nav>
        <!-- User footer -->
        <div class="px-4 py-4 border-t border-white/10 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-green-600 to-emerald-700 flex items-center justify-center text-sm font-bold text-white shrink-0">A</div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-slate-200 truncate">Administrator</div>
                    <div class="text-[11px] text-slate-500 truncate">admin@ffms.com</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main content area -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Mobile top bar -->
        <header class="md:hidden sticky top-0 z-30 bg-slate-950/90 backdrop-blur-lg text-white flex items-center justify-between px-4 py-3 shadow-lg border-b border-white/10">
            <div class="flex items-center gap-2 font-extrabold tracking-tight">
                <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                FFMS
            </div>
            <button id="theme-toggle" class="p-2 rounded-full bg-white/10 hover:bg-white/20 transition">
                <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </header>

        <!-- Top bar (desktop) -->
        <header class="hidden md:flex sticky top-0 z-30 bg-slate-950/80 backdrop-blur-xl text-white items-center justify-between px-8 py-3 shadow-lg border-b border-white/10">
            <div class="flex items-center gap-2 text-sm font-medium text-slate-300">
                <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                Farm Management System
            </div>
            <div class="flex items-center gap-4">
                <button id="theme-toggle-desktop" class="p-2 rounded-full bg-white/10 hover:bg-white/20 transition">
                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <div class="text-[11px] text-slate-400 font-medium">Administrator</div>
            </div>
        </header>

        <main class="flex-1 p-4 md:p-8 max-w-[1400px] w-full mx-auto relative">
            <!-- Decorative background blobs -->
            <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden" aria-hidden>
                <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-green-400/15 blur-3xl animate-blob"></div>
                <div class="absolute top-1/3 -right-32 w-96 h-96 rounded-full bg-amber-400/10 blur-3xl animate-blob" style="animation-delay: 2.5s"></div>
                <div class="absolute bottom-0 left-1/3 w-80 h-80 rounded-full bg-emerald-400/10 blur-3xl animate-blob" style="animation-delay: 5s"></div>
            </div>

            <div class="relative z-10">
                <?php
                require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
                $success = SessionHelper::getFlash('success');
                if ($success): ?>
                    <div class="glass-card card-glow bg-emerald-50/80 p-4 mb-6 text-emerald-800 flex items-center gap-3" role="alert">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>
                <?php echo $content; ?>
            </div>
        </main>

        <footer class="text-center p-4 text-[11px] text-slate-500">
            &copy; 2026 Intelligent Farm Management System
        </footer>
    </div>

    <script>
        // Theme toggle
        const toggles = document.querySelectorAll('[id^="theme-toggle"]');
        const body = document.body;
        if (localStorage.getItem('theme') === 'dark') body.classList.add('dark');
        toggles.forEach(btn => {
            btn.addEventListener('click', () => {
                body.classList.toggle('dark');
                localStorage.setItem('theme', body.classList.contains('dark') ? 'dark' : 'light');
            });
        });
    </script>
</body>
</html>
