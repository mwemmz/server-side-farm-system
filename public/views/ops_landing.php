<?php
/**
 * Ops landing — grouped catalogue of the operational record types this role
 * can work with. Each card links to that record's list/form.
 */
$opsGroups = $data['groups'] ?? [];
$totalTypes = 0;
foreach ($opsGroups as $items) { $totalTypes += count($items); }
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 animate-fade-in">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Operations</h1>
            <p class="text-sm text-slate-500 mt-1.5 max-w-3xl">
                Day-to-day records across fields, crops, livestock, equipment, labour, harvest, sales and suppliers.
                <?php echo $totalTypes; ?> record type<?php echo $totalTypes === 1 ? '' : 's'; ?> available to you.
            </p>
        </div>
        <a href="index.php?module=Dashboard" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">&larr; Dashboard</a>
    </div>

    <?php if (empty($opsGroups)): ?>
        <div class="glass-card p-6 text-slate-600">No operational records are available for your role.</div>
    <?php endif; ?>

    <?php foreach ($opsGroups as $group => $items): ?>
        <section class="animate-fade-in">
            <div class="flex items-center gap-2 mb-3">
                <h2 class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($group); ?></h2>
                <span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-200 text-slate-600"><?php echo count($items); ?></span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3">
                <?php foreach ($items as $it): ?>
                    <a href="index.php?module=Ops&amp;view=<?php echo urlencode($it['key']); ?>"
                       class="group glass-card p-4 hover:border-emerald-300 hover:shadow-lg transition border border-white/60">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="font-bold text-slate-800 group-hover:text-emerald-700 transition"><?php echo htmlspecialchars($it['label']); ?></div>
                                <p class="text-xs text-slate-500 mt-1 leading-relaxed"><?php echo htmlspecialchars($it['summary']); ?></p>
                            </div>
                            <span class="shrink-0 text-xs font-extrabold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
                                <?php echo (int) $it['count']; ?>
                            </span>
                        </div>
                        <div class="mt-3 text-[11px] font-bold uppercase tracking-wide text-emerald-700 opacity-0 group-hover:opacity-100 transition">Open records &rarr;</div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
