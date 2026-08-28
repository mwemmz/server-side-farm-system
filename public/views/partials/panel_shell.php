<?php
/**
 * Shared shell for Phase 6 control-panel views.
 *
 * Expected variables before including:
 *   $panelTitle      string  e.g. "Irrigation Control Panel"
 *   $panelModule     string  e.g. "Irrigation" (must match a PanelJsonGateway case)
 *   $panelEntities   array   assoc id => label ([] for farm- or aggregate-level panels)
 *   $panelFarmId     int|null  optional farm id for farm-level panels
 *   $panelAddLink    string  URL to the demoted CRUD "Add/Edit" view
 */
$panelEntities = isset($panelEntities) ? $panelEntities : [];
$panelFarmId   = isset($panelFarmId)   ? $panelFarmId   : null;

// Allow a farm/asset filter from the URL (?farm_id=, ?asset=) so markers on the
// farm map can deep-link straight into the relevant module scoped to that asset.
$wantedFarmId = isset($_GET['farm_id']) ? (int) $_GET['farm_id'] : 0;
if ($wantedFarmId) $panelFarmId = $wantedFarmId;
$wantedAssetId = isset($_GET['asset']) ? (int) $_GET['asset'] : 0;

$firstId       = null;
$selectorOptions = '';
foreach ($panelEntities as $eid => $elabel) {
    $isSel = ($wantedAssetId && $wantedAssetId === (int) $eid)
        || (!$wantedAssetId && $firstId === null);
    $selectorOptions .= '<option value="' . (int) $eid . '"' . ($isSel ? ' selected' : '') . '>'
        . htmlspecialchars($elabel) . '</option>';
    if ($firstId === null) $firstId = (int) $eid;
}
$startingId = ($wantedAssetId && array_key_exists($wantedAssetId, $panelEntities)) ? $wantedAssetId : ($firstId ?: 0);
$cfg = [
    'module'  => $panelModule,
    'pollMs'  => 5000,
    'entity'  => ['id' => $startingId],
    'farmId'  => $panelFarmId,
];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800"><?php echo htmlspecialchars($panelTitle); ?></h1>

<?php
// Contextual AI recommendation cards (data-driven) for this panel's module.
require_once __DIR__ . '/../../../src/Intelligence/InsightsEngine.php';
$engine = new InsightsEngine($pdo);
$ctxModule = $panelModule;
require __DIR__ . '/recommendation_cards.php';
?>

<script type="application/json" id="ff-panel-config"><?php echo json_encode($cfg); ?></script>

<?php if ($selectorOptions): ?>
<div class="glass-card p-3 sm:p-4 mb-5 flex flex-wrap items-center gap-3">
    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
    <span class="text-sm font-semibold text-slate-600">Live view:</span>
    <select id="ff-entity-select" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700 shadow-sm"><?php echo $selectorOptions; ?></select>
    <span class="text-xs text-slate-400">switch to live-monitor another record</span>
</div>
<?php endif; ?>

<div class="flex items-center justify-between mb-4">
    <span id="ff-panel-status" class="inline-flex items-center gap-2 text-xs text-slate-500">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> connecting…
    </span>
    <a href="<?php echo htmlspecialchars($panelAddLink); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-shadow">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Add / Manage
    </a>
</div>

<div id="ff-panel-mount" class="space-y-5">
    <div class="glass-card p-8 text-center text-slate-400 text-sm">Loading live panel…</div>
</div>

<script src="views/js/panels.js"></script>
