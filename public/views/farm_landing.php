<?php
// Farm landing — Leaflet satellite map simulating the farm layout + asset markers.
$farms = $data ?? [];
require_once __DIR__ . '/../../config/env.php';

// Defaults from the schema (Lusaka, Zambia) in case a farm has no coords.
$defaultLat = -15.3875;
$defaultLon = 28.3228;

// Aggregate stats across connected data.
$totFarms = count($farms);
$totFields  = 0;  $totLivestock = 0;  $totCrops = 0;  $totHa = 0;
foreach ($farms as $f) {
    $fid = (int) (is_array($f) ? ($f['id'] ?? 0) : ($f->id ?? 0));
    $totFields   += (int) $pdo->query("SELECT COUNT(*) FROM fields WHERE farm_id = $fid")->fetchColumn();
    $totLivestock += (int) $pdo->query("SELECT COUNT(*) FROM livestock WHERE farm_id = $fid")->fetchColumn();
    $totCrops    += (int) $pdo->query("SELECT COUNT(*) FROM crops WHERE farm_id = $fid")->fetchColumn();
    $h = (float) $pdo->query("SELECT COALESCE(SUM(size),0) FROM fields WHERE farm_id = $fid")->fetchColumn();
    $totHa += $h;
}

// ---- Asset markers -----------------------------------------------------
// The farm assets don't carry real GPS coordinates in the schema, so (as with
// the field map) their on-farm positions are simulated deterministically around
// each farm's centre. Each marker carries metadata (asset type, asset id, module
// route, farm id) so a tap can open the relevant module filtered to that asset.
$assets = [];
$typeMeta = [
    'poultry'    => ['module' => 'Livestock',   'label' => 'Poultry House',  'icon' => 'P'],
    'barn'       => ['module' => 'Livestock',   'label' => 'Livestock Barn', 'icon' => 'B'],
    'irrigation' => ['module' => 'Irrigation',  'label' => 'Irrigation',     'icon' => 'I'],
    'water'      => ['module' => 'Irrigation',  'label' => 'Water Source',   'icon' => 'W'],
    'equipment'  => ['module' => 'Equipment',   'label' => 'Equipment Shed', 'icon' => 'E'],
    'storage'    => ['module' => 'Storage',     'label' => 'Storage Facility', 'icon' => 'S'],
    'well'       => ['module' => 'Storage',     'label' => 'Water Well',     'icon' => 'W'],
];

$focusedFarmIdx = 0; // first farm receives the global equipment/storage/water set

// Asset placement helper: deterministic, stable offset (in metres) around a centre.
$placeAsset = function ($la, $lo, $seedKey) {
    $ph  = crc32($seedKey);
    $ang = (($ph % 360) / 57.2958);
    $dist = 70 + ($ph % 260); // 70..330 m from the farm centre
    $dLat = ($dist / 111320.0) * cos($ang);
    $dLng = ($dist / (111320.0 * max(cos($la * M_PI / 180), 0.05))) * sin($ang);
    return ['lat' => $la + $dLat, 'lng' => $lo + $dLng];
};

foreach ($farms as $idx => $f) {
    $fid = (int) (is_array($f) ? ($f['id'] ?? 0) : ($f->id ?? 0));
    $la  = (float) (is_array($f) ? ($f['latitude'] ?? 0) : ($f->latitude ?? 0));
    $lo  = (float) (is_array($f) ? ($f['longitude'] ?? 0) : ($f->longitude ?? 0));
    if ($la == 0 && $lo == 0) { $la = $defaultLat; $lo = $defaultLon; }

    // Irrigation systems owned by this farm (centre-pivot sprinkler etc.).
    $irrStmt = $pdo->prepare("SELECT * FROM irrigation_systems WHERE farm_id = ?");
    $irrStmt->execute([$fid]);
    foreach ($irrStmt->fetchAll() as $irr) {
        $iid = (int) $irr['id'];
        $itype = (string) ($irr['type'] ?? 'Irrigation');
        $isPivot = (stripos($itype, 'pivot') !== false);
        $p = $placeAsset($la, $lo, "irr-$fid-$iid");
        $assets[] = [
            'id' => $iid, 'type' => 'irrigation', 'farm_id' => $fid, 'asset' => $iid,
            'module' => 'Irrigation',
            'label' => $isPivot ? "Center-Pivot Sprinkler #$iid" : $itype . " System #$iid",
            'sub' => ($irr['status'] ?? '') ? 'Status: ' . $irr['status'] : 'Irrigation system',
        ] + $p;
        // A water source (borehole / dam) serves each irrigation system.
        $w = $placeAsset($la, $lo, "water-$fid-$iid");
        $assets[] = [
            'id' => $iid, 'type' => 'water', 'farm_id' => $fid, 'asset' => $iid,
            'module' => 'Irrigation',
            'label' => "Water Source (Irrigation #$iid)",
            'sub' => 'Borehole / reservoir feeding the system',
        ] + $w;
    }

    // Poultry units -> Livestock module.
    $poultryStmt = $pdo->prepare("SELECT * FROM livestock WHERE farm_id = ? AND LOWER(type) LIKE '%poultry%'");
    $poultryStmt->execute([$fid]);
    $pidx = 0;
    foreach ($poultryStmt->fetchAll() as $l) {
        $lid = (int) $l['id'];
        $p = $placeAsset($la, $lo, "poultry-$fid-$lid");
        $assets[] = [
            'id' => $lid, 'type' => 'poultry', 'farm_id' => $fid, 'asset' => $lid,
            'module' => 'Livestock',
            'label' => "Poultry House " . (++$pidx),
            'sub' => ($l['breed'] ?? '') ? 'Breed: ' . $l['breed'] : 'Poultry unit',
        ] + $p;
    }

    // Other livestock group into a barn -> Livestock module.
    $barnStmt = $pdo->prepare("SELECT * FROM livestock WHERE farm_id = ? AND LOWER(type) NOT LIKE '%poultry%'");
    $barnStmt->execute([$fid]);
    $barnCount = $barnStmt->rowCount();
    if ($barnCount > 0) {
        $types = [];
        foreach ($barnStmt->fetchAll() as $l) { $types[(string) $l['type']] = true; }
        $b = $placeAsset($la, $lo, "barn-$fid");
        $assets[] = [
            'id' => (int) $barnCount, 'type' => 'barn', 'farm_id' => $fid, 'asset' => (int) $barnCount,
            'module' => 'Livestock',
            'label' => "Livestock Barn #$fid",
            'sub' => implode(', ', array_keys($types)) ?: 'Livestock',
        ] + $b;
    }

    // Global (non-farm-scoped) equipment + storage attach to the focused farm.
    if ($idx === $focusedFarmIdx) {
        $eqidx = 0;
        foreach ($pdo->query("SELECT * FROM equipment ORDER BY id")->fetchAll() as $eq) {
            $eid = (int) $eq['id'];
            $p = $placeAsset($la, $lo, "eq-$fid-$eid");
            $assets[] = [
                'id' => $eid, 'type' => 'equipment', 'farm_id' => $fid, 'asset' => $eid,
                'module' => 'Equipment',
                'label' => "Equipment Shed #" . (++$eqidx),
                'sub' => ($eq['name'] ?? '') . (($eq['maintenance_status'] ?? '') ? ' · ' . $eq['maintenance_status'] : ''),
            ] + $p;
        }
        $sidx = 0;
        foreach ($pdo->query("SELECT * FROM storage_records ORDER BY id")->fetchAll() as $st) {
            $sid = (int) $st['id'];
            $p = $placeAsset($la, $lo, "st-$fid-$sid");
            $assets[] = [
                'id' => $sid, 'type' => 'storage', 'farm_id' => $fid, 'asset' => $sid,
                'module' => 'Storage',
                'label' => ($st['name'] ?? 'Storage Facility') . " #" . (++$sidx),
                'sub' => 'Capacity: ' . number_format((float) ($st['capacity'] ?? 0), 0) . ' t',
            ] + $p;
        }
    }
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
<div class="glass-card p-3 sm:p-5 mb-5">
    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
        <h2 class="text-lg font-bold text-slate-700">Farm Locations — Satellite View</h2>
        <a href="index.php?module=Farm&action=manage" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-shadow">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            Add / Manage Farm
        </a>
    </div>
    <div id="farm-map" class="rounded-xl overflow-hidden" style="height: 430px; background:#0b1a26"></div>
    <p class="text-[11px] text-slate-400 mt-2">Satellite imagery &copy; Esri. Asset positions are simulated for demonstration (assets have no real GPS in the schema) — tap a marker to open its module.</p>
</div>

<!-- Asset legend -->
<div class="glass-card p-3 sm:p-4 mb-5 flex flex-wrap items-center gap-x-5 gap-y-2">
    <span class="text-sm font-bold text-slate-700 mr-1">Tap an asset to manage it:</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full inline-block" style="background:#16a34a"></span> Farm</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full inline-block" style="background:#f59e0b"></span> Poultry</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full inline-block" style="background:#92400e"></span> Barn</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full inline-block" style="background:#0369a1"></span> Irrigation</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full inline-block" style="background:#06b6d4"></span> Water</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full inline-block" style="background:#ea580c"></span> Equipment</span>
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full inline-block" style="background:#7c3aed"></span> Storage</span>
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
            <a href="<?php echo $fid ? 'index.php?module=Field&farm_id=' . $fid : 'index.php?module=Field'; ?>" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-green-700 hover:text-green-800">
                View fields
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </a>
        </div>
    <?php endforeach; else: ?>
        <p class="text-slate-500 col-span-full glass-card p-4">No farms registered yet.</p>
    <?php endif; ?>
</div>

<style>
.asset-pin-inner {
    width: 32px; height: 32px; border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg); display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 9px rgba(0,0,0,.45); border: 2px solid #fff;
}
.asset-pin-inner span { transform: rotate(45deg); color: #fff; font-weight: 800; font-size: 14px; line-height: 1; }
.farm-pin-inner {
    width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg);
    display: flex; align-items: center; justify-content: center; background: #16a34a;
    box-shadow: 0 4px 9px rgba(0,0,0,.4); border: 2px solid #fff;
}
.farm-pin-inner span { transform: rotate(45deg); color: #fff; font-weight: 800; font-size: 15px; }
.asset-popup { font-family: inherit; }
.asset-popup .t { font-size: 11px; color: #64748b; margin-top: 2px; }
.asset-popup .vd { display:inline-block; margin-top: 7px; font-size: 12px; font-weight: 700; color: #059669; text-decoration: none; }
</style>

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

    var assets = <?php echo json_encode($assets); ?>;

    var typeCfg = {
        poultry:    { c: '#f59e0b', i: 'P' },
        barn:       { c: '#92400e', i: 'B' },
        irrigation: { c: '#0369a1', i: 'I' },
        water:      { c: '#06b6d4', i: 'W' },
        equipment:  { c: '#ea580c', i: 'E' },
        storage:    { c: '#7c3aed', i: 'S' }
    };

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    var center = [<?php echo json_encode($center['lat']); ?>, <?php echo json_encode($center['lng']); ?>];

    var map = L.map('farm-map').setView(center, 12);

    // Satellite base layer (Esri World Imagery — no API key required).
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics',
        maxZoom: 19
    }).addTo(map);

    var bounds = [];

    // Farm markers (green leaf pins).
    if (!farms.length) {
        L.marker(center, { icon: L.divIcon({ className: 'farm-pin', html: '<div class="farm-pin-inner"><span>&#127807;</span></div>', iconSize: [28, 40], iconAnchor: [14, 38], popupAnchor: [0, -34] }) })
            .addTo(map)
            .bindPopup('<strong>Demo farm location</strong><br>Add a farm to see it here.');
        bounds.push(center);
    } else {
        farms.forEach(function (f) {
            var la = (f.lat && f.lat !== 0) ? f.lat : <?php echo json_encode($defaultLat); ?>;
            var lo = (f.lng && f.lng !== 0) ? f.lng : <?php echo json_encode($defaultLon); ?>;
            bounds.push([la, lo]);
            var mk = L.marker([la, lo], { icon: L.divIcon({ className: 'farm-pin', html: '<div class="farm-pin-inner"><span>&#127807;</span></div>', iconSize: [28, 40], iconAnchor: [14, 38], popupAnchor: [0, -34] }) })
                .addTo(map)
                .bindPopup('<strong>' + escapeHtml(f.name) + '</strong><br>' + escapeHtml(f.loc || '') + '<br><a href="index.php?module=Field&farm_id=' + f.id + '">Open fields</a>');
            L.circle([la, lo], { radius: 220, color: '#16a34a', weight: 1, fillColor: '#22c55e', fillOpacity: 0.08 }).addTo(map);
        });
    }

    // Asset markers (color-coded by type; tap -> module filtered to the asset).
    assets.forEach(function (a) {
        var t = typeCfg[a.type] || { c: '#64748b', i: '&middot;' };
        var icon = L.divIcon({
            className: 'asset-pin',
            html: '<div class="asset-pin-inner" style="background:' + t.c + '"><span>' + t.i + '</span></div>',
            iconSize: [32, 40], iconAnchor: [16, 38], popupAnchor: [0, -34]
        });
        var link = 'index.php?module=' + encodeURIComponent(a.module) + '&farm_id=' + encodeURIComponent(a.farm_id) + '&asset=' + encodeURIComponent(a.asset);
        var mk = L.marker([a.lat, a.lng], { icon: icon }).addTo(map);
        bounds.push([a.lat, a.lng]);
        mk.bindPopup(
            '<div class="asset-popup">' +
            '<strong>' + escapeHtml(a.label) + '</strong>' +
            (a.sub ? '<div class="t">' + escapeHtml(a.sub) + '</div>' : '') +
            '<a class="vd" href="' + link + '">View details &rarr;</a>' +
            '</div>'
        );
    });

    if (bounds.length) {
        try { map.fitBounds(bounds, { padding: [35, 35] }); } catch (e) {}
    }
})();
</script>
