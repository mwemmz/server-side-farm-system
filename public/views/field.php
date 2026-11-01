<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../src/Helpers/GeoJsonHelper.php';

$successMessage = SessionHelper::getFlash('success');
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$records = $data['records'] ?? ($data ?? []);

$boundaryFields = [];
foreach ($records as $rec) {
    if (!is_array($rec)) continue;
    $geo = $rec['boundary_geojson'] ?? null;
    if (is_string($geo) && $geo !== '') {
        $decoded = json_decode($geo, true);
        $geo = is_array($decoded) ? $decoded : null;
    }
    $boundaryFields[] = [
        'id'   => (int) ($rec['id'] ?? 0),
        'name' => (string) ($rec['name'] ?? 'Field'),
        'size' => (float) ($rec['size'] ?? 0),
        'ha'   => isset($rec['computed_hectares']) ? (float) $rec['computed_hectares'] : null,
        'lat'  => isset($rec['latitude']) ? (float) $rec['latitude'] : null,
        'lng'  => isset($rec['longitude']) ? (float) $rec['longitude'] : null,
        'geo'  => $geo,
    ];
}
$csrf = SessionHelper::csrfToken();
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-6 text-green-800">Field Management</h1>

<?php if ($successMessage): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
        <?php echo htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add New Field</h2>
    <?php
    $fields = [
        ['name' => 'farm_id', 'label' => 'Farm', 'type' => 'combo', 'options' => FormHelper::farmOptions($pdo), 'allow_custom' => false, 'placeholder' => 'Type to find a farm…', 'value' => $formData['farm_id'] ?? ($_GET['farm_id'] ?? '')],
        ['name' => 'name', 'label' => 'Name', 'value' => $formData['name'] ?? ''],
        ['name' => 'size', 'label' => 'Size (hectares)', 'type' => 'number', 'step' => '0.01', 'min' => '0.01', 'required' => false, 'value' => $formData['size'] ?? '', 'help' => 'Leave blank if you draw a boundary — the area is then measured automatically.'],
        ['name' => 'soil_type', 'label' => 'Soil Type', 'type' => 'select', 'options' => ['Loam' => 'Loam', 'Clay' => 'Clay', 'Sandy' => 'Sandy', 'Silty' => 'Silty', 'Chalky' => 'Chalky', 'Peat' => 'Peat'], 'placeholder' => 'Select a soil type…', 'value' => $formData['soil_type'] ?? ''],
        ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'step' => '0.000001', 'required' => false, 'value' => $formData['latitude'] ?? '', 'help' => 'Optional — derived from the boundary when drawn.'],
        ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'step' => '0.000001', 'required' => false, 'value' => $formData['longitude'] ?? ''],
        ['name' => 'boundary_geojson', 'label' => 'Boundary GeoJSON', 'type' => 'hidden', 'required' => false, 'value' => $formData['boundary_geojson'] ?? ''],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Field&action=add', 'POST', $errors);
    ?>
</div>

<?php if (!empty($boundaryFields)): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="glass-card p-4 sm:p-6 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div>
            <h2 class="text-xl font-semibold">Field Boundaries</h2>
            <p class="text-xs text-slate-500 mt-0.5">Tap the map to place boundary corners. Draw at least 3 points, then save.</p>
        </div>
        <div class="flex items-center gap-2">
            <select id="boundary-field" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
                <?php foreach ($boundaryFields as $bf): ?>
                    <option value="<?php echo (int) $bf['id']; ?>"><?php echo htmlspecialchars($bf['name']); ?><?php echo $bf['ha'] !== null ? ' (' . number_format($bf['ha'], 2) . ' ha)' : ''; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div id="boundary-map" class="rounded-xl overflow-hidden" style="height:360px;background:#e8f0e6"></div>
    <div class="flex flex-wrap items-center gap-2 mt-3">
        <button type="button" id="boundary-undo" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold hover:bg-slate-200">Undo point</button>
        <button type="button" id="boundary-reset" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold hover:bg-slate-200">Clear drawing</button>
        <span class="text-xs text-slate-500" id="boundary-hint">0 points</span>
        <span class="ml-auto flex gap-2">
            <button type="button" id="boundary-clear" class="px-3 py-2 rounded-lg bg-white border border-red-200 text-red-700 text-sm font-semibold hover:bg-red-50">Remove saved boundary</button>
            <button type="button" id="boundary-save" class="px-4 py-2 rounded-lg bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow hover:opacity-90">Save boundary</button>
        </span>
    </div>
    <form id="boundary-form" method="POST" action="index.php?module=Field&amp;action=boundary" class="hidden">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="field_id" id="boundary-field-id" value="">
        <input type="hidden" name="boundary_geojson" id="boundary-geojson" value="">
        <input type="hidden" name="clear_boundary" id="boundary-clear-flag" value="">
    </form>
</div>
<script>
(function () {
    var fields = <?php echo json_encode($boundaryFields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var mapEl = document.getElementById('boundary-map');
    if (!mapEl || typeof L === 'undefined') return;

    var defaultCenter = [-15.3875, 28.3228];
    var map = L.map('boundary-map').setView(defaultCenter, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

    var points = [];
    var markers = [];
    var poly = null;
    var saved = null;
    var active = null;

    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function pointLabel(ll) { return ll.lat.toFixed(6) + ', ' + ll.lng.toFixed(6); }

    function redraw() {
        markers.forEach(function (m) { map.removeLayer(m); });
        markers = [];
        if (poly) { map.removeLayer(poly); poly = null; }
        points.forEach(function (ll) {
            var m = L.circleMarker(ll, { radius: 5, color: '#166534', fillColor: '#22c55e', fillOpacity: 1 }).addTo(map);
            markers.push(m);
        });
        if (points.length >= 2) {
            poly = L.polygon(points, { color: '#16a34a', weight: 2, fillColor: '#22c55e', fillOpacity: 0.22 }).addTo(map);
        }
        document.getElementById('boundary-hint').textContent = points.length + ' point' + (points.length === 1 ? '' : 's');
    }

    function setPoints(lls) {
        points = lls.map(function (p) { return L.latLng(p[1], p[0]); });
        redraw();
    }

    function loadField(id) {
        active = null;
        for (var i = 0; i < fields.length; i++) { if (String(fields[i].id) === String(id)) { active = fields[i]; break; } }
        if (!active) return;
        document.getElementById('boundary-field-id').value = active.id;
        if (saved) { map.removeLayer(saved); saved = null; }
        if (active.geo && active.geo.coordinates && active.geo.coordinates[0]) {
            saved = L.geoJSON(active.geo, { style: { color: '#b45309', weight: 2, dashArray: '5 5', fillOpacity: 0.12 } }).addTo(map);
            setPoints(active.geo.coordinates[0]);
            map.fitBounds(saved.getBounds(), { padding: [25, 25] });
        } else {
            setPoints([]);
            var c = (active.lat && active.lng) ? [active.lat, active.lng] : defaultCenter;
            map.setView(c, 15);
        }
    }

    map.on('click', function (e) {
        points.push(e.latlng);
        redraw();
    });

    document.getElementById('boundary-undo').addEventListener('click', function () {
        points.pop(); redraw();
    });
    document.getElementById('boundary-reset').addEventListener('click', function () {
        setPoints([]);
    });
    document.getElementById('boundary-save').addEventListener('click', function () {
        if (!active) return;
        if (points.length < 3) { alert('Place at least 3 boundary points first.'); return; }
        var coords = points.map(function (ll) { return [Number(ll.lng.toFixed(6)), Number(ll.lat.toFixed(6))]; });
        coords.push(coords[0].slice());
        document.getElementById('boundary-geojson').value = JSON.stringify({ type: 'Polygon', coordinates: [coords] });
        document.getElementById('boundary-clear-flag').value = '';
        document.getElementById('boundary-form').submit();
    });
    document.getElementById('boundary-clear').addEventListener('click', function () {
        if (!active) return;
        if (!confirm('Remove the saved boundary for ' + active.name + '?')) return;
        document.getElementById('boundary-geojson').value = '';
        document.getElementById('boundary-clear-flag').value = '1';
        document.getElementById('boundary-form').submit();
    });

    document.getElementById('boundary-field').addEventListener('change', function () {
        loadField(this.value);
    });
    loadField(document.getElementById('boundary-field').value);
})();
</script>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Existing Fields</h2>
    <?php if (isset($records) && !empty($records)): ?>
        <ul class="space-y-2.5">
            <?php foreach ($records as $record): ?>
                <li class="bg-white/50 border border-white/60 rounded-xl px-3.5 sm:px-4 py-3 text-sm text-slate-700 shadow-sm flex flex-wrap items-center gap-2 justify-between">
                    <span><?php echo htmlspecialchars($record['name']); ?> (Size: <?php echo htmlspecialchars($record['size']); ?>, Soil: <?php echo htmlspecialchars($record['soil_type']); ?>)</span>
                    <span class="text-xs text-slate-500">
                        <?php if (!empty($record['computed_hectares'])): ?>
                            Boundary: <?php echo htmlspecialchars(GeoJsonHelper::formatHectares((float) $record['computed_hectares'])); ?>
                        <?php else: ?>
                            No boundary drawn
                        <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No fields registered yet.</p>
    <?php endif; ?>
</div>
