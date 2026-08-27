<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FFMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            background-color: #0f172a;
            background-image:
                radial-gradient(at 15% 0%, rgba(34, 197, 94, 0.18) 0px, transparent 50%),
                radial-gradient(at 85% 8%, rgba(245, 158, 11, 0.12) 0px, transparent 45%),
                radial-gradient(at 55% 100%, rgba(16, 185, 129, 0.14) 0px, transparent 50%);
            background-attachment: fixed;
        }
        .glass-card {
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0.04));
            backdrop-filter: blur(16px) saturate(1.4);
            -webkit-backdrop-filter: blur(16px) saturate(1.4);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.35);
        }
        input {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #fff;
        }
        input::placeholder { color: rgba(255, 255, 255, 0.4); }
        input:focus { outline: none; border-color: rgba(34, 197, 94, 0.6); box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <!-- Decorative blobs -->
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden>
        <div class="absolute -top-24 -left-24 w-96 h-96 rounded-full bg-green-400/15 blur-3xl"></div>
        <div class="absolute bottom-0 -right-32 w-[28rem] h-[28rem] rounded-full bg-amber-400/10 blur-3xl"></div>
    </div>

    <div class="glass-card rounded-3xl p-8 w-full max-w-md relative z-10 animate-fade-in-up">
        <!-- Brand -->
        <div class="flex items-center gap-3 mb-8">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-900/40">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div>
                <div class="text-xl font-extrabold tracking-tight text-white leading-none">FFMS</div>
                <div class="text-[11px] text-slate-400 mt-1">Intelligent Farm Management System</div>
            </div>
        </div>

        <h2 class="text-2xl font-bold text-white mb-6">Sign in to your account</h2>

        <?php if ($loginError): ?>
            <div class="mb-4 rounded-xl bg-rose-500/15 border border-rose-500/40 px-4 py-3 text-sm text-rose-200 flex items-center gap-2.5" role="alert">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>
                <span><?php echo htmlspecialchars($loginError); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php?module=Security&action=login" class="space-y-4">
            <div>
                <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Username</label>
                <input id="username" name="username" type="text" required autofocus placeholder="admin"
                       class="w-full rounded-xl px-4 py-3 text-sm font-medium transition-all duration-200">
            </div>
            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Password</label>
                <input id="password" name="password" type="password" required placeholder="••••••••"
                       class="w-full rounded-xl px-4 py-3 text-sm font-medium transition-all duration-200">
            </div>
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white transition-all duration-200 bg-gradient-to-r from-green-600 to-emerald-600 shadow-lg shadow-green-900/40 hover:from-green-500 hover:to-emerald-500 active:scale-[0.98]">
                Sign In
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </button>
        </form>

        <div class="mt-6 text-center text-[11px] text-slate-500">
            Default test account: <span class="text-slate-300 font-semibold">admin</span> / <span class="text-slate-300 font-semibold">admin123</span>
        </div>
    </div>
    <style>
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade-in-up { animation: fadeInUp 0.4s ease-out both; }
    </style>
</body>
</html>