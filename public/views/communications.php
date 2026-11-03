<?php
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
$success = SessionHelper::getFlash('success');
$error   = SessionHelper::getFlash('error');
$convs   = $data['conversations'] ?? [];
$msgs    = $data['messages'] ?? [];
$activeId = $data['activeId'] ?? null;
$uid     = $data['userId'] ?? 0;
$role    = $data['role'] ?? 'worker';
$scope   = $data['scope'] ?? 'intranet';
$isExtranet = ($scope === 'extranet');

$intranetUrl  = 'index.php?module=Communications&action=index';
$extranetUrl  = 'index.php?module=Communications&action=extranet';
$createAction = $isExtranet ? 'createExtranet' : 'create';
$backParam    = $isExtranet ? 'index.php?module=Communications&action=extranet' : 'index.php?module=Communications&action=view';
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-green-800">
            <?php echo $isExtranet ? '🌐 Extranet — Farm-to-Farm Messaging' : '🏢 Intranet — Farm Staff Messaging'; ?>
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            <?php echo $isExtranet
                ? 'Secure messaging with connected farms. Requires an accepted farm connection.'
                : 'Private messaging between your farm\'s staff members. Only visible within your farm.'; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?php echo htmlspecialchars($intranetUrl); ?>"
           class="px-4 py-2 rounded-xl text-sm font-bold transition <?php echo !$isExtranet ? 'bg-gradient-to-r from-green-600 to-emerald-600 text-white shadow' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'; ?>">
            🏢 Intranet
        </a>
        <a href="<?php echo htmlspecialchars($extranetUrl); ?>"
           class="px-4 py-2 rounded-xl text-sm font-bold transition <?php echo $isExtranet ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'; ?>">
            🌐 Extranet
        </a>
        <?php if ($isExtranet): ?>
        <a href="index.php?module=Connections" class="px-4 py-2 rounded-xl text-sm font-bold bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 transition">
            🔗 Manage Connections
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($success): ?>
<div class="bg-emerald-50 border border-emerald-300 text-emerald-800 px-4 py-3 rounded-xl mb-5 text-sm flex items-center gap-2">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    <?php echo htmlspecialchars($success); ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-red-50 border border-red-300 text-red-800 px-4 py-3 rounded-xl mb-5 text-sm">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<?php if ($isExtranet): ?>
<!-- Extranet notice -->
<div class="glass-card bg-blue-50/60 border border-blue-200 px-4 py-3 mb-5 text-sm text-blue-800 flex items-start gap-2 rounded-xl">
    <span class="text-lg shrink-0">ℹ️</span>
    <span>Extranet conversations are only available between farms with an <strong>accepted connection</strong>.
    You can message any member of a connected farm.
    <a href="index.php?module=Connections" class="font-bold underline hover:no-underline ml-1">View connections →</a></span>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Sidebar: conversation list + new conversation form -->
    <div class="glass-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-slate-800">
                <?php echo $isExtranet ? 'Farm-to-Farm Chats' : 'Conversations'; ?>
            </h2>
            <span class="text-xs text-slate-400"><?php echo count($convs); ?> total</span>
        </div>

        <ul class="space-y-1.5 mb-5 max-h-80 overflow-y-auto">
            <?php foreach ($convs as $c): ?>
            <li>
                <a href="index.php?module=Communications&action=<?php echo $isExtranet ? 'extranet' : 'view'; ?>&id=<?php echo (int)$c['id']; ?>"
                   class="flex items-start gap-2 px-3 py-2.5 rounded-xl text-sm transition
                          <?php echo $activeId == $c['id']
                              ? ($isExtranet ? 'bg-blue-100 text-blue-900' : 'bg-green-100 text-green-900')
                              : 'bg-white/50 hover:bg-white/80 text-slate-700'; ?>">
                    <div class="w-7 h-7 rounded-lg <?php echo $isExtranet ? 'bg-blue-200 text-blue-700' : 'bg-green-200 text-green-700'; ?> flex items-center justify-center text-xs font-bold shrink-0 mt-0.5">
                        <?php echo $isExtranet ? '🌐' : '💬'; ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold truncate">
                            <?php echo htmlspecialchars($c['title'] ?: ('Conversation #' . $c['id'])); ?>
                        </div>
                        <div class="text-[10px] text-slate-400 flex items-center gap-1">
                            <span><?php echo htmlspecialchars(ucfirst($c['kind'])); ?></span>
                            <?php if (!empty($c['created_at'])): ?>
                            · <span><?php echo date('M j', strtotime($c['created_at'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            </li>
            <?php endforeach; ?>
            <?php if (empty($convs)): ?>
            <li class="text-sm text-slate-400 text-center py-4">No conversations yet.</li>
            <?php endif; ?>
        </ul>

        <!-- New conversation form -->
        <details class="group" <?php echo empty($convs) ? 'open' : ''; ?>>
            <summary class="text-sm font-bold text-slate-700 cursor-pointer flex items-center gap-1.5 hover:text-green-700 transition list-none">
                <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <?php echo $isExtranet ? 'Start Farm-to-Farm Chat' : 'New Conversation'; ?>
            </summary>
            <form method="post" action="index.php?module=Communications&action=<?php echo htmlspecialchars($createAction); ?>"
                  class="mt-3 space-y-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Title</label>
                    <input type="text" name="title"
                           placeholder="<?php echo $isExtranet ? 'e.g. Seed purchase inquiry' : 'e.g. Irrigation schedule'; ?>"
                           class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                </div>
                <button type="submit"
                        class="w-full py-2 rounded-xl text-sm font-bold text-white transition
                               <?php echo $isExtranet ? 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:opacity-90' : 'bg-gradient-to-r from-green-600 to-emerald-600 hover:opacity-90'; ?>">
                    <?php echo $isExtranet ? '+ Start Chat' : '+ New Conversation'; ?>
                </button>
            </form>
        </details>

        <!-- Broadcast (intranet, managers+ only) -->
        <?php if (!$isExtranet && in_array($role, ['admin', 'manager', 'farm-owner'], true)): ?>
        <details class="mt-4">
            <summary class="text-sm font-bold text-amber-700 cursor-pointer flex items-center gap-1.5 hover:text-amber-800 list-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 0 1-3.417.592l-2.147-6.15M18 13a3 3 0 1 0 0-6M5.436 13.683A4.001 4.001 0 0 0 7 8H5"/></svg>
                Send Broadcast
            </summary>
            <form method="post" action="index.php?module=Communications&action=broadcast" class="space-y-3 mt-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="text" name="title" placeholder="Broadcast title" required
                       class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-amber-400 outline-none">
                <textarea name="body" rows="3" placeholder="Message for all farm members…" required
                          class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-amber-400 outline-none resize-none"></textarea>
                <button type="submit"
                        class="w-full py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold transition">
                    📢 Send to All Staff
                </button>
            </form>
        </details>
        <?php endif; ?>
    </div>

    <!-- Message thread -->
    <div class="lg:col-span-2 glass-card p-5 flex flex-col" style="min-height:520px;">
        <?php if ($activeId): ?>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-slate-800">
                    <?php
                    foreach ($convs as $c) {
                        if ((int)$c['id'] === (int)$activeId) {
                            echo htmlspecialchars($c['title'] ?: 'Conversation #' . $c['id']);
                            break;
                        }
                    }
                    ?>
                </h2>
                <span class="text-xs text-slate-400"><?php echo count($msgs); ?> message<?php echo count($msgs) !== 1 ? 's' : ''; ?></span>
            </div>

            <!-- Messages -->
            <div id="msg-thread" class="flex-1 overflow-y-auto space-y-3 mb-4 max-h-96 pr-1">
                <?php if (empty($msgs)): ?>
                    <p class="text-sm text-slate-400 text-center py-8">No messages yet — say hello!</p>
                <?php else: ?>
                    <?php foreach ($msgs as $m): ?>
                    <?php $isOwn = ((int)($m['sender_id'] ?? 0) === $uid); ?>
                    <div class="flex <?php echo $isOwn ? 'justify-end' : 'justify-start'; ?> gap-2">
                        <?php if (!$isOwn): ?>
                        <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0 mt-0.5">
                            <?php echo strtoupper(substr($m['sender_name'] ?? '?', 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <div class="max-w-xs lg:max-w-sm">
                            <div class="rounded-2xl px-4 py-2.5 text-sm
                                <?php echo $isOwn
                                    ? 'bg-gradient-to-br from-green-600 to-emerald-600 text-white rounded-br-sm'
                                    : 'bg-white border border-slate-200 text-slate-800 rounded-bl-sm'; ?>">
                                <?php echo nl2br(htmlspecialchars($m['body'] ?? '')); ?>
                            </div>
                            <div class="text-[10px] text-slate-400 mt-1 <?php echo $isOwn ? 'text-right' : 'text-left'; ?>">
                                <?php echo htmlspecialchars($m['sender_name'] ?? 'Unknown'); ?>
                                · <?php echo date('H:i', strtotime($m['created_at'] ?? 'now')); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Message input -->
            <form method="post" action="index.php?module=Communications&action=post" class="flex gap-2 mt-auto">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="hidden" name="conversation_id" value="<?php echo (int)$activeId; ?>">
                <input type="hidden" name="back" value="<?php echo htmlspecialchars($isExtranet ? 'index.php?module=Communications&action=extranet' : 'index.php?module=Communications&action=view'); ?>">
                <input type="text" name="body" id="msg-input" placeholder="Type a message…" required autocomplete="off"
                       class="flex-1 px-4 py-2.5 text-sm border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-green-400 outline-none">
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl text-sm font-bold text-white transition
                               <?php echo $isExtranet ? 'bg-blue-600 hover:bg-blue-700' : 'bg-green-600 hover:bg-green-700'; ?>">
                    Send
                </button>
            </form>
            <script>
            // Auto-scroll to bottom and focus input
            (function() {
                var thread = document.getElementById('msg-thread');
                if (thread) thread.scrollTop = thread.scrollHeight;
                var inp = document.getElementById('msg-input');
                if (inp) inp.focus();
            })();
            </script>
        <?php else: ?>
            <div class="flex-1 flex flex-col items-center justify-center text-center text-slate-400 gap-3 py-12">
                <div class="text-5xl"><?php echo $isExtranet ? '🌐' : '💬'; ?></div>
                <div class="font-semibold text-slate-600">Select a conversation to view messages</div>
                <div class="text-sm max-w-xs">
                    <?php if ($isExtranet): ?>
                        Start by searching for farms to connect with, then begin a farm-to-farm conversation.
                    <?php else: ?>
                        Create a conversation on the left to start messaging your farm's team.
                    <?php endif; ?>
                </div>
                <?php if ($isExtranet): ?>
                <a href="index.php?module=Connections"
                   class="mt-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 transition">
                    Find farms to connect with →
                </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
