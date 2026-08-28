<?php
$records = $data ?? [];
$entities = [];
foreach ($records as $r) {
    $id = (int) (is_array($r) ? ($r['id'] ?? 0) : ($r->id ?? 0));
    $entities[$id] = (is_array($r) ? ($r['name'] ?? 'Facility') : ($r->name ?? 'Facility')) . ' #' . $id;
}
$panelTitle   = 'Storage Facility Control Panel';
$panelModule  = 'Storage';
$panelEntities = $entities;
$panelFarmId   = null;
$panelAddLink  = 'index.php?module=Storage&action=add';
require __DIR__ . '/partials/panel_shell.php';
