<?php
/**
 * Reusable contextual recommendation cards for a module.
 * Expects: $engine (InsightsEngine) and $ctxModule (module name to filter by).
 * Renders only that module's recommendations as compact, tap-to-navigate cards.
 */
require_once __DIR__ . '/../../../src/Intelligence/InsightsEngine.php';

$engine    = $engine ?? new InsightsEngine($GLOBALS['pdo'] ?? $pdo);
$ctxModule = $ctxModule ?? ($_GET['module'] ?? '');

// Surface the most relevant recommendations for a given module. e.g. spoilage /
// sell-timing advice lives under the Sales category but is highly relevant on the
// Storage panel (stored produce). Storage pulls Sales recs in too.
$aliases = [
    'Storage' => ['Storage', 'Sales'],
    'Inventory' => ['Inventory'],
    'Pest' => ['Pest'],
    'Sales' => ['Sales'],
    'Irrigation' => ['Irrigation'],
    'Livestock' => ['Livestock'],
    'Weather' => ['Irrigation'],
];
$merge   = $aliases[$ctxModule] ?? [$ctxModule];

$recs = [];
foreach ($merge as $m) {
    foreach ($engine->all($m) as $r) { $recs[] = $r; }
}
$seen = [];
$recs = array_values(array_filter($recs, function ($r) use (&$seen) {
    $k = $r['id'] ?? $r['title'];
    if (isset($seen[$k])) return false;
    $seen[$k] = true; return true;
}));
$badge = [
    'high'   => 'bg-red-100 text-red-700',
    'medium' => 'bg-amber-100 text-amber-700',
    'low'    => 'bg-slate-100 text-slate-600',
];
$dot = ['high' => 'bg-red-500', 'medium' => 'bg-amber-500', 'low' => 'bg-slate-400'];

if (!$recs) {
    return; // No contextual insight for this module right now.
}
?>
<div class="glass-card p-4 md:p-5 mb-4">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <span class="h-1.5 w-1.5 rounded-full bg-gradient-to-r from-violet-500 to-indigo-500"></span>
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">AI Recommendations</h3>
        </div>
        <a href="index.php?module=Insights" class="text-[11px] font-bold text-green-700 hover:text-green-800">View all →</a>
    </div>
    <div class="space-y-2.5">
        <?php foreach ($recs as $r): ?>
            <?php $b = $badge[$r['priority']] ?? $badge['low']; $d = $dot[$r['priority']] ?? 'bg-slate-400'; ?>
            <a href="<?php echo htmlspecialchars($r['link']); ?>" class="flex items-start gap-3 rounded-xl border border-slate-100 bg-white/60 p-3 hover:bg-white hover:shadow-sm transition group">
                <span class="mt-1 w-2 h-2 rounded-full <?php echo $d; ?> shrink-0"></span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($r['title']); ?></span>
                        <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full <?php echo $b; ?> shrink-0"><?php echo $r['priority']; ?></span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5"><?php echo htmlspecialchars($r['message']); ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
