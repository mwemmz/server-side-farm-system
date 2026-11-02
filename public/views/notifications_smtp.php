<?php
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errorMessage   = SessionHelper::getFlash('error');

$config     = $data['config'] ?? [];
$errors     = $data['errors'] ?? [];
$testResult = $data['testResult'] ?? null;

$currentUserEmail = $_SESSION['email'] ?? '';
$isEnabled = !empty($config['enabled']);
$isConfigured = !empty($config['host']) && (!empty($config['user']) || (int)($config['port'] ?? 0) === 25 || (int)($config['port'] ?? 0) === 2525);
?>

<div class="mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-green-800">SMTP Notification Settings</h1>
            <p class="text-sm text-slate-500 mt-1">Configure SMTP email delivery for automated farm alerts, rule triggers, and critical events.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="index.php?module=Notifications&action=manage" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-slate-200 text-slate-700 text-xs font-bold shadow-sm hover:bg-slate-50 transition-colors">
                &larr; Notifications Center
            </a>
            <a href="index.php?module=Notifications&action=rules" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-xs font-bold shadow-md hover:shadow-emerald-700/40 transition-shadow">
                Alert Rules
            </a>
        </div>
    </div>
</div>

<?php if ($successMessage): ?>
    <div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-xl mb-6 text-sm flex items-start gap-2.5 shadow-sm">
        <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <div><?php echo htmlspecialchars($successMessage); ?></div>
    </div>
<?php endif; ?>

<?php if ($errorMessage || !empty($errors['general'])): ?>
    <div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-xl mb-6 text-sm flex items-start gap-2.5 shadow-sm">
        <svg class="w-5 h-5 text-red-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div><?php echo htmlspecialchars($errorMessage ?: $errors['general']); ?></div>
    </div>
<?php endif; ?>

<!-- Status Banner -->
<div class="glass-card p-5 mb-6 border border-white/60">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl <?php echo $isEnabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'; ?> flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-bold text-slate-800">SMTP Notification Engine</h2>
                    <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full <?php echo $isEnabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'; ?>">
                        <?php echo $isEnabled ? 'Active' : 'Disabled'; ?>
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    <?php if ($isConfigured): ?>
                        Connected to <strong class="text-slate-700"><?php echo htmlspecialchars($config['host']); ?>:<?php echo htmlspecialchars($config['port']); ?></strong> (<?php echo htmlspecialchars($config['secure']); ?>)
                    <?php else: ?>
                        Not yet configured. Enter your server credentials below to activate email alerts.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <?php if (!empty($config['recipient'])): ?>
            <div class="text-right text-xs">
                <span class="text-slate-400">Default Alert Recipient:</span>
                <span class="font-bold text-slate-700 block"><?php echo htmlspecialchars($config['recipient']); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- SMTP Settings Form -->
    <div class="lg:col-span-2 glass-card p-6">
        <h2 class="text-lg font-bold text-slate-800 mb-1">Server Credentials &amp; Routing</h2>
        <p class="text-xs text-slate-500 mb-5">These settings are securely stored in your farm system and used for all automated operational mailings.</p>

        <form method="POST" action="index.php?module=Notifications&action=smtp" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
            <input type="hidden" name="action_type" value="save">

            <div class="flex items-center gap-3 p-3.5 bg-slate-50/70 border border-slate-200/80 rounded-xl mb-4">
                <input type="checkbox" id="smtp_enabled" name="smtp_enabled" value="1" <?php echo $isEnabled ? 'checked' : ''; ?> class="w-4 h-4 text-green-600 rounded border-slate-300 focus:ring-green-500">
                <label for="smtp_enabled" class="text-sm font-semibold text-slate-700 cursor-pointer">
                    Enable SMTP Email Dispatch for System Notifications
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">SMTP Host *</label>
                    <input type="text" name="smtp_host" required value="<?php echo htmlspecialchars($config['host'] ?? ''); ?>" placeholder="e.g. smtp.gmail.com" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-white/80 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Port *</label>
                    <input type="number" name="smtp_port" required value="<?php echo htmlspecialchars($config['port'] ?? 587); ?>" placeholder="587" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-white/80 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Encryption *</label>
                    <select name="smtp_secure" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-white/80 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                        <option value="tls" <?php echo ($config['secure'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS / STARTTLS (Port 587 - Recommended)</option>
                        <option value="ssl" <?php echo ($config['secure'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL (Port 465)</option>
                        <option value="none" <?php echo ($config['secure'] ?? '') === 'none' ? 'selected' : ''; ?>>None / Plain (Port 25 or Local Relay)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">SMTP Username / Email</label>
                    <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($config['user'] ?? ''); ?>" placeholder="e.g. farm.alerts@gmail.com" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-white/80 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">SMTP Password / App Password</label>
                    <input type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?php echo !empty($config['pass']) ? '•••••••••••• (Leave blank to keep)' : 'Enter SMTP password'; ?>" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-white/80 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                    <span class="text-[11px] text-slate-400 mt-1 block">For Gmail, use a 16-character Google App Password.</span>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">From Address</label>
                    <input type="email" name="smtp_from" value="<?php echo htmlspecialchars($config['from'] ?? ''); ?>" placeholder="e.g. alerts@myfarm.com" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-white/80 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Sender Display Name</label>
                    <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($config['from_name'] ?? 'FFMS Alerts'); ?>" placeholder="e.g. FFMS Farm Alerts" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-white/80 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Default Alert Recipient Email</label>
                    <input type="email" name="smtp_recipient" value="<?php echo htmlspecialchars($config['recipient'] ?? ''); ?>" placeholder="e.g. manager@myfarm.com" class="w-full px-3.5 py-2.5 text-sm border border-slate-200 rounded-xl bg-white/80 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                    <span class="text-[11px] text-slate-400 mt-1 block">Receives automated rule triggers and low-stock warnings.</span>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-end">
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-all">
                    Save SMTP Configuration
                </button>
            </div>
        </form>
    </div>

    <!-- Test & Diagnosis Section -->
    <div class="space-y-6">
        <div class="glass-card p-6">
            <h2 class="text-lg font-bold text-slate-800 mb-1">Verify SMTP Connection</h2>
            <p class="text-xs text-slate-500 mb-4">Send an immediate live test email to verify credentials, TLS handshake, and deliverability.</p>

            <form method="POST" action="index.php?module=Notifications&action=smtp" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="hidden" name="action_type" value="test">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Recipient Email *</label>
                    <input type="email" name="test_recipient" required value="<?php echo htmlspecialchars($_POST['test_recipient'] ?? ($config['recipient'] ?: $currentUserEmail)); ?>" placeholder="your-email@domain.com" class="w-full px-3.5 py-2 text-sm border border-slate-200 rounded-xl bg-white/80 focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none transition">
                    <?php if (!empty($errors['test_recipient'])): ?>
                        <span class="text-xs text-red-600 mt-1 block"><?php echo htmlspecialchars($errors['test_recipient']); ?></span>
                    <?php endif; ?>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold shadow transition flex items-center justify-center gap-2">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Send Test Email &amp; Diagnostics
                </button>
            </form>

            <?php if ($testResult): ?>
                <div class="mt-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Test Result:</span>
                        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full <?php echo $testResult['success'] ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo $testResult['success'] ? '✓ Success' : '✕ Failed'; ?>
                        </span>
                    </div>

                    <?php if (!$testResult['success']): ?>
                        <p class="text-xs text-red-700 bg-red-50 p-2.5 rounded-lg border border-red-200 mb-2 font-mono break-all">
                            <?php echo htmlspecialchars($testResult['error'] ?? 'Connection error'); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-xs text-emerald-700 bg-emerald-50 p-2.5 rounded-lg border border-emerald-200 mb-2">
                            ✓ Message queued and accepted by the SMTP server. Check your inbox (or spam folder).
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($testResult['transcript'])): ?>
                        <details class="text-[11px] text-slate-600 mt-2">
                            <summary class="cursor-pointer font-bold text-slate-700 hover:text-green-700 mb-1">
                                View SMTP Server Protocol Transcript
                            </summary>
                            <pre class="bg-slate-900 text-emerald-400 p-3 rounded-lg overflow-x-auto text-[10px] font-mono leading-relaxed mt-1 max-h-48"><?php
                                foreach ($testResult['transcript'] as $line) {
                                    echo htmlspecialchars($line) . "\n";
                                }
                            ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="glass-card p-5 text-xs text-slate-600 space-y-3">
            <h3 class="font-bold text-slate-800 uppercase tracking-wider text-[11px] flex items-center gap-1.5">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Quick Setup Guide
            </h3>
            <p><strong>Gmail:</strong> Enable 2-Step Verification in Google Account, then generate an <em>App password</em> under Security. Use <code>smtp.gmail.com</code>, Port <code>587</code>, TLS.</p>
            <p><strong>Microsoft 365 / Outlook:</strong> Host <code>smtp.office365.com</code>, Port <code>587</code>, TLS, with your work email and password.</p>
            <p><strong>SendGrid / Brevo:</strong> Host <code>smtp.sendgrid.net</code> or <code>smtp-relay.brevo.com</code>, Port <code>587</code>, user <code>apikey</code> with your API key as password.</p>
        </div>
    </div>
</div>
