<?php
// Field landing — Leaflet map simulating individual fields per farm.
require_once __DIR__ . '/../../config/env.php';

$farmId = isset($_GET['farm_id']) ? (int) $_GET['farm_id'] : 0;
if ($farmId) {
    $stmt = $pdo->prepare("SELECT * FROM farms WHERE id = ?");
    $stmt->execute([$farmId]);
    $farm = $stmt->fetch();
} else {
    $farm = $pdo->query("SELECT * FROM farms ORDER BY id LIMIT 1")->fetch();
}

$defaultLat = -15.3875; $defaultLon = 28.3228;
$center = ['lat' => $defaultLat, 'lng' => $defaultLon];
if ($farm) {
    $la = (float) ($farm['latitude'] ?? 0); $lo = (float) ($farm['longitude'] ?? 0);
    if (($la != 0 || $lo != 0)) { $center = ['lat' => $la, 'lng' => $lo]; }
}

if ($farmId) {
    $stmt = $pdo->prepare("SELECT * FROM fields WHERE farm_id = ? ORDER BY id");
    $stmt->execute([$farmId]);
    $fields = $stmt->fetchAll();
} else {
    $fields = $pdo->query("SELECT * FROM fields ORDER BY id")->fetchAll();
}

$totFields = count($fields);
$totHa = 0; $soilMap = [];
foreach ($fields as $fld) {
    $totHa += (float) ($fld['size'] ?? 0);
    $soil = (string) ($fld['soil_type'] ?? 'Unknown');
    $soilMap[$soil] = ($soilMap[$soil] ?? 0) + 1;
}
arsort($soilMap);

$soilColors = ['clay' => '#b45309', 'sandy' => '#d97706', 'loam' => '#16a34a', 'silty' => '#ca8a04', 'chalky' => '#a8a29e', 'peat' => '#78350f'];

function pancake($s) { return '#' . substr(md5($s), 0, 6); }

$addLink = $farmId ? 'index.php?module=Field&action=manage&farm_id=' . $farmId : 'index.php?module=Field&action=manage';
?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800 flex items-center gap-3">
    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
    Field Overview &amp; Map
    <?php if ($farm): ?><span class="text-base font-semibold text-slate-500">— <?php echo htmlspecialchars($farm['name']); ?></span><?php endif; ?>
</h1>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
    <div class="glass-card p-4 text-center"><div class="text-3xl font-extrabold text-green-700"><?php echo $totFields; ?></div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fields</div></div>
    <div class="glass-card p-4 text-center"><div class="text-3xl font-extrabold text-emerald-600"><?php echo number_format($totHa, 1); ?> ha</div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Area</div></div>
    <div class="glass-card p-4 text-center"><div class="text-3xl font-extrabold text-amber-600"><?php echo ($totFields ? round($totHa / $totFields, 1) : 0); ?> ha</div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Field</div></div>
    <div class="glass-card p-4 text-center"><div class="text-3xl font-extrabold text-slate-700"><?php echo count($soilMap); ?></div><div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Soil Types</div></div>
</div>

<div class="glass-card p-3 sm:p-5 mb-6">
    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
        <h2 class="text-lg font-bold text-slate-700">Field Layout (simulated)</h2>
        <div class="flex items-center gap-3 flex-wrap">
            <?php foreach ($soilMap as $soil => $n): ?>
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                    <span class="w-3 h-3 rounded-full inline-block" style="background:<?php echo isset($soilColors[strtolower($soil)]) ? $soilColors[strtolower($soil)] : pancake($soil); ?>"></span>
                    <?php echo htmlspecialchars($soil); ?> (<?php echo $n; ?>)
                </span>
            <?php endforeach; ?>
            <a href="<?php echo $addLink; ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-shadow">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                Add / Manage Field
            </a>
        </div>
    </div>
    <div id="field-map" class="rounded-xl overflow-hidden" style="height: 420px; background:#e8f0e6"></div>
</div>

<?php if (!empty($soilMap)): ?>
<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-sm font-bold text-slate-700 mb-3">Fields by Soil Type</h2>
    <?php foreach ($soilMap as $soil => $n): $pct = $totFields ? ($n / $totFields * 100) : 0; ?>
        <div class="flex items-center gap-3 mb-2">
            <span class="text-xs font-semibold text-slate-600 w-24"><?php echo htmlspecialchars($soil); ?></span>
            <div class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full" style="width:<?php echo $pct; ?>%;background:<?php echo isset($soilColors[strtolower($soil)]) ? $soilColors[strtolower($soil)] : pancake($soil); ?>"></div>
            </div>
            <span class="text-xs text-slate-500 w-8 text-right"><?php echo round($pct); ?>%</span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php if (!empty($fields)): foreach ($fields as $fld):
        $fn = is_array($fld) ? ($fld['name'] ?? 'Field') : ($fld->name ?? 'Field');
        $fs = is_array($fld) ? ($fld['size'] ?? 0) : ($fld->size ?? 0);
        $st = is_array($fld) ? ($fld['soil_type'] ?? 'Unknown') : ($fld->soil_type ?? 'Unknown');
    ?>
        <div class="glass-card p-4 sm:p-5">
            <div class="flex items-center justify-between">
                <div class="text-base font-bold text-slate-800"><?php echo htmlspecialchars($fn); ?></div>
                <span class="w-3.5 h-3.5 rounded-full inline-block" style="background:<?php echo isset($soilColors[strtolower($st)]) ? $soilColors[strtolower($st)] : pancake($st); ?>"></span>
            </div>
            <div class="mt-1 text-sm text-slate-500"><?php echo number_format((float) $fs, 1); ?> ha · <?php echo htmlspecialchars($st); ?></div>
        </div>
    <?php endforeach; else: ?>
        <p class="text-slate-500 col-span-full glass-card p-4">No fields recorded<?php echo $farm ? ' for this farm' : ''; ?> yet.</p>
    <?php endif; ?>
</div>

<script>
(function () {
    var center = [<?php echo json_encode($center['lat']); ?>, <?php echo json_encode($center['lng']); ?>];
    var fields = <?php echo json_encode(array_map(function ($fld) {
        return [
            'id'   => (int) (is_array($fld) ? ($fld['id'] ?? 0) : ($fld->id ?? 0)),
            'name' => (string) (is_array($fld) ? ($fld['name'] ?? 'Field') : ($fld->name ?? 'Field')),
            'size' => (float) (is_array($fld) ? ($fld['size'] ?? 0) : ($fld->size ?? 0)),
            'soil' => (string) (is_array($fld) ? ($fld['soil_type'] ?? 'Unknown') : ($fld->soil_type ?? 'Unknown')),
        ];
    }, $fields)); ?>;
    var map = L.map('field-map').setView(center, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
    var colors = {
        clay:'#b45309', sandy:'#d97706', loam:'#16a34a', silty:'#ca8a04', chalky:'#a8a29e', peat:'#78350f'
    };
    function soilColor(s){ var k=String(s).toLowerCase(); return colors[k] || '#' + (function(){var h=0;for(var i=0;i<s.length;i++){h=(h*31+s.charCodeAt(i))>>>0;}return (h&0xFFFFFF).toString(16).padStart(6,'0');})(s); }
    var metersPerDegLat = 111320;
    if (!fields.length) {
        L.marker(center).addTo(map).bindPopup('<strong>No fields</strong><br>Add a field to see it on the map.');
    }
    fields.forEach(function (fl, idx) {
        // simulated layout: grid around the farm centre, deterministic by index
        var col = idx % 3, row = Math.floor(idx / 3);
        var lat = center[0] + (row === 0 ? 0.0006 : 0.00082 * row);
        var lng = center[1] + (col - 1) * 0.0014;
        var radius = Math.max(90, Math.min(220, Math.sqrt(fl.size || 1) * 55));
        var color = soilColor(fl.soil);
        var cr = L.circle([lat, lng], { radius: radius, color: color, weight: 2, fillColor: color, fillOpacity: 0.28 }).addTo(map);
        cr.bindPopup('<strong>' + fl.name + '</strong><br>' + fl.size.toFixed(1) + ' ha · ' + fl.soil);
    });
    if (fields.length >= 2) {
        map.fitBounds(fields.map(function(fl, idx) {
            var col = idx % 3, row = Math.floor(idx / 3);
            return [center[0] + (row === 0 ? 0.0006 : 0.00082 * row), center[1] + (col - 1) * 0.0014];
        }));
    }
})();
</script>
