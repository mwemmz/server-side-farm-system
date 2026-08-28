<?php
/**
 * Generic module landing — replaces the "starts with a form" CRUD page for the
 * form-first modules. Each module gets a relevant summary strip + the records
 * as glass cards, with CRUD (form) demoted behind an "Add / Manage" button that
 * routes to ?module=X&action=manage (renders the original view).
 *
 * Expected: $module (current module name), $data (index() record list), and the
 * app's $pdo / $config globals are in scope.
 */
require_once __DIR__ . '/../../config/env.php';
ini_set('display_errors', '1'); error_reporting(E_ALL); // TEMP DIAGNOSTIC
$recs = $data ?? [];
$m    = isset($module) ? $module : ($_GET['module'] ?? 'Module');

function esc($v) { return htmlspecialchars($v ?? ''); }

// ---- per-module configuration: title, subtitle, add link ----
$title = 'Overview';
$subtitle = '';
$addLink = 'index.php?module=' . urlencode($m) . '&action=manage';
$summaries = []; // array of [label, value, color?] → stat cards BEFORE the list
$render = '';    // HTML for the record cards

switch ($m) {
    case 'Crop':
        $title = 'Crop Dashboard'; $subtitle = 'Grow your season — track plantings and harvest windows.';
        $active = 0; $upcoming = 0;
        foreach ($recs as $c) {
            $active++;
            if (!empty($c['expected_harvest_date']) && strtotime((string) $c['expected_harvest_date']) >= strtotime('today')) $upcoming++;
        }
        $summaries = [['Active crops', $active, 'green'], ['Harvest due', $upcoming, 'amber'], ['Total fields', (int) $pdo->query('SELECT COUNT(*) FROM fields')->fetchColumn(), 'slate']];
        foreach ($recs as $c) {
            $g = $c['expected_harvest_date'] ? ((strtotime((string) $c['expected_harvest_date']) < strtotime('today')) ? ['Overdue', 'red'] : ['On track', 'green']) : ['—', 'slate'];
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="flex justify-between items-start"><div class="text-base font-bold text-slate-800">' . esc($c['name']) . '</div><span class="text-[10px] font-bold uppercase px-2 py-1 rounded-full bg-'.(($g[1]==='red')?'red':'emerald').'-100 text-'.(($g[1]==='red')?'red':'emerald').'-800">'.$g[0].'</span></div>'
                . '<div class="text-xs text-slate-500 mt-0.5">' . esc($c['variety'] ?? '') . ' · Farm #' . esc($c['farm_id'] ?? '—') . '</div>'
                . '<div class="mt-2 text-xs text-slate-500 flex gap-4"><span>Planted <strong>' . esc($c['planting_date'] ?? '—') . '</strong></span><span>Harvest <strong>' . esc($c['expected_harvest_date'] ?? '—') . '</strong></span></div></div>';
        }
        break;

    case 'Inventory':
        $title = 'Inventory Dashboard'; $subtitle = 'Live stock levels and reorder status.';
        $totQty = 0; $low = 0; $totalItems = count($recs);
        foreach ($recs as $i) { $q = (int) ($i['quantity'] ?? 0); $totQty += $q; if ($q <= 10) $low++; }
        $summaries = [['Items tracked', $totalItems, 'slate'], ['Total units', $totQty, 'green'], ['Low stock', $low, ($low ? 'red' : 'green')]];
        foreach ($recs as $i) {
            $q = (int) ($i['quantity'] ?? 0); $ok = $q > 10;
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="flex justify-between items-center"><div class="text-base font-bold text-slate-800">' . esc($i['name']) . '</div>'
                . '<span class="text-sm font-extrabold '.($ok?'text-emerald-700':'text-red-700').'">'.$q.' <span class="text-[10px] font-medium text-slate-400">'.esc($i['unit'] ?? '').'</span></span></div>'
                . ($ok ? '' : '<div class="mt-2 text-[11px] font-semibold text-red-600">⚠ Low stock — consider reorder</div>') . '</div>';
        }
        break;

    case 'Labour':
        $title = 'Labour Dashboard'; $subtitle = 'Workforce on the ground this season.';
        $roles = [];
        foreach ($recs as $l) { $r = $l['role'] ?: 'General'; $roles[$r] = ($roles[$r] ?? 0) + 1; }
        arsort($roles);
        $summaries = [['Workforce', count($recs), 'green'], ['Roles', count($roles), 'slate']];
        foreach ($roles as $r => $n) $summaries[] = [$r, $n, 'amber'];
        foreach ($recs as $l) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="text-base font-bold text-slate-800">' . esc($l['name']) . '</div>'
                . '<div class="text-xs text-slate-500 mt-0.5">' . esc($l['role'] ?? '') . ' · ' . esc($l['attendance_date'] ?? '') . '</div></div>';
        }
        break;

    case 'Pest':
        $title = 'Pest Management Dashboard'; $subtitle = 'Monitor detections and control actions.';
        $byPest = [];
        foreach ($recs as $p) { $n = $p['pest_name'] ?: 'Unknown'; $byPest[$n] = ($byPest[$n] ?? 0) + 1; }
        arsort($byPest);
        $summaries = [['Detections', count($recs), 'red'], ['Pest species', count($byPest), 'amber']];
        foreach ($byPest as $n => $c) if ($c > 1) $summaries[] = [$n, $c, 'red'];
        foreach ($recs as $p) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="flex justify-between items-start"><div class="text-base font-bold text-slate-800">' . esc($p['pest_name']) . '</div><span class="text-[10px] font-bold uppercase px-2 py-1 rounded-full bg-red-100 text-red-800">detected</span></div>'
                . '<div class="text-xs text-slate-500 mt-1">Field/farm #' . esc($p['farm_id'] ?? '—') . ' · ' . esc($p['detected_date'] ?? '') . '</div>'
                . (($p['action_taken'] ?? '') ? '<div class="mt-2 text-xs text-slate-600"><strong>Action:</strong> ' . esc($p['action_taken']) . '</div>' : '') . '</div>';
        }
        break;

    case 'Harvest':
        $title = 'Harvest Dashboard'; $subtitle = 'Seasonal yields and quality at a glance.';
        $tot = 0.0; $quality = [];
        foreach ($recs as $h) { $tot += (float) ($h['quantity'] ?? 0); $q = $h['quality'] ?: '—'; $quality[$q] = ($quality[$q] ?? 0) + 1; }
        $summaries = [['Total harvested', number_format($tot, 0) . ' kg', 'green'], ['Records', count($recs), 'slate']];
        foreach ($quality as $q => $n) $summaries[] = ['Quality: ' . ($q === '—' ? 'n/a' : $q), $n, 'amber'];
        foreach ($recs as $h) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="flex justify-between items-center"><div class="text-base font-bold text-slate-800">Crop #' . esc($h['crop_id'] ?? '—') . '</div><span class="text-sm font-extrabold text-emerald-700">' . number_format((float) ($h['quantity'] ?? 0)) . ' kg</span></div>'
                . '<div class="text-xs text-slate-500 mt-1">' . esc($h['harvest_date'] ?? '') . ' · Quality: ' . esc($h['quality'] ?? '—') . '</div></div>';
        }
        break;

    case 'Market':
        $title = 'Market Prices'; $subtitle = 'Current crop prices to guide selling decisions.';
        $latest = []; $total = 0.0;
        foreach ($recs as $md) { $total += (float) ($md['price'] ?? 0); $latest[$md['crop_name']] = $md['price']; }
        $avg = count($recs) ? ($total / count($recs)) : 0;
        $summaries = [['Latest prices', count($latest), 'green'], ['Avg price', 'K' . number_format($avg, 2), 'slate']];
        foreach ($recs as $md) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="flex justify-between items-center"><div class="text-base font-bold text-slate-800">' . esc($md['crop_name']) . '</div><span class="text-sm font-extrabold text-emerald-700">' . money($md['price']) . '</span></div>'
                . '<div class="text-xs text-slate-500 mt-1">per kg · ' . esc($md['market_date'] ?? '') . '</div></div>';
        }
        break;

    case 'Supplier':
        $title = 'Suppliers'; $subtitle = 'Your sourcing network.';
        $spend = (float) $pdo->query('SELECT COALESCE(SUM(cost),0) FROM procurement_records')->fetchColumn();
        $summaries = [['Suppliers', count($recs), 'green'], ['Procurement spend', money($spend), 'amber']];
        foreach ($recs as $s) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="text-base font-bold text-slate-800">' . esc($s['name']) . '</div>'
                . '<div class="text-xs text-slate-500 mt-1">' . esc($s['contact_info'] ?? 'No contact info') . '</div></div>';
        }
        break;

    case 'Procurement':
        $title = 'Procurement'; $subtitle = 'Purchases and spend this period.';
        $totSpend = 0.0; foreach ($recs as $pr) $totSpend += (float) ($pr['cost'] ?? 0);
        $summaries = [['Purchases', count($recs), 'slate'], ['Total spend', money($totSpend), 'green']];
        foreach ($recs as $pr) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="flex justify-between items-center"><div class="text-base font-bold text-slate-800">' . esc($pr['item_name']) . '</div><span class="text-sm font-extrabold text-emerald-700">' . money($pr['cost']) . '</span></div>'
                . '<div class="text-xs text-slate-500 mt-1">' . esc($pr['quantity'] ?? '') . ' units · ' . esc($pr['purchase_date'] ?? '') . '</div></div>';
        }
        break;

    case 'Sales':
        $title = 'Sales Dashboard'; $subtitle = 'Revenue and customer activity.';
        $totRev = 0.0; foreach ($recs as $s) $totRev += (float) ($s['amount'] ?? 0);
        $summaries = [['Sales', count($recs), 'slate'], ['Total revenue', money($totRev), 'green']];
        foreach ($recs as $s) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="flex justify-between items-center"><div class="text-base font-bold text-slate-800">' . esc($s['customer_name']) . '</div><span class="text-sm font-extrabold text-emerald-700">' . money($s['amount']) . '</span></div>'
                . '<div class="text-xs text-slate-500 mt-1">' . esc($s['sale_date'] ?? '') . '</div></div>';
        }
        break;

    case 'Notifications':
        $title = 'Notifications'; $subtitle = 'Alerts and intelligence updates.';
        $summaries = [['Notifications', count($recs), 'green']];
        foreach ($recs as $n) {
            $render .= '<div class="glass-card p-4 sm:p-5 flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-green-500 mt-2 shrink-0"></span>'
                . '<div><div class="text-sm text-slate-800">' . esc($n['message']) . '</div><div class="text-[11px] text-slate-400 mt-1">' . esc($n['created_at'] ?? '') . '</div></div></div>';
        }
        break;

    case 'Analytics':
        $title = 'Analytics'; $subtitle = 'Module performance snapshots.';
        $summaries = [['Data points', count($recs), 'green']];
        foreach ($recs as $a) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="text-base font-bold text-slate-800">' . esc($a['module_name'] ?? 'Module') . '</div>'
                . '<div class="text-xs text-slate-500 mt-1">' . esc($a['data_points'] ?? '') . '</div></div>';
        }
        break;

    case 'Reports':
        $title = 'Reports'; $subtitle = 'Generated business reports.';
        $summaries = [['Reports', count($recs), 'green']];
        foreach ($recs as $r) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="text-base font-bold text-slate-800">' . esc($r['report_type'] ?? 'Report') . '</div>'
                . '<div class="text-xs text-slate-500 mt-1">' . esc($r['generated_at'] ?? '') . '</div></div>';
        }
        break;

    case 'Security':
        $title = 'Security Log'; $subtitle = 'Audit trail of actions taken.';
        $summaries = [['Log entries', count($recs), 'slate']];
        foreach ($recs as $log) {
            $render .= '<div class="glass-card p-4 sm:p-5 flex items-start gap-3"><span class="w-2 h-2 rounded-full bg-slate-400 mt-2 shrink-0"></span>'
                . '<div><div class="text-sm text-slate-800">' . esc($log['action'] ?? '') . '</div><div class="text-[11px] text-slate-400 mt-1">' . esc($log['log_time'] ?? '') . '</div></div></div>';
        }
        break;

    default:
        // Safe fallback: plain card list.
        $title = $m ?: 'Overview'; $subtitle = '';
        $summaries = [['Records', count($recs), 'slate']];
        foreach ($recs as $r) {
            $render .= '<div class="glass-card p-4 sm:p-5"><div class="text-sm text-slate-700">' . esc(is_string($r) ? $r : json_encode($r)) . '</div></div>';
        }
}

$summaryCards = '';
foreach ($summaries as $s) {
    $colorMap = ['green' => 'text-green-700', 'amber' => 'text-amber-700', 'red' => 'text-red-700', 'slate' => 'text-slate-800'];
    $summaryCards .= '<div class="glass-card p-4 text-center"><div class="text-2xl font-extrabold ' . ($colorMap[$s[2]] ?? 'text-slate-800') . '">' . esc($s[1]) . '</div>'
        . '<div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mt-1">' . esc($s[0]) . '</div></div>';
}
?>
<h1 class="text-2xl sm:text-3xl font-bold mb-1 text-green-800"><?php echo esc($title); ?></h1>
<?php if ($subtitle): ?><p class="text-sm text-slate-500 mb-5"><?php echo esc($subtitle); ?></p><?php endif; ?>

<div class="flex items-center justify-between mb-4">
    <a href="<?php echo esc($addLink); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white text-sm font-bold shadow-lg hover:shadow-emerald-700/40 transition-shadow">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Add / Manage <?php echo esc($m); ?>
    </a>
</div>

<?php if ($summaryCards): ?><div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 mb-6"><?php echo $summaryCards; ?></div><?php endif; ?>

<div class="<?php echo ($m === 'Notifications' || $m === 'Security' || $m === 'Analytics') ? 'space-y-2.5' : 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4'; ?>">
    <?php if ($render): echo $render; else: ?><p class="text-slate-500 glass-card p-4">No records yet — use "Add / Manage" to create the first one.</p><?php endif; ?>
</div>
