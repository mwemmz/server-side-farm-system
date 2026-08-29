<?php
// Market Analysis & Price Prediction — interactive "what to plant" tool.
// Part of the Predictive Analytics / BI layer. All data is loaded client-side
// from the same market & sales records the system already tracks.
$months = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-1 text-green-800 flex items-center gap-3">
    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" x2="16.65" y1="21" y2="16.65"/><line x1="8" x2="14" y1="11" y2="11"/></svg>
    Market Analysis &amp; Price Prediction
</h1>
<p class="text-sm text-slate-500 mb-6">Pick a crop and when you plan to plant, and see what the market is likely to pay at harvest before you commit land and inputs. Reads from your own price &amp; sales history.</p>

<!-- Selector -->
<div class="glass-card p-5 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-4 items-end">
        <div class="flex flex-col gap-1">
            <label class="text-sm font-semibold text-slate-700">Crop</label>
            <select id="ma-crop" class="w-full px-3.5 py-2.5 text-sm text-slate-800 bg-white/70 border border-slate-200 rounded-lg focus:ring-2 focus:ring-green-500"></select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-sm font-semibold text-slate-700">Planned planting month</label>
            <select id="ma-plant" class="w-full px-3.5 py-2.5 text-sm text-slate-800 bg-white/70 border border-slate-200 rounded-lg focus:ring-2 focus:ring-green-500">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m === 2 ? 'selected' : ''; ?>><?php echo $months[$m]; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button id="ma-go" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition">
            Generate report
        </button>
    </div>
</div>

<!-- Loading / results -->
<div id="ma-status" class="hidden glass-card p-5 mb-6 text-center text-slate-500 text-sm">Analysing market history…</div>

<!-- Decision report -->
<div id="ma-verdict" class="hidden mb-6"></div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
    <!-- Price history chart -->
    <div class="glass-card p-5">
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-lg font-bold text-slate-700">Price History — <span id="ma-chart-title">Tomato</span></h2>
            <span class="text-[10px] font-bold uppercase text-slate-400">12–24 months</span>
        </div>
        <p class="text-xs text-slate-500 mb-3">Average price per kg by month. Lines: <span class="font-semibold" style="color:#059669">historical</span> · <span class="font-semibold" style="color:#f59e0b">harvest window</span>.</p>
        <div id="ma-chart" class="w-full"></div>
        <p id="ma-chart-empty" class="hidden text-sm text-slate-400 py-8 text-center">No price history recorded for this crop yet.</p>
    </div>

    <!-- Seasonal pattern -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Seasonal Pattern</h2>
        <div id="ma-seasonal" class="text-sm text-slate-600 space-y-2"></div>
        <div class="mt-4 grid grid-cols-12 gap-1" id="ma-seasonal-bars"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
    <!-- Prediction -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Price Prediction <span id="ma-pred-sub" class="text-slate-400 font-semibold"></span></h2>
        <div id="ma-prediction" class="space-y-3 text-sm text-slate-600"></div>
    </div>

    <!-- Demand estimation -->
    <div class="glass-card p-5">
        <h2 class="text-lg font-bold text-slate-700 mb-3">Market Demand Estimation</h2>
        <div id="ma-demand" class="space-y-3 text-sm text-slate-600"></div>
    </div>
</div>

<style>
.ma-bar:hover { filter: brightness(1.1); }
.ma-bar-label { font-size: 9px; fill: #64748b; text-anchor: middle; }
.ma-month { font-size: 9px; fill: #94a3b8; text-anchor: middle; }
</style>

<script>
(function () {
    var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    function fm(n) {
        n = Number(n) || 0;
        var neg = n < 0 ? '-' : ''; n = Math.abs(n);
        var intPart = Math.floor(n), dec = Math.round((n - intPart) * 100);
        if (dec === 100) { intPart++; dec = 0; }
        var s = String(intPart).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return 'K' + neg + s + '.' + (dec < 10 ? '0' : '') + dec;
    }

    var cropSel = document.getElementById('ma-crop');
    var plantSel = document.getElementById('ma-plant');
    var goBtn = document.getElementById('ma-go');
    var status = document.getElementById('ma-status');
    var verdictBox = document.getElementById('ma-verdict');
    var cropJson = []; // [{name}]

    function esc(s) { return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function setStatus(msg, show) { status.textContent = msg || ''; status.classList.toggle('hidden', !show); }

    function load() {
        var crop = cropSel.value || 'Tomato';
        var plant = plantSel.value || '2';
        setStatus('Analysing market history…', true);
        verdictBox.classList.add('hidden');
        fetch('index.php?module=Insights&action=market_json&crop=' + encodeURIComponent(crop) + '&plant_month=' + encodeURIComponent(plant))
            .then(function (r) { return r.json(); })
            .then(function (res) {
                setStatus(false, false);
                if (!res.success) return;
                var d = res.data;
                // Populate crop select on first load.
                if (!cropSel.options.length) {
                    (d.crops || []).forEach(function (c) {
                        var o = document.createElement('option');
                        o.value = c; o.textContent = c;
                        if (String(c).toLowerCase() === String(d.crop || 'Tomato').toLowerCase()) o.selected = true;
                        cropSel.appendChild(o);
                    });
                }
                document.getElementById('ma-chart-title').textContent = d.crop;
                renderChart(d.history || [], d.report ? d.report.harvest_label : '');
                renderSeasonal(d.report ? d.report.seasonal : null);
                renderPrediction(d.report ? d.report.prediction : null);
                renderDemand(d.report ? d.report.demand : null);
                renderVerdict(d.report);
            })
            .catch(function () { setStatus('Could not load analysis — try again.', true); });
    }

    function renderChart(history, harvestLabel) {
        var box = document.getElementById('ma-chart');
        var empty = document.getElementById('ma-chart-empty');
        box.innerHTML = ''; empty.classList.add('hidden');
        if (!history || !history.length) { empty.classList.remove('hidden'); return; }
        var W = 560, H = 220, padL = 46, padR = 14, padT = 18, padB = 26;
        var iw = W - padL - padR, ih = H - padT - padB;
        var prices = history.map(function (h) { return h.price; });
        var min = Math.min.apply(null, prices), max = Math.max.apply(null, prices);
        var span = (max - min) || 1; min = min - span * 0.1; max = max + span * 0.1;
        var x = function (i) { return padL + (history.length > 1 ? (i / (history.length - 1)) * iw : iw / 2); };
        var y = function (v) { return padT + ih - ((v - min) / (max - min)) * ih; };
        // Highlight harvest months (from the label, e.g. "Apr–May").
        var parts = (harvestLabel || '').split('–');
        var h1 = MONTHS.indexOf(parts[0]) + 1, h2 = MONTHS.indexOf(parts[1]) + 1;
        var hMonths = [];
        var mm = h1; while (true) { hMonths.push(mm); if (mm === h2) break; mm = (mm % 12) + 1; if (hMonths.length > 3) break; }

        var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" class="w-full" role="img" aria-label="Price history">';
        // Grid + harvest band.
        for (var g = 0; g <= 4; g++) {
            var gy = padT + ih * (g / 4);
            var val = max - ((max - min) * (g / 4));
            svg += '<line x1="' + padL + '" y1="' + gy + '" x2="' + (W - padR) + '" y2="' + gy + '" stroke="#e2e8f0" stroke-width="1"/>';
            svg += '<text x="' + (padL - 6) + '" y="' + (gy + 3) + '" text-anchor="end" class="ma-bar-label">' + Number(val).toFixed(1) + '</text>';
        }
        if (hMonths.length) {
            var xs = [];
            history.forEach(function (h, i) { if (hMonths.indexOf(h.month) >= 0) xs.push(x(i)); });
            if (xs.length > 1) {
                svg += '<rect x="' + xs[0] + '" y="' + padT + '" width="' + (xs[xs.length-1] - xs[0]) + '" height="' + ih + '" fill="#f59e0b" fill-opacity="0.12"/>';
            }
        }
        // Line + points.
        var path = history.map(function (h, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(h.price).toFixed(1); }).join(' ');
        svg += '<polyline points="' + history.map(function (h, i) { return x(i).toFixed(1) + ',' + y(h.price).toFixed(1); }).join(' ') + '" fill="none" stroke="#059669" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';
        history.forEach(function (h, i) {
            svg += '<circle cx="' + x(i).toFixed(1) + '" cy="' + y(h.price).toFixed(1) + '" r="3" fill="#059669"/>';
            var lab = h.year % 10 + '' + String(h.month).padStart(2, '0');
            svg += '<text x="' + x(i).toFixed(1) + '" y="' + (H - 8) + '" class="ma-month">' + MONTHS[h.month - 1] + '</text>';
        });
        svg += '<g>';
        history.forEach(function (h, i) {
            svg += '<title>' + MONTHS[h.month - 1] + ' ' + h.year + ': ' + fm(h.price) + '/kg</title>';
        });
        svg += '</g></svg>';
        box.innerHTML = svg;
    }

    function renderSeasonal(s) {
        var box = document.getElementById('ma-seasonal');
        var bars = document.getElementById('ma-seasonal-bars');
        if (!s || !s.available) {
            box.innerHTML = '<p class="text-slate-400">No seasonal pattern available yet.</p>';
            bars.innerHTML = '';
            return;
        }
        var html = '';
        html += '<div class="flex justify-between"><span>Historical average</span><b>' + fm(s.mean) + '/kg</b></div>';
        html += '<div class="flex justify-between"><span>Peak (highest price) months</span><b class="text-green-700">' + esc(s.peak_months) + '</b></div>';
        html += '<div class="flex justify-between"><span>Low (glut) months</span><b class="text-red-600">' + esc(s.trough_months) + '</b></div>';
        html += '<div class="flex justify-between"><span>Price volatility</span><b>' + s.volatility + '%</b></div>';
        box.innerHTML = html + '<div class="pt-2"><span class="text-xs font-bold text-slate-500">Influencing factors</span><ul class="list-disc pl-5 mt-1 space-y-1 text-xs text-slate-500">' +
            (s.factors || []).map(function (f) { return '<li>' + esc(f) + '</li>'; }).join('') + '</ul></div>';

        // Month bars.
        var profile = s.profile || {};
        var vals = Object.keys(profile).map(function (k) { return profile[k] || 0; });
        var mx = Math.max.apply(null, vals.concat([0.01]));
        var bhtml = '<div class="col-span-12 text-[10px] text-slate-400 mb-1">Average price by month (relative)</div>';
        for (var m = 1; m <= 12; m++) {
            var v = profile[m];
            var h = v ? Math.max(4, Math.round((v / mx) * 80)) : 2;
            bhtml += '<div class="flex flex-col items-center justify-end gap-1" style="height:92px">' +
                '<span class="text-[9px] font-bold ' + (v ? 'text-slate-600' : 'text-slate-300') + '">' + (v ? v.toFixed(1) : '–') + '</span>' +
                '<div class="ma-bar rounded-t ' + (v ? 'bg-emerald-500' : 'bg-slate-200') + '" style="height:' + h + 'px;width:100%"></div>' +
                '<span class="text-[9px] text-slate-400">' + MONTHS[m - 1] + '</span></div>';
        }
        bars.innerHTML = bhtml;
    }

    function renderPrediction(p) {
        var box = document.getElementById('ma-prediction');
        if (!p) { box.innerHTML = '<p class="text-slate-400">Prediction unavailable.</p>'; return; }
        document.getElementById('ma-pred-sub').textContent = 'for ' + p.harvest_label + ' harvest';
        var html = '';
        html += '<div class="flex items-center gap-4 flex-wrap">' +
            '<div class="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2 text-center"><div class="text-[10px] font-bold uppercase text-slate-500">Expected</div><div class="text-2xl font-extrabold text-emerald-700">' + fm(p.point) + '</div><div class="text-[10px] text-slate-500">/kg</div></div>' +
            '<div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-2 text-center"><div class="text-[10px] font-bold uppercase text-slate-500">Range</div><div class="text-lg font-extrabold text-amber-700">' + fm(p.low) + ' – ' + fm(p.high) + '</div><div class="text-[10px] text-slate-500">/kg</div></div>' +
            '<div class="bg-sky-50 border border-sky-200 rounded-xl px-4 py-2 text-center"><div class="text-[10px] font-bold uppercase text-slate-500">Confidence</div><div class="text-lg font-extrabold text-sky-700">' + p.confidence + '%</div></div>' +
            '</div>';
        html += '<div class="bg-white/50 border border-white/60 rounded-lg px-3 py-2 text-xs text-slate-500"><b class="text-slate-700">Method used:</b> ' + esc(p.method) + '.<br>Short-run trend ' + (p.trend_per_month >= 0 ? '+' : '') + 'K' + p.trend_per_month + ' per month, volatility ' + p.volatility_pct + '%, based on ' + p.data_months + ' months of data.</div>';
        box.innerHTML = html;
    }

    function renderDemand(dm) {
        var box = document.getElementById('ma-demand');
        if (!dm) { box.innerHTML = '<p class="text-slate-400">Demand data unavailable.</p>'; return; }
        var html = '<div class="flex items-center gap-2">Expect market demand <b class="px-2 py-0.5 rounded-full ' +
            (dm.level === 'High' ? 'bg-emerald-100 text-emerald-800' : (dm.level === 'Low' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800')) + '">' + esc(dm.level) + '</b></div>';
        html += '<div class="grid grid-cols-2 gap-2 text-xs">';
        var dmonths = Object.keys(dm.harvest_months || {}).map(function (k) { return dm.harvest_months[k]; });
        dmonths.forEach(function (mm) {
            html += '<div class="bg-white/50 border border-white/60 rounded-lg px-3 py-2"><b>' + esc(mm.month) + '</b><div>Sales: ' + fm(mm.sales) + '</div><div>Buyers: ' + mm.buyers + '</div></div>';
        });
        html += '</div>';
        html += '<div class="text-xs text-slate-500">' + esc(dm.note) + '</div>';
        box.innerHTML = html;
    }

    function verdictColor(c) { return {green:'from-green-600 to-emerald-600', amber:'from-amber-500 to-orange-600', red:'from-rose-500 to-red-600'}[c] || 'from-slate-600 to-slate-700'; }

    function renderVerdict(r) {
        if (!r) { return; }
        var icon = r.color === 'green' ? '&#10003;' : (r.color === 'red' ? '&#10007;' : '&#9888;');
        var html = '<div class="glass-card p-6">' +
            '<div class="flex items-center gap-4 flex-wrap">' +
            '<div class="w-14 h-14 rounded-2xl bg-gradient-to-br ' + verdictColor(r.color) + ' flex items-center justify-center text-white text-2xl font-extrabold shadow-lg">' + icon + '</div>' +
            '<div><div class="text-xs font-bold uppercase tracking-wide text-slate-400">Decision — ' + esc(r.crop) + ' planted in ' + esc(r.plant_month_label) + '</div>' +
            '<div class="text-xl font-extrabold text-slate-800">' + esc(r.verdict_label) + '</div>' +
            '<div class="text-xs text-slate-500">Harvest window: ' + esc(r.harvest_label) + '</div></div></div>' +
            '<div class="mt-4 text-sm text-slate-600">' + esc(r.reasoning) + '</div>' +
            '<div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-center">' +
            '<div class="bg-white/50 border border-white/60 rounded-xl py-3"><div class="text-[10px] font-bold uppercase text-slate-500">Expected price range</div><div class="text-lg font-extrabold text-slate-800">' + fm(r.prediction.low) + ' – ' + fm(r.prediction.high) + '</div><div class="text-[10px] text-slate-500">/kg</div></div>' +
            '<div class="bg-white/50 border border-white/60 rounded-xl py-3"><div class="text-[10px] font-bold uppercase text-slate-500">Break-even</div><div class="text-lg font-extrabold text-slate-800">' + fm(r.break_even) + '</div><div class="text-[10px] text-slate-500">est. cost /kg</div></div>' +
            '<div class="bg-white/50 border border-white/60 rounded-xl py-3"><div class="text-[10px] font-bold uppercase text-slate-500">Price crash risk</div><div class="text-lg font-extrabold ' + (r.crash_risk === 'High' ? 'text-rose-600' : 'text-emerald-700') + '">' + esc(r.crash_risk) + '</div></div>' +
            '</div>' +
            '<h3 class="text-sm font-bold text-slate-600 mt-4 mb-1.5">Key risks</h3><ul class="list-disc pl-5 space-y-1 text-xs text-slate-500">' +
            (r.risks || []).map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') +
            '</ul></div>';
        verdictBox.innerHTML = html;
        verdictBox.classList.remove('hidden');
    }

    goBtn.addEventListener('click', load);
    cropSel.addEventListener('change', load);
    plantSel.addEventListener('change', load);
    load();
})();
</script>
