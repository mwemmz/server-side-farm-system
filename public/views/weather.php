<?php
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';
$farms       = $data['farms'] ?? [];
$forecast    = $data['forecast'] ?? [];
$farm        = $data['farm'] ?? null;
$farmId      = (int) ($data['farmId'] ?? 0);
$current     = $forecast['current'] ?? null;
$daily       = $forecast['daily'] ?? [];
$hourly      = $forecast['hourly_today'] ?? [];
$tz          = $forecast['timezone'] ?? '';
$fetchedAt   = $forecast['fetched_at'] ?? null;
$wxError     = $forecast['error'] ?? null;

$dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
function fmtDay(string $dateStr): string {
    global $dayNames;
    $ts = strtotime($dateStr);
    $today = date('Y-m-d');
    if ($dateStr === $today) return 'Today';
    if ($dateStr === date('Y-m-d', strtotime('+1 day'))) return 'Tomorrow';
    return $dayNames[date('N', $ts) % 7] . ' ' . date('d', $ts);
}
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-blue-800 flex items-center gap-2">
            ⛅ Weather &amp; Forecast
        </h1>
        <p class="text-sm text-slate-500 mt-1">Live meteorological data &amp; 7-day agricultural forecast powered by Open-Meteo.</p>
    </div>
    <form method="get" action="index.php" class="flex items-center gap-2">
        <input type="hidden" name="module" value="Weather">
        <input type="hidden" name="action" value="forecast">
        <select name="farm_id" onchange="this.form.submit()"
                class="px-3 py-2 text-sm border border-slate-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-blue-400 outline-none">
            <option value="">— Select farm —</option>
            <?php foreach ($farms as $f): ?>
            <option value="<?php echo (int)$f['id']; ?>" <?php echo $farmId === (int)$f['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($f['name']); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <a href="index.php?module=Weather&action=forecast&farm_id=<?php echo $farmId; ?>&bust=1"
           class="px-3 py-2 rounded-xl bg-blue-100 text-blue-700 text-sm font-bold hover:bg-blue-200 transition"
           title="Refresh forecast">↻</a>
    </form>
</div>

<?php if ($wxError): ?>
<div class="glass-card bg-amber-50/80 border border-amber-200 p-4 mb-6 flex items-start gap-3 text-amber-800 text-sm rounded-xl">
    <span class="text-xl">⚠️</span>
    <span><?php echo htmlspecialchars($wxError); ?></span>
</div>
<?php elseif (!$farmId): ?>
<div class="glass-card p-8 text-center text-slate-400">
    <div class="text-5xl mb-3">🌾</div>
    <p class="font-semibold">Select a farm above to load its weather forecast.</p>
</div>
<?php else: ?>

<!-- Current Conditions -->
<?php if ($current): ?>
<div class="glass-card p-5 mb-6 bg-gradient-to-br from-blue-600 to-indigo-700 text-white rounded-2xl shadow-xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-5">
            <div class="text-7xl leading-none"><?php echo $current['icon']; ?></div>
            <div>
                <div class="text-5xl font-extrabold leading-none"><?php echo round($current['temp']); ?>°C</div>
                <div class="text-lg font-semibold mt-1 opacity-90"><?php echo htmlspecialchars($current['condition']); ?></div>
                <div class="text-sm opacity-75 mt-0.5"><?php echo htmlspecialchars($farm['name'] ?? ''); ?> · <?php echo htmlspecialchars($tz); ?></div>
            </div>
        </div>
        <div class="text-right space-y-1 text-sm opacity-90">
            <div>💨 Wind: <strong><?php echo round($current['wind']); ?> km/h</strong></div>
            <?php if (isset($daily[0])): ?>
            <div>🌡️ High: <strong><?php echo round($daily[0]['temp_max']); ?>°</strong> / Low: <strong><?php echo round($daily[0]['temp_min']); ?>°</strong></div>
            <div>🌧️ Rain chance: <strong><?php echo $daily[0]['rain_chance'] ?? '—'; ?>%</strong></div>
            <div>☀️ UV index: <strong><?php echo $daily[0]['uv_index'] ?? '—'; ?></strong></div>
            <?php endif; ?>
            <?php if ($fetchedAt): ?>
            <div class="text-xs opacity-60 mt-2">Updated <?php echo htmlspecialchars($fetchedAt); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($daily[0]['farm_advice'])): ?>
    <div class="mt-4 pt-4 border-t border-white/20 text-sm font-medium">
        <?php echo htmlspecialchars($daily[0]['farm_advice']); ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 7-Day Forecast -->
<?php if (!empty($daily)): ?>
<h2 class="text-lg font-bold text-slate-800 mb-3">7-Day Forecast</h2>
<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
    <?php foreach ($daily as $i => $day): ?>
    <div class="glass-card p-3 text-center <?php echo $i === 0 ? 'ring-2 ring-blue-400' : ''; ?> rounded-xl">
        <div class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">
            <?php echo fmtDay($day['date']); ?>
        </div>
        <div class="text-3xl my-2"><?php echo $day['icon']; ?></div>
        <div class="text-xs font-semibold text-slate-600 mb-2 leading-tight">
            <?php echo htmlspecialchars($day['condition']); ?>
        </div>
        <div class="text-sm font-bold text-slate-800">
            <?php echo round($day['temp_max']); ?>° / <?php echo round($day['temp_min']); ?>°
        </div>
        <?php if ($day['precipitation'] > 0): ?>
        <div class="text-xs text-blue-600 mt-1">💧 <?php echo round($day['precipitation'], 1); ?> mm</div>
        <?php endif; ?>
        <?php if ($day['rain_chance'] !== null): ?>
        <div class="text-xs text-slate-400 mt-0.5"><?php echo $day['rain_chance']; ?>% rain</div>
        <?php endif; ?>
        <!-- Advice tooltip trigger -->
        <div class="mt-2 text-[10px] text-slate-400 leading-tight italic">
            <?php echo htmlspecialchars(substr($day['farm_advice'], 0, 60)); ?>…
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Farm advice cards for next 7 days -->
<h2 class="text-lg font-bold text-slate-800 mb-3">Agricultural Recommendations</h2>
<div class="space-y-2 mb-6">
    <?php foreach ($daily as $i => $day): ?>
    <div class="glass-card px-4 py-3 flex items-start gap-3 rounded-xl text-sm">
        <div class="text-xl shrink-0"><?php echo $day['icon']; ?></div>
        <div class="flex-1 min-w-0">
            <span class="font-bold text-slate-700"><?php echo fmtDay($day['date']); ?>:</span>
            <span class="text-slate-600 ml-1"><?php echo htmlspecialchars($day['farm_advice']); ?></span>
        </div>
        <div class="shrink-0 text-xs text-slate-400 whitespace-nowrap">
            <?php echo round($day['temp_max']); ?>°/<?php echo round($day['temp_min']); ?>°
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Hourly chart for today -->
<?php if (!empty($hourly)): ?>
<h2 class="text-lg font-bold text-slate-800 mb-3">Today — Hourly Detail</h2>
<div class="glass-card p-5 mb-6 overflow-x-auto">
    <canvas id="hourlyChart" height="90"></canvas>
</div>
<script>
(function() {
    var labels = <?php echo json_encode(array_column($hourly, 'time')); ?>;
    var temps  = <?php echo json_encode(array_column($hourly, 'temp')); ?>;
    var rain   = <?php echo json_encode(array_column($hourly, 'precipitation')); ?>;
    var hum    = <?php echo json_encode(array_column($hourly, 'humidity')); ?>;

    var ctx = document.getElementById('hourlyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    type: 'line',
                    label: 'Temp (°C)',
                    data: temps,
                    borderColor: '#f97316',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    pointRadius: 2,
                    yAxisID: 'yTemp',
                },
                {
                    type: 'bar',
                    label: 'Rain (mm)',
                    data: rain,
                    backgroundColor: 'rgba(59,130,246,0.5)',
                    yAxisID: 'yRain',
                },
                {
                    type: 'line',
                    label: 'Humidity (%)',
                    data: hum,
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    pointRadius: 0,
                    borderDash: [4,3],
                    yAxisID: 'yTemp',
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top' } },
            scales: {
                yTemp: { type: 'linear', position: 'left', title: { display: true, text: '°C / %' } },
                yRain: { type: 'linear', position: 'right', title: { display: true, text: 'mm' }, grid: { drawOnChartArea: false } }
            }
        }
    });
})();
</script>

<!-- Hourly table -->
<div class="glass-card overflow-x-auto rounded-2xl mb-6">
    <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Time</th>
                <th class="px-4 py-3">Cond.</th>
                <th class="px-4 py-3">Temp</th>
                <th class="px-4 py-3">Feels</th>
                <th class="px-4 py-3">Humidity</th>
                <th class="px-4 py-3">Wind</th>
                <th class="px-4 py-3">Rain</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php foreach ($hourly as $h): ?>
            <tr class="hover:bg-slate-50 transition">
                <td class="px-4 py-2 font-mono font-bold text-slate-700"><?php echo htmlspecialchars($h['time']); ?></td>
                <td class="px-4 py-2 text-lg"><?php echo $h['icon']; ?></td>
                <td class="px-4 py-2 font-semibold"><?php echo round($h['temp']); ?>°C</td>
                <td class="px-4 py-2 text-slate-500"><?php echo $h['feels_like'] !== null ? round($h['feels_like']).'°' : '—'; ?></td>
                <td class="px-4 py-2 text-slate-500"><?php echo $h['humidity'] !== null ? $h['humidity'].'%' : '—'; ?></td>
                <td class="px-4 py-2 text-slate-500"><?php echo round($h['wind']); ?> km/h</td>
                <td class="px-4 py-2 <?php echo $h['precipitation'] > 0 ? 'text-blue-600 font-bold' : 'text-slate-300'; ?>">
                    <?php echo $h['precipitation'] > 0 ? round($h['precipitation'], 1).' mm' : '—'; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php endif; /* end farmId check */ ?>
