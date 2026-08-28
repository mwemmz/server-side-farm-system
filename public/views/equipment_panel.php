<?php
$items = $data ?? [];
$entities = [];
foreach ($items as $i) {
    $id = (int) (is_array($i) ? ($i['id'] ?? 0) : ($i->id ?? 0));
    $entities[$id] = (is_array($i) ? ($i['name'] ?? 'Equipment') : ($i->name ?? 'Equipment')) . ' #' . $id;
}
$panelTitle   = 'Equipment Control Panel';
$panelModule  = 'Equipment';
$panelEntities = $entities;
$panelFarmId   = null;
$panelAddLink  = 'index.php?module=Equipment&action=add';
require __DIR__ . '/partials/panel_shell.php';
