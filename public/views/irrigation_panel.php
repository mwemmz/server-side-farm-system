<?php
$systems = $data ?? [];
$entities = [];
foreach ($systems as $s) {
    $id = (int) (is_array($s) ? ($s['id'] ?? 0) : ($s->id ?? 0));
    $label = (is_array($s) ? ($s['type'] ?? 'System') : ($s->type ?? 'System')) . ' #' . $id;
    if (is_array($s) ? !empty($s['status']) : !empty($s->status)) {
        $label .= ' - ' . (is_array($s) ? $s['status'] : $s->status);
    }
    $entities[$id] = $label;
}
$panelTitle   = 'Irrigation Control Panel';
$panelModule  = 'Irrigation';
$panelEntities = $entities;
$panelFarmId   = null;
$panelAddLink  = 'index.php?module=Irrigation&action=add';
require __DIR__ . '/partials/panel_shell.php';
