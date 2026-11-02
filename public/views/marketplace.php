<?php
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
$success = SessionHelper::getFlash('success');
$error = SessionHelper::getFlash('error');
$listings = $data['listings'] ?? [];
$q = $data['q'] ?? '';
$cat = $data['category'] ?? '';
$uid = $data['userId'] ?? 0;
$myListings = $data['myListings'] ?? [];
$orders = $data['orders'] ?? [];
$sales = $data['sales'] ?? [];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Marketplace (Farmer-to-Farmer)</h1>
<?php if ($success): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold mb-3">Search produce</h2>
    <form method="get" action="index.php" class="flex flex-wrap gap-2">
        <input type="hidden" name="module" value="Marketplace">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Search items/produce…" class="flex-1 min-w-[200px] px-3 py-2 text-sm border rounded-lg">
        <input type="text" name="category" value="<?php echo htmlspecialchars($cat); ?>" placeholder="Category…" class="px-3 py-2 text-sm border rounded-lg">
        <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-bold">Search</button>
    </form>
</div>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold mb-3">Create a listing</h2>
    <form method="post" action="index.php?module=Marketplace&action=add" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
        <input type="text" name="item_name" required placeholder="Item (e.g. Maize)" class="px-3 py-2 text-sm border rounded-lg">
        <input type="text" name="category" placeholder="Category" class="px-3 py-2 text-sm border rounded-lg">
        <input type="number" name="quantity" required min="0.01" step="0.01" placeholder="Quantity" class="px-3 py-2 text-sm border rounded-lg">
        <input type="text" name="unit" required placeholder="Unit (kg, bags)" class="px-3 py-2 text-sm border rounded-lg">
        <input type="number" name="price_per_unit" required min="0.01" step="0.01" placeholder="Price per unit" class="px-3 py-2 text-sm border rounded-lg">
        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-bold">List item</button>
    </form>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-lg font-semibold mb-3">Available listings</h2>
    <?php if (empty($listings)): ?>
        <p class="text-sm text-slate-400">No active listings.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php foreach ($listings as $l): ?>
            <div class="bg-white/50 border border-white/60 rounded-xl p-3.5 text-sm">
                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($l['item_name']); ?></div>
                <div class="text-xs text-slate-500"><?php echo htmlspecialchars($l['category'] ?? ''); ?> · <?php echo htmlspecialchars($l['seller_farm'] ?? 'Farm'); ?></div>
                <div class="mt-1 text-slate-700"><?php echo rtrim(rtrim(number_format((float)$l['quantity'],2),'0'),'.'); ?> <?php echo htmlspecialchars($l['unit']); ?> @ K<?php echo number_format((float)$l['price_per_unit'],2); ?></div>
                <?php if ((int)$l['seller_user_id'] !== $uid): ?>
                <form method="post" action="index.php?module=Marketplace&action=buy" class="mt-2 flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>">
                    <input type="hidden" name="listing_id" value="<?php echo (int)$l['id']; ?>">
                    <input type="number" name="quantity" min="0.01" step="0.01" max="<?php echo (float)$l['quantity']; ?>" value="1" class="w-20 px-2 py-1 text-sm border rounded-lg">
                    <button type="submit" class="px-3 py-1 rounded-lg bg-blue-600 text-white text-xs font-bold">Buy</button>
                </form>
                <?php else: ?>
                <div class="mt-2 text-xs text-slate-400">Your listing</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
<div class="glass-card p-4 sm:p-6">
    <h2 class="text-lg font-semibold mb-3">My selling activity</h2>
    <?php if (!$myListings): ?><p class="text-sm text-slate-400">You have no listings yet.</p><?php endif; ?>
    <?php foreach ($myListings as $l): ?><div class="border-b border-slate-100 py-3 text-sm"><div class="flex justify-between"><b><?php echo htmlspecialchars($l['item_name']); ?></b><span class="text-xs"><?php echo htmlspecialchars($l['status']); ?></span></div><div class="text-xs text-slate-500"><?php echo htmlspecialchars($l['quantity'].' '.$l['unit']); ?> @ K<?php echo number_format((float)$l['price_per_unit'],2); ?></div><?php if ($l['status'] === 'active'): ?><form method="post" action="index.php?module=Marketplace&action=edit" class="flex gap-2 mt-2"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>"><input type="hidden" name="listing_id" value="<?php echo (int)$l['id']; ?>"><input type="number" name="quantity" min="0.01" step="0.01" value="<?php echo (float)$l['quantity']; ?>" class="w-24 border rounded px-2 py-1 text-xs"><input type="number" name="price_per_unit" min="0.01" step="0.01" value="<?php echo (float)$l['price_per_unit']; ?>" class="w-24 border rounded px-2 py-1 text-xs"><button class="text-xs font-bold text-green-700">Save</button></form><form method="post" action="index.php?module=Marketplace&action=cancel" class="mt-1"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SessionHelper::csrfToken()); ?>"><input type="hidden" name="listing_id" value="<?php echo (int)$l['id']; ?>"><button class="text-xs text-red-700">Cancel listing</button></form><?php endif; ?></div><?php endforeach; ?>
</div>
<div class="glass-card p-4 sm:p-6">
    <h2 class="text-lg font-semibold mb-3">Buying activity</h2>
    <?php if (!$orders): ?><p class="text-sm text-slate-400">You have not bought anything yet.</p><?php endif; ?>
    <?php foreach ($orders as $o): ?><div class="border-b border-slate-100 py-3 text-sm"><b><?php echo htmlspecialchars($o['item_name']); ?></b><div class="text-xs text-slate-500"><?php echo htmlspecialchars($o['quantity'].' '.$o['unit']); ?> · K<?php echo number_format((float)$o['total_amount'],2); ?> · <?php echo htmlspecialchars($o['status']); ?></div></div><?php endforeach; ?>
    <h3 class="font-semibold mt-5 mb-2">Sales received</h3>
    <?php if (!$sales): ?><p class="text-sm text-slate-400">No completed sales yet.</p><?php endif; ?>
    <?php foreach ($sales as $o): ?><div class="border-b border-slate-100 py-2 text-sm"><b><?php echo htmlspecialchars($o['item_name']); ?></b><div class="text-xs text-slate-500"><?php echo htmlspecialchars($o['quantity'].' '.$o['unit']); ?> · K<?php echo number_format((float)$o['total_amount'],2); ?> · <?php echo htmlspecialchars($o['buyer_name'] ?? 'Buyer'); ?></div></div><?php endforeach; ?>
</div>
</div>
<p class="text-xs text-slate-400 mt-4">Buying/selling posts finance records (expense for buyer, income for seller) and reduces listing quantity.</p>