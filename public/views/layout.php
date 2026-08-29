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
                'Insights' => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5.76.76 1.23 1.52 1.41 2.5"/>',
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
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-green-600 to-emerald-700 flex items-center justify-center text-sm font-bold text-white shrink-0"><?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?></div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-slate-200 truncate capitalize"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?></div>
                    <div class="text-[11px] text-slate-500 truncate"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'user')); ?></div>
                </div>
            </div>
            <a href="index.php?module=Security&action=logout"
               class="mt-3 w-full flex items-center justify-center gap-2 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300 hover:bg-white/10 hover:text-white transition-colors py-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                Sign out
            </a>
        </div>
    </aside>

    <!-- Main content area -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Mobile top bar -->
        <header class="md:hidden sticky top-0 z-40 bg-slate-950/90 backdrop-blur-lg text-white flex items-center justify-between px-3 sm:px-4 py-3 shadow-lg border-b border-white/10">
            <div class="flex items-center gap-2 font-extrabold tracking-tight">
                <button id="mobile-menu-btn" aria-label="Open menu" aria-controls="mobile-drawer"
                        class="p-2 -ml-1 rounded-lg bg-white/10 hover:bg-white/20 transition">
                    <svg class="w-5 h-5 text-slate-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/></svg>
                </button>
                <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                FFMS
            </div>
            <div class="flex items-center gap-2">
                <button id="theme-toggle" class="p-2 rounded-full bg-white/10 hover:bg-white/20 transition">
                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </header>

        <!-- Mobile slide-in drawer -->
        <div id="mobile-drawer" class="md:hidden fixed inset-0 z-50 hidden" aria-hidden="true">
            <!-- Backdrop -->
            <div id="mobile-drawer-backdrop" class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
            <!-- Panel -->
            <div id="mobile-drawer-panel" class="absolute inset-y-0 left-0 w-72 max-w-[85vw] bg-slate-950 text-slate-200 flex flex-col shadow-2xl -translate-x-full transition-transform duration-300 ease-out">
                <!-- Brand + close -->
                <div class="px-4 py-4 flex items-center justify-between border-b border-white/10">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div>
                            <div class="text-base font-extrabold text-white leading-none">FFMS</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">Farm Management</div>
                        </div>
                    </div>
                    <button id="mobile-menu-close" aria-label="Close menu" class="p-2 rounded-lg bg-white/10 hover:bg-white/20 transition">
                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
                <!-- Links -->
                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                    <?php
                    foreach ($modules as $mod => $iconPath) {
                        $isActive = ($currentModule === $mod);
                        $activeClass = $isActive
                            ? 'bg-gradient-to-r from-green-600 to-emerald-600 text-white'
                            : 'text-slate-400 hover:bg-white/5 hover:text-white';
                        echo "<a href='index.php?module={$mod}' class='mobile-nav-link flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition-all duration-200 {$activeClass}'>
                            <svg class='w-5 h-5 shrink-0' fill='none' stroke='currentColor' stroke-width='2' viewBox='0 0 24 24'>{$iconPath}</svg>
                            <span>{$mod}</span>
                        </a>";
                    }
                    ?>
                </nav>
                <!-- User footer -->
                <div class="px-4 py-4 border-t border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-600 to-emerald-700 flex items-center justify-center text-xs font-bold text-white shrink-0"><?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?></div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-200 truncate capitalize"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?></div>
                            <div class="text-[11px] text-slate-500 truncate"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'user')); ?></div>
                        </div>
                    </div>
                    <a href="index.php?module=Security&action=logout"
                       class="mt-3 w-full flex items-center justify-center gap-2 rounded-xl bg-white/5 border border-white/10 text-xs font-semibold text-slate-300 hover:bg-white/10 hover:text-white transition-colors py-2">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Sign out
                    </a>
                </div>
            </div>
        </div>

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
                <div class="text-[11px] text-slate-400 font-medium capitalize"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'user')); ?></div>
            </div>
        </header>

        <main class="flex-1 p-3 sm:p-4 md:p-8 max-w-[1400px] w-full mx-auto relative overflow-x-hidden">
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

        // Mobile navigation drawer
        const drawer = document.getElementById('mobile-drawer');
        const panel = document.getElementById('mobile-drawer-panel');
        const openBtn = document.getElementById('mobile-menu-btn');
        const closeBtn = document.getElementById('mobile-menu-close');
        const backdrop = document.getElementById('mobile-drawer-backdrop');

        function openDrawer() {
            drawer.classList.remove('hidden');
            drawer.setAttribute('aria-hidden', 'false');
            // force reflow then animate
            requestAnimationFrame(() => panel.classList.remove('-translate-x-full'));
            body.style.overflow = 'hidden';
        }
        function closeDrawer() {
            panel.classList.add('-translate-x-full');
            body.style.overflow = '';
            setTimeout(() => {
                drawer.classList.add('hidden');
                drawer.setAttribute('aria-hidden', 'true');
            }, 300);
        }

        if (openBtn && drawer) {
            openBtn.addEventListener('click', openDrawer);
            closeBtn.addEventListener('click', closeDrawer);
            backdrop.addEventListener('click', closeDrawer);
            document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); });
            // Close on navigation + resize to desktop
            document.querySelectorAll('.mobile-nav-link').forEach(link => link.addEventListener('click', closeDrawer));
            window.addEventListener('resize', () => { if (window.innerWidth >= 768) closeDrawer(); });
        }
    </script>

    <!-- ============ Floating AI Assistant chat widget ============ -->
    <div id="ff-chat-root" class="fixed bottom-5 right-5 z-[100]">
        <!-- Launcher -->
        <button id="ff-chat-fab" aria-label="Open AI assistant"
                class="flex items-center gap-2 pl-3 pr-4 py-3 rounded-full bg-gradient-to-r from-green-600 to-emerald-600 text-white shadow-xl shadow-emerald-900/40 hover:shadow-emerald-700/50 hover:scale-[1.03] transition-all">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>
            <span class="text-sm font-bold hidden sm:inline">AI Assistant</span>
        </button>

        <!-- Window -->
        <div id="ff-chat-window" class="hidden fixed bottom-24 right-4 sm:right-5 w-[calc(100vw-2rem)] sm:w-96 max-h-[75vh] flex-col rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 bg-slate-950 text-white">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-bold leading-none">AI Assistant</div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Over your farm data</div>
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <button id="ff-chat-new" title="New chat" class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition">
                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    </button>
                    <button id="ff-chat-close" aria-label="Close chat" class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition">
                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>
            </div>
            <!-- Tabs: Chat | Memory -->
            <div class="flex items-stretch border-b border-slate-200 bg-slate-50/80">
                <button data-ff-tab="chat" class="ff-tab flex-1 py-2 px-3 text-xs font-bold uppercase tracking-wide text-green-700 border-b-2 border-green-600 bg-white">Chat</button>
                <button data-ff-tab="memory" class="ff-tab flex-1 py-2 px-3 text-xs font-bold uppercase tracking-wide text-slate-500 border-b-2 border-transparent hover:bg-slate-100">Memory</button>
            </div>
            <!-- Chat + Memory panes -->
            <div id="ff-chat-body" class="relative">
                <!-- Chat pane -->
                <div id="ff-chat-msgs" class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50 min-h-[16rem] max-h-[46vh]">
                    <div class="flex gap-2 items-end">
                        <div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-white border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm">
                            Hi! I can query your farm data. Try <em>"What should I do this week?"</em> or <em>"How much did I spend on fertilizer this season?"</em>
                        </div>
                    </div>
                </div>
                <!-- Memory pane (prior conversations) -->
                <div id="ff-chat-memory" class="hidden absolute inset-0 bg-slate-50 overflow-y-auto p-3 flex flex-col gap-2">
                    <div class="text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Previous conversations</div>
                    <div id="ff-memory-list" class="space-y-1.5"></div>
                    <div class="mt-auto pt-2"><button id="ff-memory-empty" class="w-full text-center text-xs font-semibold text-slate-500 border border-dashed border-slate-300 rounded-lg py-2 hover:bg-slate-100 transition">Start a new chat</button></div>
                </div>
            </div>
            <!-- Suggestions (chat tab only) -->
            <div id="ff-chat-chips" class="px-3 pb-2 flex flex-wrap gap-1.5">
                <button class="ff-chip text-[11px] px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 transition">What should I do this week?</button>
                <button class="ff-chip text-[11px] px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 transition">Is it a good time to sell?</button>
                <button class="ff-chip text-[11px] px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 transition">What's low in stock?</button>
            </div>
            <!-- Input -->
            <div class="border-t border-slate-200 p-3 bg-white">
                <form id="ff-chat-form" class="flex gap-2">
                    <input id="ff-chat-input" type="text" autocomplete="off" placeholder="Ask about your farm…"
                           class="flex-1 px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-green-500/40">
                    <button type="submit" class="shrink-0 px-3 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold hover:opacity-90 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const fab = document.getElementById('ff-chat-fab');
            const win = document.getElementById('ff-chat-window');
            const closeBtn = document.getElementById('ff-chat-close');
            const newBtn = document.getElementById('ff-chat-new');
            const msgs = document.getElementById('ff-chat-msgs');
            const memPane = document.getElementById('ff-chat-memory');
            const memList = document.getElementById('ff-memory-list');
            const form = document.getElementById('ff-chat-form');
            const input = document.getElementById('ff-chat-input');

            let sessionId = 0;

            function esc(s) {
                return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            }

            function fmtDate(iso) {
                if (!iso) return '';
                const d = new Date(iso.replace(' ', 'T'));
                return isNaN(d) ? '' : d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            }

            function appendBot(text, cards) {
                let html = '<div class="flex gap-2 items-end"><div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-white border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm whitespace-pre-wrap">' + esc(text);
                if (cards && cards.length) {
                    html += '<div class="mt-2 space-y-1.5">';
                    cards.forEach(c => {
                        html += '<a href="' + esc(c.link || '#') + '" class="block rounded-lg border border-green-100 bg-green-50/60 px-2.5 py-1.5 hover:bg-green-50 transition">'
                            + '<div class="text-xs font-bold text-green-800">' + esc(c.title || '') + '</div>'
                            + '<div class="text-[11px] text-slate-600 mt-0.5">' + esc(c.body || '') + '</div></a>';
                    });
                    html += '</div>';
                }
                html += '</div></div>';
                msgs.insertAdjacentHTML('beforeend', html);
                msgs.scrollTop = msgs.scrollHeight;
            }

            function appendUser(text) {
                msgs.insertAdjacentHTML('beforeend',
                    '<div class="flex justify-end"><div class="max-w-[85%] rounded-2xl rounded-br-sm bg-green-600 text-white px-3 py-2 text-sm shadow-sm">' + esc(text) + '</div></div>');
                msgs.scrollTop = msgs.scrollHeight;
            }

            function clearMessages() {
                msgs.innerHTML = '';
            }

            function loadHistory(sid) {
                return fetch('index.php?module=Insights&action=chat_history&session_id=' + sid)
                    .then(r => r.json())
                    .then(j => {
                        if (!j.success || !j.data) return;
                        clearMessages();
                        (j.data.messages || []).forEach(m => {
                            if (m.role === 'user') appendUser(m.text);
                            else appendBot(m.text, m.cards || []);
                        });
                        if (!msgs.innerHTML.trim()) {
                            msgs.insertAdjacentHTML('beforeend',
                                '<div class="flex gap-2 items-end"><div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-white border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm">This conversation is empty — ask something below.</div></div>');
                        }
                    });
            }

            function startNewChat() {
                sessionId = 0;
                clearMessages();
                msgs.insertAdjacentHTML('beforeend',
                    '<div class="flex gap-2 items-end"><div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-white border border-slate-200 px-3 py-2 text-sm text-slate-700 shadow-sm">Hi! Ask me about your farm.</div></div>');
            }

            function switchTab(name) {
                document.querySelectorAll('.ff-tab').forEach(t => {
                    const active = t.getAttribute('data-ff-tab') === name;
                    t.classList.toggle('text-green-700', active);
                    t.classList.toggle('border-green-600', active);
                    t.classList.toggle('text-slate-500', !active);
                    t.classList.toggle('bg-white', active);
                    t.classList.toggle('border-transparent', !active);
                });
                if (name === 'memory') {
                    msgs.classList.add('hidden');
                    memPane.classList.remove('hidden');
                    memPane.classList.add('flex');
                    loadSessions();
                } else {
                    memPane.classList.add('hidden');
                    memPane.classList.remove('flex');
                    msgs.classList.remove('hidden');
                }
            }

            function loadSessions() {
                fetch('index.php?module=Insights&action=chat_sessions')
                    .then(r => r.json())
                    .then(j => {
                        const list = (j.data && j.data.sessions) || [];
                        memList.innerHTML = '';
                        if (!list.length) {
                            memList.innerHTML = '<div class="text-center text-xs text-slate-400 py-6">No saved conversations yet.</div>';
                            return;
                        }
                        list.forEach(s => {
                            const item = document.createElement('button');
                            item.type = 'button';
                            item.className = 'w-full text-left rounded-lg border border-slate-200 bg-white p-2.5 hover:border-green-300 hover:bg-green-50/50 transition';
                            item.innerHTML =
                                '<div class="flex items-center justify-between gap-2">' +
                                '<span class="text-xs font-bold text-slate-700 truncate">' + esc(s.title || 'New chat') + '</span>' +
                                '<span class="shrink-0 text-[10px] text-slate-400">' + (s.message_count || 0) + ' msgs</span></div>' +
                                '<div class="text-[11px] text-slate-500 mt-0.5 truncate">' + esc(s.last_message || 'No replies yet') + '</div>' +
                                '<div class="text-[10px] text-slate-400 mt-0.5">' + fmtDate(s.updated_at) + '</div>';
                            item.addEventListener('click', () => {
                                sessionId = Number(s.id);
                                switchTab('chat');
                                loadHistory(sessionId);
                            });
                            memList.appendChild(item);
                        });
                    });
            }

            async function send(q) {
                if (!q.trim()) return;
                appendUser(q.trim().replace(/\n/g, ' '));
                input.value = '';
                const typing = document.createElement('div');
                typing.className = 'flex gap-2 items-end';
                typing.innerHTML = '<div class="px-3 py-2 rounded-2xl rounded-bl-sm bg-white border border-slate-200 text-sm text-slate-400 shadow-sm">typing…</div>';
                msgs.appendChild(typing);
                msgs.scrollTop = msgs.scrollHeight;
                try {
                    const fd = new FormData();
                    fd.append('message', q);
                    if (sessionId) fd.append('session_id', sessionId);
                    const res = await fetch('index.php?module=Insights&action=chat', { method: 'POST', body: fd });
                    const json = await res.json();
                    typing.remove();
                    if (json.session_id) sessionId = Number(json.session_id);
                    const d = (json && json.data) || {};
                    appendBot(d.text || 'Sorry, something went wrong.', d.cards || []);
                } catch (e) {
                    typing.remove();
                    appendBot('Could not reach the assistant. Please try again.');
                }
            }

            if (fab) {
                fab.addEventListener('click', () => {
                    win.classList.remove('hidden');
                    win.classList.add('flex');
                    fab.classList.add('hidden');
                    switchTab('chat');
                    input.focus();
                });
            }
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    win.classList.add('hidden');
                    win.classList.remove('flex');
                    fab.classList.remove('hidden');
                });
            }
            if (newBtn) newBtn.addEventListener('click', (e) => { e.stopPropagation(); startNewChat(); switchTab('chat'); });
            if (document.getElementById('ff-memory-empty')) {
                document.getElementById('ff-memory-empty').addEventListener('click', () => { startNewChat(); switchTab('chat'); });
            }
            document.querySelectorAll('.ff-tab').forEach(t => t.addEventListener('click', () => switchTab(t.getAttribute('data-ff-tab'))));
            if (form) form.addEventListener('submit', (e) => { e.preventDefault(); send(input.value); });
            document.querySelectorAll('.ff-chip').forEach(btn => btn.addEventListener('click', () => send(btn.textContent)));
        })();
    </script>
</body>
</html>
