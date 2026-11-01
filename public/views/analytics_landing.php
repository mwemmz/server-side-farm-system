<?php
/**
 * Analytics landing — enterprise profitability, yields, equipment cost,
 * inventory value and market prices for a selected date range.
 *
 * Money is only rendered for roles that are allowed to see finance figures;
 * everyone else still gets the production view.
 */
require_once __DIR__ . '/../../src/Intelligence/AnalyticsService.php';
require_once __DIR__ . '/../../src/Helpers/RoleHelper.php';

$analyticsRole = RoleHelper::normalise($_SESSION['role'] ?? 'worker');
$analyticsMoney = RoleHelper::seesFinance($analyticsRole);
$analyticsFrom = trim((string) ($_GET['from'] ?? ''));
$analyticsTo = trim((string) ($_GET['to'] ?? ''));

try {
    $analytics = new AnalyticsService($pdo, $analyticsFrom ?: null, $analyticsTo ?: null);
    $overview = $analytics->overview();
    $cropRows = $analytics->cropEnterprise();
    $livestockRows = $analytics->livestockEnterprise();
    $equipmentRows = $analytics->equipmentCosts();
    $yieldRows = $analytics->yieldRows();
    $inventoryRows = $analytics->inventoryRows();
    $marketRows = $analytics->marketPrices();
    $expiringRows = $analytics->expiringInventory();
} catch (\Throwable $e) {
    error_log('Analytics landing failed: ' . $e->getMessage());
    $analytics = null;
    $overview = ['from' => date('Y-01-01'), 'to' => date('Y-m-d'), 'revenue' => 0, 'expenses' => 0,
        'profit' => 0, 'margin_pct' => 0, 'roi_pct' => 0, 'area_ha' => 0, 'harvest_kg' => 0,
        'cost_per_ha' => null, 'cost_per_kg' => null, 'active_crops' => 0, 'livestock' => 0,
        'inventory_value' => 0];
    $cropRows = $livestockRows = $equipmentRows = $yieldRows = $inventoryRows = $marketRows = $expiringRows = [];
}

$fmtNum = function ($v, $dp = 2) {
    if ($v === null || $v === '') return '—';
    return number_format((float) $v, $dp);
};
$fmtMoney = function ($v) use ($fmtNum) { return 'K' . $fmtNum($v); };
$fmtQty = function ($v) use ($fmtNum) {
    if ($v === null || $v === '') return '—';
    $n = (float) $v;
    $dp = (floor($n) == $n) ? 0 : 2;
    return number_format($n, $dp);
};
$trend = function ($price, $prev) {
    if ($price === null || $prev === null || (float) $prev <= 0) return ['flat', '—'];
    $pct = (((float) $price - (float) $prev) / (float) $prev) * 100;
    if ($pct > 0.01) return ['up', '+' . number_format($pct, 1) . '%'];
    if ($pct < -0.01) return ['down', number_format($pct, 1) . '%'];
    return ['flat', '0.0%'];
};
?>
<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4 animate-fade-in">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Intelligence</div>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Farm Analytics</h1>
            <p class="text-sm text-slate-500 mt-1.5 max-w-3xl">
                Enterprise profitability, yields, equipment cost and market signals for
                <?php echo htmlspecialchars($overview['from']); ?> to <?php echo htmlspecialchars($overview['to']); ?>.
            </p>
        </div>
        <form method="get" action="index.php" class="flex flex-wrap items-end gap-2">
            <input type="hidden" name="module" value="Analytics">
            <label class="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                From
                <input type="date" name="from" value="<?php echo htmlspecialchars($overview['from']); ?>"
                       class="mt-1 block px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white/80">
            </label>
            <label class="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                To
                <input type="date" name="to" value="<?php echo htmlspecialchars($overview['to']); ?>"
                       class="mt-1 block px-3 py-2 text-sm rounded-lg border border-slate-200 bg-white/80">
            </label>
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700 transition">Apply</button>
            <a href="index.php?module=Analytics" class="px-3 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-700">Reset</a>
        </form>
    </div>

    <!-- Headline cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 stagger">
        <?php if ($analyticsMoney): ?>
            <div class="glass-card p-4 border-t-4 border-emerald-400">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Revenue</div>
                <div class="mt-1 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums"><?php echo $fmtMoney($overview['revenue']); ?></div>
                <div class="text-[11px] text-slate-400 mt-1">Sales in range</div>
            </div>
            <div class="glass-card p-4 border-t-4 border-rose-400">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Costs</div>
                <div class="mt-1 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums"><?php echo $fmtMoney($overview['expenses']); ?></div>
                <div class="text-[11px] text-slate-400 mt-1">All recorded expenses</div>
            </div>
            <div class="glass-card p-4 border-t-4 <?php echo $overview['profit'] >= 0 ? 'border-teal-400' : 'border-red-400'; ?>">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Profit</div>
                <div class="mt-1 text-xl md:text-2xl font-extrabold tabular-nums <?php echo $overview['profit'] >= 0 ? 'text-emerald-700' : 'text-red-600'; ?>"><?php echo $fmtMoney($overview['profit']); ?></div>
                <div class="text-[11px] text-slate-400 mt-1">Margin <?php echo $fmtNum($overview['margin_pct'], 1); ?>% · ROI <?php echo $fmtNum($overview['roi_pct'], 1); ?>%</div>
            </div>
            <div class="glass-card p-4 border-t-4 border-indigo-400">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Cost per hectare</div>
                <div class="mt-1 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums"><?php echo $overview['cost_per_ha'] === null ? '—' : $fmtMoney($overview['cost_per_ha']); ?></div>
                <div class="text-[11px] text-slate-400 mt-1"><?php echo $fmtQty($overview['area_ha']); ?> ha mapped</div>
            </div>
        <?php else: ?>
            <div class="glass-card p-4 border-t-4 border-emerald-400">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Active crops</div>
                <div class="mt-1 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums"><?php echo (int) $overview['active_crops']; ?></div>
                <div class="text-[11px] text-slate-400 mt-1">In production</div>
            </div>
            <div class="glass-card p-4 border-t-4 border-amber-400">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Livestock</div>
                <div class="mt-1 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums"><?php echo (int) $overview['livestock']; ?></div>
                <div class="text-[11px] text-slate-400 mt-1">Active animals</div>
            </div>
            <div class="glass-card p-4 border-t-4 border-indigo-400">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Area farmed</div>
                <div class="mt-1 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums"><?php echo $fmtQty($overview['area_ha']); ?> ha</div>
                <div class="text-[11px] text-slate-400 mt-1">Mapped field area</div>
            </div>
            <div class="glass-card p-4 border-t-4 border-teal-400">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Harvested</div>
                <div class="mt-1 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums"><?php echo $fmtQty($overview['harvest_kg']); ?></div>
                <div class="text-[11px] text-slate-400 mt-1">kg in range</div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($analyticsMoney): ?>
    <div class="glass-card p-4 sm:p-6 animate-fade-in">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-bold text-slate-800">Crop enterprise profitability</h2>
            <span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">Revenue vs cost</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-[10px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
                    <tr><th class="text-left py-2 pr-4">Crop</th><th class="text-right py-2 pr-4">Revenue</th><th class="text-right py-2 pr-4">Cost</th><th class="text-right py-2">Profit</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($cropRows)): ?>
                    <tr><td colspan="4" class="py-6 text-center text-slate-500">No crop records yet.</td></tr>
                <?php else: foreach ($cropRows as $r): ?>
                    <tr>
                        <td class="py-2 pr-4 font-semibold text-slate-700"><?php echo htmlspecialchars((string) $r['name']); ?></td>
                        <td class="py-2 pr-4 text-right tabular-nums"><?php echo $fmtMoney($r['revenue']); ?></td>
                        <td class="py-2 pr-4 text-right tabular-nums"><?php echo $fmtMoney($r['cost']); ?></td>
                        <td class="py-2 text-right tabular-nums font-bold <?php echo ((float) $r['profit']) >= 0 ? 'text-emerald-700' : 'text-red-600'; ?>"><?php echo $fmtMoney($r['profit']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="glass-card p-4 sm:p-6 animate-fade-in">
            <h2 class="text-lg font-bold text-slate-800 mb-3">Yield per hectare</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-[10px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr><th class="text-left py-2 pr-3">Crop</th><th class="text-right py-2 pr-3">Area (ha)</th><th class="text-right py-2 pr-3">Harvested</th><th class="text-right py-2">Yield/ha</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (empty($yieldRows)): ?>
                        <tr><td colspan="4" class="py-6 text-center text-slate-500">No yield data yet.</td></tr>
                    <?php else: foreach ($yieldRows as $r): ?>
                        <tr>
                            <td class="py-2 pr-3 font-semibold text-slate-700"><?php echo htmlspecialchars((string) $r['name']); ?></td>
                            <td class="py-2 pr-3 text-right tabular-nums"><?php echo $fmtQty($r['area_ha']); ?></td>
                            <td class="py-2 pr-3 text-right tabular-nums"><?php echo $fmtQty($r['harvested']); ?></td>
                            <td class="py-2 text-right tabular-nums font-bold text-slate-700"><?php echo $fmtQty($r['yield_per_ha']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-card p-4 sm:p-6 animate-fade-in">
            <h2 class="text-lg font-bold text-slate-800 mb-3">Latest market prices</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-[10px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr><th class="text-left py-2 pr-3">Crop</th><th class="text-right py-2 pr-3">Price</th><th class="text-right py-2 pr-3">Change</th><th class="text-right py-2">Date</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (empty($marketRows)): ?>
                        <tr><td colspan="4" class="py-6 text-center text-slate-500">No market data recorded yet.</td></tr>
                    <?php else: foreach ($marketRows as $r):
                        list($dir, $label) = $trend($r['price'] ?? null, $r['prev_price'] ?? null);
                        $cls = $dir === 'up' ? 'text-emerald-700' : ($dir === 'down' ? 'text-red-600' : 'text-slate-500');
                    ?>
                        <tr>
                            <td class="py-2 pr-3 font-semibold text-slate-700"><?php echo htmlspecialchars((string) $r['crop_name']); ?></td>
                            <td class="py-2 pr-3 text-right tabular-nums"><?php echo $fmtNum($r['price']); ?></td>
                            <td class="py-2 pr-3 text-right tabular-nums font-bold <?php echo $cls; ?>"><?php echo htmlspecialchars($label); ?></td>
                            <td class="py-2 text-right tabular-nums text-slate-500"><?php echo htmlspecialchars((string) ($r['market_date'] ?? '—')); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($analyticsMoney): ?>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="glass-card p-4 sm:p-6 animate-fade-in">
            <h2 class="text-lg font-bold text-slate-800 mb-3">Livestock enterprise</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-[10px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr><th class="text-left py-2 pr-3">Animal</th><th class="text-right py-2 pr-3">Revenue</th><th class="text-right py-2 pr-3">Cost</th><th class="text-right py-2">Profit</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (empty($livestockRows)): ?>
                        <tr><td colspan="4" class="py-6 text-center text-slate-500">No livestock records yet.</td></tr>
                    <?php else: foreach ($livestockRows as $r): ?>
                        <tr>
                            <td class="py-2 pr-3 font-semibold text-slate-700"><?php echo htmlspecialchars((string) $r['name']); ?></td>
                            <td class="py-2 pr-3 text-right tabular-nums"><?php echo $fmtMoney($r['revenue']); ?></td>
                            <td class="py-2 pr-3 text-right tabular-nums"><?php echo $fmtMoney($r['cost']); ?></td>
                            <td class="py-2 text-right tabular-nums font-bold <?php echo ((float) $r['profit']) >= 0 ? 'text-emerald-700' : 'text-red-600'; ?>"><?php echo $fmtMoney($r['profit']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-card p-4 sm:p-6 animate-fade-in">
            <h2 class="text-lg font-bold text-slate-800 mb-3">Equipment running cost</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-[10px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr><th class="text-left py-2 pr-3">Machine</th><th class="text-right py-2">Cost in range</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (empty($equipmentRows)): ?>
                        <tr><td colspan="2" class="py-6 text-center text-slate-500">No equipment records yet.</td></tr>
                    <?php else: foreach ($equipmentRows as $r): ?>
                        <tr>
                            <td class="py-2 pr-3 font-semibold text-slate-700"><?php echo htmlspecialchars((string) $r['name']); ?></td>
                            <td class="py-2 text-right tabular-nums font-bold text-slate-700"><?php echo $fmtMoney($r['cost']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="glass-card p-4 sm:p-6 animate-fade-in">
            <h2 class="text-lg font-bold text-slate-800 mb-3">Inventory on hand</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-[10px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr><th class="text-left py-2 pr-3">Item</th><th class="text-right py-2 pr-3">Quantity</th><?php if ($analyticsMoney): ?><th class="text-right py-2">Value</th><?php endif; ?></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (empty($inventoryRows)): ?>
                        <tr><td colspan="<?php echo $analyticsMoney ? 3 : 2; ?>" class="py-6 text-center text-slate-500">No stock recorded yet.</td></tr>
                    <?php else: foreach ($inventoryRows as $r): ?>
                        <tr>
                            <td class="py-2 pr-3 font-semibold text-slate-700"><?php echo htmlspecialchars((string) $r['name']); ?></td>
                            <td class="py-2 pr-3 text-right tabular-nums"><?php echo $fmtQty($r['quantity']); ?> <?php echo htmlspecialchars((string) ($r['unit'] ?? '')); ?></td>
                            <?php if ($analyticsMoney): ?>
                                <td class="py-2 text-right tabular-nums font-bold text-slate-700"><?php echo $fmtMoney($r['value']); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-card p-4 sm:p-6 animate-fade-in">
            <h2 class="text-lg font-bold text-slate-800 mb-3">Expiring stock (30 days)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-[10px] uppercase tracking-wide text-slate-500 border-b border-slate-200">
                        <tr><th class="text-left py-2 pr-3">Item</th><th class="text-right py-2 pr-3">Quantity</th><th class="text-right py-2">Expiry</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (empty($expiringRows)): ?>
                        <tr><td colspan="3" class="py-6 text-center text-slate-500">Nothing expiring in the next 30 days.</td></tr>
                    <?php else: foreach ($expiringRows as $r): ?>
                        <tr>
                            <td class="py-2 pr-3 font-semibold text-slate-700"><?php echo htmlspecialchars((string) $r['name']); ?></td>
                            <td class="py-2 pr-3 text-right tabular-nums"><?php echo $fmtQty($r['quantity']); ?> <?php echo htmlspecialchars((string) ($r['unit'] ?? '')); ?></td>
                            <td class="py-2 text-right tabular-nums text-amber-700"><?php echo htmlspecialchars((string) $r['expiry_date']); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 text-sm">
        <a href="index.php?module=Ops" class="font-semibold text-emerald-700 hover:text-emerald-800">Record operational data &rarr;</a>
        <span class="text-slate-300">|</span>
        <a href="index.php?module=Reports" class="font-semibold text-slate-600 hover:text-slate-800">Export reports &rarr;</a>
        <span class="text-slate-300">|</span>
        <a href="index.php?module=Analytics&amp;action=manage" class="text-xs text-slate-400 hover:text-slate-600">Manage legacy analytics entries</a>
    </div>
</div>
