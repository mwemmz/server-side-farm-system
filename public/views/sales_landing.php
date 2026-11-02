<?php
$sales = is_array($data) ? $data : [];
$totalSales = 0.0;
$thisMonth = 0.0;
$customers = [];
$month = date('Y-m');

foreach ($sales as $sale) {
    $amount = (float) ($sale['amount'] ?? 0);
    $totalSales += $amount;
    if (substr((string) ($sale['sale_date'] ?? ''), 0, 7) === $month) {
        $thisMonth += $amount;
    }
    $customer = trim((string) ($sale['customer_name'] ?? ''));
    if ($customer !== '') {
        $customers[$customer] = true;
    }
}
?>
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Marketing &amp; sales</h1>
            <p class="text-sm text-slate-500 mt-1">Decide what to sell, record transactions, and track the money coming in.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="index.php?module=Sales&amp;action=add" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold">Record a sale</a>
            <a href="index.php?module=Market" class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold">Update market prices</a>
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="glass-card p-4"><div class="text-2xl font-extrabold text-emerald-700"><?php echo money($totalSales); ?></div><div class="text-[11px] uppercase tracking-wide text-slate-500 mt-1">Recorded sales</div></div>
        <div class="glass-card p-4"><div class="text-2xl font-extrabold text-blue-700"><?php echo money($thisMonth); ?></div><div class="text-[11px] uppercase tracking-wide text-slate-500 mt-1">This month</div></div>
        <div class="glass-card p-4"><div class="text-2xl font-extrabold text-slate-800"><?php echo count($sales); ?></div><div class="text-[11px] uppercase tracking-wide text-slate-500 mt-1">Transactions</div></div>
        <div class="glass-card p-4"><div class="text-2xl font-extrabold text-amber-700"><?php echo count($customers); ?></div><div class="text-[11px] uppercase tracking-wide text-slate-500 mt-1">Customers</div></div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <a href="index.php?module=Market" class="glass-card p-5 hover:border-green-300 transition">
            <div class="text-xs font-bold uppercase tracking-wide text-green-700">1. Check the market</div>
            <h2 class="text-lg font-bold text-slate-800 mt-2">Market prices</h2>
            <p class="text-sm text-slate-500 mt-1">Maintain current prices before deciding when and where to sell.</p>
        </a>
        <a href="index.php?module=Insights&amp;view=market" class="glass-card p-5 hover:border-green-300 transition">
            <div class="text-xs font-bold uppercase tracking-wide text-blue-700">2. Make a decision</div>
            <h2 class="text-lg font-bold text-slate-800 mt-2">Market analysis</h2>
            <p class="text-sm text-slate-500 mt-1">Compare history, seasonality, demand, and predicted harvest prices.</p>
        </a>
        <a href="index.php?module=Marketplace" class="glass-card p-5 hover:border-green-300 transition">
            <div class="text-xs font-bold uppercase tracking-wide text-amber-700">3. Find a buyer</div>
            <h2 class="text-lg font-bold text-slate-800 mt-2">Farmer marketplace</h2>
            <p class="text-sm text-slate-500 mt-1">List available produce or buy from another farm.</p>
        </a>
    </div>

    <div class="glass-card p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Recent sales</h2>
                <p class="text-xs text-slate-500 mt-1">The latest transactions recorded in the system.</p>
            </div>
            <a href="index.php?module=Sales&amp;action=manage" class="text-sm font-bold text-green-700 hover:text-green-800">View all</a>
        </div>
        <?php if (empty($sales)): ?>
            <p class="text-sm text-slate-500">No sales recorded yet. Start by recording your first sale.</p>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach (array_slice($sales, 0, 8) as $sale): ?>
                    <div class="py-3 flex items-center justify-between gap-3 text-sm">
                        <div>
                            <div class="font-semibold text-slate-800"><?php echo htmlspecialchars($sale['customer_name'] ?? 'Unknown customer'); ?></div>
                            <div class="text-xs text-slate-500"><?php echo htmlspecialchars($sale['crop_name'] ?? 'Product not specified'); ?> · <?php echo htmlspecialchars($sale['sale_date'] ?? ''); ?></div>
                        </div>
                        <div class="font-bold text-emerald-700"><?php echo money($sale['amount'] ?? 0); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
