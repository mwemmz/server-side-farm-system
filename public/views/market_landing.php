<?php
$prices = is_array($data) ? $data : [];
$latest = [];
$totalPrice = 0.0;

foreach ($prices as $price) {
    $crop = trim((string) ($price['crop_name'] ?? ''));
    if ($crop === '') {
        continue;
    }
    $value = (float) ($price['price'] ?? 0);
    $totalPrice += $value;
    if (!isset($latest[$crop])) {
        $latest[$crop] = $price;
    }
}
$averagePrice = count($prices) > 0 ? $totalPrice / count($prices) : 0;
?>
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Market intelligence</h1>
            <p class="text-sm text-slate-500 mt-1">Use current prices and history to choose the right crop, timing, and buyer.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="index.php?module=Market&amp;action=add" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold">Add price update</a>
            <a href="index.php?module=Insights&amp;view=market" class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold">Open analysis</a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="glass-card p-4"><div class="text-2xl font-extrabold text-emerald-700"><?php echo count($latest); ?></div><div class="text-[11px] uppercase tracking-wide text-slate-500 mt-1">Crops tracked</div></div>
        <div class="glass-card p-4"><div class="text-2xl font-extrabold text-slate-800"><?php echo money($averagePrice); ?></div><div class="text-[11px] uppercase tracking-wide text-slate-500 mt-1">Average price/kg</div></div>
        <div class="glass-card p-4"><div class="text-2xl font-extrabold text-blue-700"><?php echo count($prices); ?></div><div class="text-[11px] uppercase tracking-wide text-slate-500 mt-1">Price updates</div></div>
        <div class="glass-card p-4"><div class="text-2xl font-extrabold text-amber-700">3</div><div class="text-[11px] uppercase tracking-wide text-slate-500 mt-1">Next steps</div></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="index.php?module=Insights&amp;view=market" class="glass-card p-5 hover:border-green-300 transition">
            <div class="text-xs font-bold uppercase tracking-wide text-green-700">Plan</div>
            <h2 class="text-lg font-bold text-slate-800 mt-2">Analyse a crop</h2>
            <p class="text-sm text-slate-500 mt-1">See seasonal patterns and expected prices before planting.</p>
        </a>
        <a href="index.php?module=Sales&amp;action=add" class="glass-card p-5 hover:border-green-300 transition">
            <div class="text-xs font-bold uppercase tracking-wide text-blue-700">Sell</div>
            <h2 class="text-lg font-bold text-slate-800 mt-2">Record a sale</h2>
            <p class="text-sm text-slate-500 mt-1">Capture the customer, product, amount, and payment details.</p>
        </a>
        <a href="index.php?module=Marketplace" class="glass-card p-5 hover:border-green-300 transition">
            <div class="text-xs font-bold uppercase tracking-wide text-amber-700">Connect</div>
            <h2 class="text-lg font-bold text-slate-800 mt-2">Browse marketplace</h2>
            <p class="text-sm text-slate-500 mt-1">Find buyers and sellers beyond your existing customer list.</p>
        </a>
    </div>

    <div class="glass-card p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Latest prices by crop</h2>
                <p class="text-xs text-slate-500 mt-1">Add a new update when you receive a reliable market quote.</p>
            </div>
            <a href="index.php?module=Market&amp;action=manage" class="text-sm font-bold text-green-700 hover:text-green-800">Manage updates</a>
        </div>
        <?php if (empty($latest)): ?>
            <p class="text-sm text-slate-500">No market prices recorded yet. Add the first update to unlock analysis.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($latest as $price): ?>
                    <div class="rounded-xl border border-slate-200 bg-white/60 p-4">
                        <div class="font-bold text-slate-800"><?php echo htmlspecialchars($price['crop_name']); ?></div>
                        <div class="text-xl font-extrabold text-emerald-700 mt-1"><?php echo money($price['price']); ?><span class="text-xs font-medium text-slate-400"> / kg</span></div>
                        <div class="text-xs text-slate-500 mt-1">Updated <?php echo htmlspecialchars($price['market_date'] ?? ''); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
