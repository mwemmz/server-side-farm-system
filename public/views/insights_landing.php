<?php
/**
 * Insights (AI/BI) — the recommendations & analytics feed.
 * Ranks proactive, data-driven recommendations from the farm's own records.
 *
 * Rendered context: $engine (InsightsEngine), $recs (all recommendations),
 * $stats (priority/category counters). Set by the front controller.
 */
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../src/Intelligence/InsightsEngine.php';

$recs  = $recs ?? [];
$stats = $stats ?? [];

$badge = [
    'high'   => ['bg-red-100 text-red-700',   'High priority'],
    'medium' => ['bg-amber-100 text-amber-700', 'Medium'],
    'low'    => ['bg-slate-100 text-slate-600', 'Low'],
];
$ring = [
    'high'   => 'from-red-500 to-rose-500',
    'medium' => 'from-amber-500 to-orange-500',
    'low'    => 'from-slate-400 to-slate-500',
];
$icon = [
    'irrigation' => '<path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z"/>',
    'inventory'  => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
    'sell'       => '<line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/>',
    'yield_risk' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    'livestock'  => '<path d="M3 7V5c0-1.1.9-2 2-2h2"/><path d="M17 3h2c1.1 0 2 .9 2 2v2"/><path d="M21 17v2c0 1.1-.9 2-2 2h-2"/><path d="M7 21H5c-1.1 0-2-.9-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/>',
    'weather'    => '<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"/>',
];

$order = ['high', 'medium', 'low'];
usort($recs, function ($a, $b) use ($order) {
    return (array_search($a['priority'], $order) <=> array_search($b['priority'], $order))
        ?: strcmp($a['module_label'] ?? '', $b['module_label'] ?? '');
});
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">AI Insights</h1>
            <p class="text-sm text-slate-500 mt-1.5 max-w-3xl">Proactive, data-driven recommendations computed from your farm's own records — the longer you log, the sharper they get.</p>
        </div>
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <button onclick="window.FFAssistant && FFAssistant.open()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Ask the Assistant
            </button>
        </div>
    </div>

    <!-- Stats strip -->
    <?php $tot = $stats['total'] ?? 0; $hi = $stats['by_priority']['high'] ?? 0; $med = $stats['by_priority']['medium'] ?? 0; $cat = count($stats['by_category'] ?? []); ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="glass-card p-4 text-center"><div class="text-2xl font-extrabold text-slate-800"><?php echo $tot; ?></div><div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mt-1">Recommendations</div></div>
        <div class="glass-card p-4 text-center"><div class="text-2xl font-extrabold text-red-600"><?php echo $hi; ?></div><div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mt-1">High priority</div></div>
        <div class="glass-card p-4 text-center"><div class="text-2xl font-extrabold text-amber-600"><?php echo $med; ?></div><div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mt-1">Medium</div></div>
        <div class="glass-card p-4 text-center"><div class="text-2xl font-extrabold text-green-700"><?php echo $cat; ?></div><div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mt-1">Categories</div></div>
    </div>

    <!-- Feed -->
    <div class="space-y-3">
        <?php if (!$recs): ?>
            <div class="glass-card p-6 text-center text-slate-500">
                <p class="font-semibold text-slate-600">No recommendations right now.</p>
                <p class="text-sm mt-1">Everything looks on track. Keep logging crops, weather, harvests and sales so the engine can surface smart suggestions.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($recs as $r): ?>
            <?php
                $b = $badge[$r['priority']] ?? $badge['low'];
                $ri = $ring[$r['priority']] ?? $ring['low'];
                $ic = $icon[$r['category']] ?? $icon['inventory'];
                $label = ucfirst($r['module_label'] ?? $r['module']);
            ?>
            <div class="glass-card card-glow overflow-hidden group">
                <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r <?php echo $ri; ?> opacity-80"></div>
                <div class="p-4 sm:p-5 flex items-start gap-4">
                    <div class="shrink-0 w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><?php echo $ic; ?></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><?php echo $label; ?></span>
                            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?php echo $b[0]; ?>"><?php echo $b[1]; ?></span>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($r['title']); ?></h3>
                        <p class="text-sm text-slate-600 mt-1"><?php echo htmlspecialchars($r['message']); ?></p>
                        <a href="<?php echo htmlspecialchars($r['link']); ?>" class="inline-flex items-center gap-1.5 mt-2 text-xs font-bold text-green-700 hover:text-green-800">
                            Open <?php echo $label; ?>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
