<?php
// Farm landing — Leaflet map simulating the farm layout + farm cards.
$farms = $data ?? [];
require_once __DIR__ . '/../../config/env.php';

// Defaults from the schema (Lusaka, Zambia) in case a farm has no coords.
$defaultLat = -15.3875;
$defaultLon = 28.3228;

// Aggregate stats across connected data.
$totFarms   = count($farms);
$totFields  = 0;  $totLivestock = 0;  $totCrops = 0;  $totHa = 0;
foreach ($farms as $f) {
    $fid = (int) (is_array($f) ? ($f['id'] ?? 0) : ($f->id ?? 0));
    $totFields  += (int) $pdo->query("SELECT COUNT(*) FROM fields WHERE farm_id = $fid")->fetchColumn();
    $totLivestock += (int) $pdo->query("SELECT COUNT(*) FROM livestock WHERE farm_id = $fid")->fetchColumn();
    $totCrops   += (int) $pdo->query("SELECT COUNT(*) FROM crops WHERE farm_id = $fid")->fetchColumn();
    $h = (float) $pdo->query("SELECT COALESCE(SUM(size),0) FROM fields WHERE farm_id = $fid")->fetchColumn();
    $totHa += $h;
}

$center = ['lat' => $defaultLat, 'lng' => $defaultLon];
if (count($farms) === 1) {
    $one = $farms[0];
    if (!empty($one['latitude']) || $one['latitude'] !== null) {
        $la = (float) ($one['latitude'] ?? $defaultLat);
        $lo = (float) ($one['longitude'] ?? $defaultLon);
        if ($la != 0 || $lo != 0) { $center = ['lat' => $la, 'lng' => $lo]; }
    }
}
?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800 flex items-center gap-3">
    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
    Farm Overview &amp; Map
</h1>

<!-- Summary strip -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
    <div class="glass-card p-4 text-center"><div class="text-3xl font-extrabold text-green-700"><?php echo $totFarms; ?></div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Farms</div></div>
    <div class="glass-card p-4 text-center"><div class="text-3xl font-extrabold text-emerald-600"><?php echo $totFields; ?></div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fields</div></div>
    <div class="glass-card p-4 text-center"><div class="text-3xl font-extrabold text-amber-600"><?php echo $totLivestock; ?></div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Livestock</div></div>
    <div class="glass-card p-4 text-center"><div class="text-3xl font-extrabold text-slate-700"><?php echo number_format($totHa, 1); ?> ha</div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Cultivated</div></div>
</div>

<!-- Map -->
<div class="glass-card p-3 sm:p-5 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-lg font-bold text-slate-700">Farm Locations (simulated map)</h2>
        <a href="index.php?module=Farm&action=manage" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-shadow">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add / Manage Farm
        </a>
    </div>
    <div id="farm-map" class="rounded-xl overflow-hidden" style="height: 420px; background:#e8f0e6"></div>
</div>

<!-- Farm cards -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php if (!empty($farms)): foreach ($farms as $f):
        $fid = (int) (is_array($f) ? ($f['id'] ?? 0) : ($f->id ?? 0));
        $name = is_array($f) ? ($f['name'] ?? 'Unnamed') : ($f->name ?? 'Unnamed');
        $loc  = is_array($f) ? ($f['location'] ?? '') : ($f->location ?? '');
    ?>
        <div class="glass-card p-4 sm:p-5">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-lg font-bold text-slate-800"><?php echo htmlspecialchars($name); ?></div>
                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($loc ?: 'Location not set'); ?></div>
                </div>
                <span class="text-[10px] font-bold uppercase px-2 py-1 rounded-full bg-emerald-100 text-emerald-800">Farm #<?php echo $fid; ?></span>
            </div>
            <a href="index.php?module=Field&farm_id=<?php echo $fid; ?>" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-green-700 hover:text-green-800">
                View fields
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
    <?php endforeach; else: ?>
        <p class="text-slate-500 col-span-full glass-card p-4">No farms registered yet.</p>
    <?php endif; ?>
</div>

<script>
(function () {
    var farms = <?php echo json_encode(array_map(function ($f) {
        return [
            'id'   => (int) (is_array($f) ? ($f['id'] ?? 0) : ($f->id ?? 0)),
            'name' => (string) (is_array($f) ? ($f['name'] ?? 'Unnamed') : ($f->name ?? 'Unnamed')),
            'loc'  => (string) (is_array($f) ? ($f['location'] ?? '') : ($f->location ?? '')),
            'lat'  => (float) (is_array($f) ? ($f['latitude'] ?? 0) : ($f->latitude ?? 0)),
            'lng'  => (float) (is_array($f) ? ($f['longitude'] ?? 0) : ($f->longitude ?? 0)),
        ];
    }, $farms)); ?>;
    var map = L.map('farm-map').setView([<?php echo json_encode($center['lat']); ?>, <?php echo json_encode($center['lng']); ?>], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
    if (!farms.length) {
        L.marker([<?php echo json_encode($center['lat']); ?>, <?php echo json_encode($center['lng']); ?>]).addTo(map)
            .bindPopup('<strong>Demo farm location</strong><br>Add a farm to see it here.');
    } else {
        farms.forEach(function (f) {
            var la = (f.lat && f.lat !== 0) ? f.lat : <?php echo json_encode($defaultLat); ?>;
            var lo = (f.lng && f.lng !== 0) ? f.lng : <?php echo json_encode($defaultLon); ?>;
            var mk = L.marker([la, lo]).addTo(map)
                .bindPopup('<strong>' + f.name + '</strong><br>' + (f.loc || '') + '<br><a href="index.php?module=Field&farm_id=' + f.id + '">Open fields</a>');
            L.circle([la, lo], { radius: 250, color: '#16a34a', fillColor: '#22c55e', fillOpacity: 0.18 }).addTo(map);
        });
    }
})();
</script>
