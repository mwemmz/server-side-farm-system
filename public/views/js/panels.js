/* FFMS Phase 6 — control-panel client.
 * Data-driven: each panel view embeds a JSON config (#ff-panel-config)
 * describing the module and (for single-entity panels) an entity list.
 * This script fetches the initial payload then polls panel_json every 5s,
 * re-rendering the dashboard from the exact same JSON the JWT API returns.
 * Numbers animate, gauges are colour-coded, and actions POST to panel_act. */
(function () {
  'use strict';

  var cfgEl = document.getElementById('ff-panel-config');
  if (!cfgEl) return;
  var cfg = JSON.parse(cfgEl.textContent);
  var POLL_MS = cfg.pollMs || 5000;
  var timer = null;

  function apiUrl() {
    var qs = 'module=' + encodeURIComponent(cfg.module) + '&action=panel_json';
    if (cfg.entity && cfg.entity.id) qs += '&id=' + encodeURIComponent(cfg.entity.id);
    if (cfg.farmId) qs += '&farm_id=' + encodeURIComponent(cfg.farmId);
    return 'index.php?' + qs;
  }
  function actUrl(act, extra) {
    var qs = 'module=' + encodeURIComponent(cfg.module) + '&action=panel_act&act=' + encodeURIComponent(act);
    if (cfg.entity && cfg.entity.id) qs += '&id=' + encodeURIComponent(cfg.entity.id);
    if (cfg.farmId) qs += '&farm_id=' + encodeURIComponent(cfg.farmId);
    if (extra) qs += extra;
    return 'index.php?' + qs;
  }

  /* ---- tiny render helpers (Tailwind classes) ---- */
  var COLORS = {
    green: { text: 'text-emerald-700', bg: 'bg-emerald-500', chip: 'bg-emerald-100 text-emerald-800', ring: 'ring-emerald-200' },
    amber: { text: 'text-amber-700',   bg: 'bg-amber-500',   chip: 'bg-amber-100 text-amber-800',   ring: 'ring-amber-200' },
    red:   { text: 'text-red-700',     bg: 'bg-red-500',     chip: 'bg-red-100 text-red-800',       ring: 'ring-red-200' },
    slate: { text: 'text-slate-700',   bg: 'bg-slate-400',   chip: 'bg-slate-100 text-slate-700',   ring: 'ring-slate-200' }
  };
  function colorOf(c) { return COLORS[c] || COLORS.green; }

  function metric(label, value, unit, color) {
    var cc = colorOf(color);
    return '<div class="bg-white/50 border border-white/60 rounded-xl p-3.5 shadow-sm">' +
      '<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1">' + label + '</div>' +
      '<div class="flex items-baseline gap-1.5">' +
      '<span class="text-2xl font-extrabold ' + cc.text + '">' + value + '</span>' +
      (unit ? '<span class="text-xs text-slate-500 font-medium">' + unit + '</span>' : '') +
      '</div></div>';
  }

  function bar(label, value, unit, color, pct, sub) {
    var cc = colorOf(color);
    pct = Math.max(0, Math.min(100, pct));
    return '<div class="bg-white/50 border border-white/60 rounded-xl p-3.5 shadow-sm">' +
      '<div class="flex justify-between items-center mb-1.5">' +
      '<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">' + label + '</div>' +
      '<div class="text-sm font-bold ' + cc.text + '">' + value + ' <span class="text-[10px] font-medium text-slate-400">' + unit + '</span></div>' +
      '</div>' +
      '<div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">' +
      '<div class="h-full rounded-full ' + cc.bg + ' transition-all duration-700" style="width:' + pct + '%"></div>' +
      '</div>' +
      (sub ? '<div class="text-[11px] text-slate-500 mt-1.5">' + sub + '</div>' : '') +
      '</div>';
  }

  function badge(text, color) {
    var cc = colorOf(color);
    return '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold ' + cc.chip + '">' +
      '<span class="w-1.5 h-1.5 rounded-full ' + cc.bg + '"></span>' + text + '</span>';
  }

  function banner(level, message) {
    if (!message) return '';
    var cc = colorOf(level);
    return '<div class="mb-4 rounded-xl border px-4 py-3 flex items-start gap-3 ' + cc.chip + '">' +
      '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">' +
      '<path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>' +
      '<span class="text-sm font-medium">' + message + '</span></div>';
  }

  function selector(options, currentId, onChange) {
    var keys = Object.keys(options);
    if (keys.length <= 1) return '';
    var opts = keys.map(function (k) {
      var sel = String(k) === String(currentId) ? ' selected' : '';
      return '<option value="' + k + '"' + sel + '>' + escapeHtml(options[k]) + '</option>';
    }).join('');
    return '<div class="glass-card p-3 sm:p-4 mb-5 flex flex-wrap items-center gap-3">' +
      '<span class="text-sm font-semibold text-slate-600">Viewing:</span>' +
      '<select id="ff-entity-select" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm font-medium text-slate-700">' + opts + '</select>' +
      '<span class="text-xs text-slate-400">selecting switches the live panel</span></div>';
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---- module renderers ---- */
  var renderers = {};

  renderers.Irrigation = function (d) {
    var r = d.reservoir, p = d.pump, f = d.flow_rate, u = d.usage_today, m = d.moisture || [], next = d.next_schedule, rec = d.recommendation;
    var zones = m.map(function (z) {
      return '<div class="bg-white/50 border border-white/60 rounded-xl p-3.5 shadow-sm">' +
        '<div class="flex justify-between items-center mb-1">' +
        '<span class="text-xs font-semibold text-slate-600">' + escapeHtml(z.name) + '</span>' +
        badge(Math.round(z.moisture) + '% moisture', z.color) + '</div>' +
        '<div class="h-2 rounded-full bg-slate-100 overflow-hidden">' +
        '<div class="h-full rounded-full ' + colorOf(z.color).bg + ' transition-all duration-700" style="width:' + Math.max(0, Math.min(100, z.moisture)) + '%"></div></div>' +
        '</div>';
    }).join('');
    var pumpBtn = '<button data-act="pump" data-on="' + (p.on ? 0 : 1) + '" class="px-4 py-2 rounded-xl text-sm font-bold text-white shadow-lg transition-all duration-200 ' +
      (p.on ? 'bg-red-500 hover:bg-red-600' : 'bg-emerald-600 hover:bg-emerald-700') + '">' +
      (p.on ? 'Turn Pump OFF' : 'Turn Pump ON') + '</button>';
    var refillBtn = '<button data-act="refill" class="px-4 py-2 rounded-xl text-sm font-bold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 shadow-sm">Refill Reservoir</button>';
    return banner((r.pct < 20 ? 'red' : 'green'), r.pct < 20 ? 'Reservoir critically low — refill soon.' : (p.on ? 'Irrigation active — monitoring flow & moisture.' : 'System idle.') )
      + '<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">'
      + bar('Reservoir', Math.round(r.current).toLocaleString(), r.unit, r.color, r.pct, Math.round(r.pct) + '% of capacity')
      + metric('Flow rate', Math.round(f.value * 10) / 10, f.unit, f.value > 0 ? 'green' : 'slate')
      + metric('Used today', Math.round(u.value).toLocaleString(), u.unit, 'slate')
      + '<div class="bg-white/50 border border-white/60 rounded-xl p-3.5 shadow-sm flex flex-col justify-between">' +
        '<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-2">Controls</div>' +
        '<div class="flex flex-col gap-2">' + pumpBtn + refillBtn + '</div></div>'
      + '</div>'
      + '<div class="glass-card p-4 sm:p-5 mb-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Field Soil Moisture</h3>' +
      (zones ? '<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">' + zones + '</div>' : '<p class="text-sm text-slate-500">No fields mapped to this system\'s farm yet.</p>') + '</div>'
      + '<div class="grid grid-cols-1 md:grid-cols-2 gap-5">'
      + '<div class="glass-card p-4 sm:p-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Next Irrigation Slot</h3>' +
      (next ? '<div class="text-sm text-slate-600"><span class="font-semibold">' + escapeHtml(next.field_name || 'Field') + '</span><br>' +
        '<span class="text-slate-500">' + escapeHtml(next.schedule_date) + ' · ' + Number(next.liters).toLocaleString() + ' L</span><br>' +
        badge('scheduled', 'green') + '</div>' : '<p class="text-sm text-slate-500">No upcoming irrigation scheduled.</p>') + '</div>'
      + '<div class="glass-card p-4 sm:p-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Active Recommendation</h3>' +
      (rec ? '<div class="text-sm text-slate-600"><span class="font-semibold">' + escapeHtml(rec.field_name || 'Field') + '</span>' +
        (rec.recommended_liters ? ' — ' + Number(rec.recommended_liters).toLocaleString() + ' L' : '') + '<br>' +
        '<span class="text-slate-500">' + escapeHtml(rec.reason || '') + '</span><br>' +
        badge('pending', 'amber') + '</div>' : '<p class="text-sm text-slate-500">No active irrigation recommendation.</p>') + '</div>'
      + '</div>';
  };

  renderers.Storage = function (d) {
    if (d.empty) return '<p class="text-slate-500">' + escapeHtml(d.message || 'No data') + '</p>';
    var f = d.facility, t = d.temperature, h = d.humidity, risk = d.spoilage_risk;
    var cols = (d.contents || []).map(function (c) {
      return '<li class="bg-white/50 border border-white/60 rounded-xl px-3.5 py-2.5 text-sm text-slate-700 shadow-sm flex justify-between">' +
        '<span>' + escapeHtml(c.product_name) + (c.grade ? ' <span class="text-[10px] uppercase text-slate-400">' + escapeHtml(c.grade) + '</span>' : '') + '</span>' +
        '<span class="font-semibold text-slate-500">' + Number(c.quantity || 0).toLocaleString() + '</span></li>';
    }).join('');
    return banner(risk.level === 'red' ? 'red' : risk.level, risk.value >= 75 ? 'Spoilage risk high — act now.' : null)
      + '<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">'
      + bar('Capacity used', Math.round(f.current_stock).toLocaleString(), f.units, f.capacity_color, f.utilization_pct, Math.round(f.utilization_pct) + '% full')
      + metric('Temperature', t.value, t.unit, t.color)
      + metric('Humidity', h.value, h.unit, h.color)
      + '<div class="bg-white/50 border border-white/60 rounded-xl p-3.5 shadow-sm"><div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1">Spoilage risk</div>' +
      '<div class="flex items-center gap-2">' + badge(risk.value + '%', risk.level) + '<span class="text-xs text-slate-400">trend ' + t.trend + '</span></div></div>'
      + '</div>'
      + '<div class="glass-card p-4 sm:p-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Stored Produce</h3>' +
      (cols ? '<ul class="space-y-2">' + cols + '</ul>' : '<p class="text-sm text-slate-500">No stored produce recorded for this facility.</p>') + '</div>';
  };

  renderers.Equipment = function (d) {
    var e = d.equipment, fuel = d.fuel, temp = d.engine_temp, hours = d.hours_today;
    var runBtn = '<button data-act="' + (e.state === 'running' ? 'park' : 'run') + '" data-on="' + (e.state === 'running' ? 0 : 1) + '" class="px-4 py-2 rounded-xl text-sm font-bold text-white shadow-lg transition-all duration-200 ' +
      (e.state === 'running' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-600 hover:bg-emerald-700') + '">' +
      (e.state === 'running' ? 'Park Engine' : 'Start Engine') + '</button>';
    return banner(d.alert ? d.alert.level : null, d.alert ? d.alert.message : null)
      + '<div class="flex items-center gap-3 mb-5">' + badge(e.state, e.state === 'running' ? 'green' : 'slate') +
      (d.service_due ? badge('service due', 'red') : '') + '</div>'
      + '<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">'
      + bar('Fuel', Math.round(fuel.value), fuel.unit, fuel.color, fuel.value)
      + metric('Engine temp', temp.value, temp.unit, temp.color)
      + metric('Run hours today', hours.value, hours.unit, 'slate')
      + '</div>'
      + '<div class="glass-card p-4 sm:p-5 flex items-center gap-3"><span class="text-sm font-semibold text-slate-600">Engine control:</span>' + runBtn + '</div>';
  };

  renderers.Livestock = function (d) {
    var s = d.summary, env = d.environment, h = d.health_index;
    var types = Object.keys(s.by_type).map(function (k) {
      return '<li class="bg-white/50 border border-white/60 rounded-xl px-3.5 py-2.5 text-sm text-slate-700 shadow-sm flex justify-between"><span>' + escapeHtml(k) + '</span><span class="font-bold text-slate-500">' + s.by_type[k] + '</span></li>';
    }).join('');
    return banner(d.alert ? d.alert.level : null, d.alert ? d.alert.message : null)
      + '<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-5">'
      + metric('Herd total', s.total, 'animals', 'slate')
      + metric('Barn temp', env.temperature.value, env.temperature.unit, env.temperature.color)
      + metric('Barn humidity', env.humidity.value, env.humidity.unit, env.humidity.color)
      + '<div class="bg-white/50 border border-white/60 rounded-xl p-3.5 shadow-sm"><div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-1">Herd health</div>' +
      '<div class="flex items-center gap-2">' + badge(h.value + '%', h.color) + '</div></div>'
      + '</div>'
      + '<div class="grid grid-cols-1 md:grid-cols-2 gap-5">'
      + '<div class="glass-card p-4 sm:p-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Herd by type</h3>' +
      (types ? '<ul class="space-y-2">' + types + '</ul>' : '<p class="text-sm text-slate-500">No livestock recorded.</p>') + '</div>'
      + '<div class="glass-card p-4 sm:p-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Supplies</h3>' +
      bar('Feed', env.feed.value, env.feed.unit, env.feed.color, env.feed.value) +
      '<div class="h-5"></div>' + bar('Water', env.water.value, env.water.unit, env.water.color, env.water.value) + '</div>'
      + '</div>';
  };

  renderers.Weather = function (d) {
    if (d.empty) return '<p class="text-slate-500">' + escapeHtml(d.message || 'No data') + '</p>';
    var c = d.current, trend = d.trend, comfort = d.comfort_index, irr = d.irrigation_suitability;
    var trendBadge = trend === 'warming' ? badge('warming', 'amber') : (trend === 'cooling' ? badge('cooling', 'green') : badge('steady', 'slate'));
    var hist = (d.history || []).map(function (r) {
      return '<div class="text-xs text-slate-500 flex justify-between border-b border-white/40 py-1.5"><span>' + escapeHtml(r.weather_date) + '</span><span>' + Number(r.temperature).toFixed(1) + '°C / ' + Number(r.humidity).toFixed(0) + '%RH' + (r.rainfall_mm ? ' / ' + Number(r.rainfall_mm).toFixed(1) + 'mm' : '') + '</span></div>';
    }).join('');
    return '<div class="glass-card p-4 sm:p-6 mb-5 flex flex-wrap items-center gap-4">' +
      '<div class="text-4xl font-extrabold text-slate-800">' + c.temperature.value + '<span class="text-lg text-slate-500">°C</span></div>' +
      '<div class="flex flex-col gap-2 text-sm text-slate-600">' +
      '<span class="flex items-center gap-2">Humidity <strong>' + c.humidity.value + '%</strong></span>' +
      '<span class="flex items-center gap-2">Rain <strong>' + c.rainfall_mm.value + ' mm</strong></span>' +
      '<span>Trend ' + trendBadge + '</span></div>' +
      '<div class="ml-auto flex flex-col items-end gap-2">' + badge('Comfort ' + comfort.value + '%', comfort.color) + badge(irr.label, irr.color) + '</div></div>'
      + '<div class="grid grid-cols-1 md:grid-cols-2 gap-5">'
      + '<div class="glass-card p-4 sm:p-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Irrigation guidance</h3><p class="text-sm text-slate-600">' + escapeHtml(irr.value) + '</p></div>'
      + '<div class="glass-card p-4 sm:p-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Recent readings</h3>' +
      (hist ? '<div>' + hist + '</div>' : '<p class="text-sm text-slate-500">No readings.</p>') + '</div>'
      + '</div>';
  };

  renderers.Finance = function (d) {
    var s = d.summary, cf = d.cashflow || [], te = d.top_expenses || [], ti = d.top_income || [];
    var cashRows = cf.map(function (m) {
      var n = Number(m.net);
      return '<div class="flex justify-between text-sm py-1.5 border-b border-white/40"><span class="text-slate-500">' + escapeHtml(String(m.month)) + '</span>' +
        '<span class="font-semibold ' + (n >= 0 ? 'text-emerald-700' : 'text-red-700') + '">' + (n >= 0 ? '+' : '') + Number(n).toLocaleString() + '</span></div>';
    }).join('');
    function catRows(list) {
      return (list && list.length ? list.map(function (c) {
        return '<li class="bg-white/50 border border-white/60 rounded-xl px-3.5 py-2.5 text-sm text-slate-700 shadow-sm flex justify-between"><span>' + escapeHtml(c.label) + '</span><span class="font-semibold text-slate-500">' + Number(c.amount).toLocaleString() + '</span></li>';
      }).join('') : '<p class="text-sm text-slate-500">No data</p>');
    }
    return '<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">'
      + metric('Income (month)', Number(s.month_income).toLocaleString(), 'ZMW', 'green')
      + metric('Expenses (month)', Number(s.month_expense).toLocaleString(), 'ZMW', 'red')
      + metric('Net (month)', (s.month_net >= 0 ? '+' : '') + Number(s.month_net).toLocaleString(), 'ZMW', s.month_net_color)
      + '</div>'
      + '<div class="glass-card p-4 sm:p-5 mb-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Cash flow (year)</h3>' +
      (cashRows ? '<div>' + cashRows + '</div>' : '<p class="text-sm text-slate-500">No cash flow data.</p>') + '</div>'
      + '<div class="grid grid-cols-1 md:grid-cols-2 gap-5">'
      + '<div class="glass-card p-4 sm:p-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Top income sources</h3><ul class="space-y-2">' + catRows(ti) + '</ul></div>'
      + '<div class="glass-card p-4 sm:p-5"><h3 class="text-sm font-bold text-slate-700 mb-3">Top spending</h3><ul class="space-y-2">' + catRows(te) + '</ul></div>'
      + '</div>';
  };

  var renderer = renderers[cfg.module] || function (d) { return '<pre class="text-xs">' + escapeHtml(JSON.stringify(d, null, 2)) + '</pre>'; };

  var mount = document.getElementById('ff-panel-mount');
  var status = document.getElementById('ff-panel-status');

  function render(data) {
    mount.innerHTML = renderer(data);
    mount.querySelectorAll('[data-act]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var act = btn.getAttribute('data-act');
        var on = btn.getAttribute('data-on');
        var extra = '';
        if (on !== null) extra += '&on=' + encodeURIComponent(on);
        if (act === 'run' || act === 'park') extra += '&running=' + encodeURIComponent(on);
        fetch(actUrl(act, extra), { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (res) { if (res.success && res.data) { render(res.data); refreshStatus(); } })
          .catch(function () {});
      });
    });
  }

  function refreshStatus() {
    if (status) status.textContent = 'Live · updated ' + new Date().toLocaleTimeString();
  }

  function load() {
    fetch(apiUrl()).then(function (r) { return r.json(); })
      .then(function (res) { if (res.success && res.data) { render(res.data); refreshStatus(); } })
      .catch(function () {});
  }

  /* entity selector (single-entity modules) */
  var sel = document.getElementById('ff-entity-select');
  if (sel) {
    sel.addEventListener('change', function () {
      cfg.entity.id = sel.value;
      load();
    });
  }

  load();
  timer = setInterval(load, POLL_MS);
})();
