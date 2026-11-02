<?php
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
$success = SessionHelper::getFlash('success');
$error = SessionHelper::getFlash('error');
$convs = $data['conversations'] ?? [];
$msgs = $data['messages'] ?? [];
$activeId = $data['activeId'] ?? null;
$uid = $data['userId'] ?? 0;
$role = $data['role'] ?? 'worker';
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Communication Hub (Intranet)</h1>
<?php if ($success): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="glass-card p-4 sm:p-6">
        <h2 class="text-lg font-semibold mb-3">Conversations</h2>
        <ul class="space-y-2 mb-4">
            <?php foreach ($convs as $c): ?>
            <li>
                <a href="index.php?module=Communications&action=view&id=<?php echo (int)$c['id']; ?>" class="block px-3 py-2 rounded-lg <?php echo $activeId == $c['id'] ? 'bg-blue-100 text-blue-800' : 'bg-white/50 hover:bg-white/80'; ?> text-sm font-semibold">
                    <?php echo htmlspecialchars($c['title'] ?: ('Conversation #' . $c['id'])); ?>
                    <span class="text-[10px] text-slate-400 ml-1">(<?php echo htmlspecialchars($c['kind']); ?>)</span>
                </a>
            </li>
            <?php endforeach; ?>
            <?php if (empty($convs)): ?><li class="text-sm text-slate-400">No conversations yet.</li><?php endif; ?>
        </ul>
        <form method="post" action="index.php?module=Communications&action=create" class="space-y-2">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
            <input type="text" name="title" placeholder="New conversation title" class="w-full px-3 py-2 text-sm border rounded-lg">
            <button type="submit" class="w-full px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">New conversation</button>
        </form>
        <?php if (in_array($role, ['admin','manager','farm-owner'], true)): ?>
        <details class="mt-4">
            <summary class="text-sm font-semibold text-amber-700 cursor-pointer">Send broadcast</summary>
            <form method="post" action="index.php?module=Communications&action=broadcast" class="space-y-2 mt-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="text" name="title" placeholder="Broadcast title" class="w-full px-3 py-2 text-sm border rounded-lg" required>
                <textarea name="body" placeholder="Message" class="w-full px-3 py-2 text-sm border rounded-lg" required></textarea>
                <button type="submit" class="w-full px-3 py-2 rounded-lg bg-amber-500 text-white text-sm font-bold hover:bg-amber-600">Send broadcast</button>
            </form>
        </details>
        <?php endif; ?>
    </div>
    <div class="lg:col-span-2 glass-card p-4 sm:p-6">
        <?php if ($activeId): ?>
            <h2 class="text-lg font-semibold mb-3">Messages</h2>
            <div class="space-y-2 mb-4 max-h-96 overflow-y-auto">
                <?php foreach ($msgs as $m): ?>
                <div class="bg-white/50 border border-white/60 rounded-xl px-3.5 py-2.5 text-sm">
                    <div class="text-[10px] font-bold uppercase text-slate-400"><?php echo htmlspecialchars($m['sender_name'] ?? 'system'); ?> · <?php echo htmlspecialchars($m['created_at'] ?? ''); ?></div>
                    <div class="text-slate-700"><?php echo nl2br(htmlspecialchars($m['body'] ?? '')); ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($msgs)): ?><p class="text-sm text-slate-400">No messages yet.</p><?php endif; ?>
            </div>
            <form method="post" action="index.php?module=Communications&action=post" class="flex gap-2">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                <input type="hidden" name="conversation_id" value="<?php echo (int)$activeId; ?>">
                <input type="text" name="body" placeholder="Type a message…" class="flex-1 px-3 py-2 text-sm border rounded-lg" required>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-700">Send</button>
            </form>
        <?php else: ?>
            <p class="text-sm text-slate-400">Select a conversation to view messages.</p>
        <?php endif; ?>
    </div>
</div>
<p class="text-xs text-slate-400 mt-4">Intranet only — a user can only access conversations whose farm matches their own farm. Admins cannot read another farm's intranet.</p>