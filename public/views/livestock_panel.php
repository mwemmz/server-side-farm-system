<?php
$farm = null;
$wantedFarm = isset($_GET['farm_id']) ? (int) $_GET['farm_id'] : 0;
if ($wantedFarm) {
    try { $exists = (int) $pdo->query("SELECT COUNT(*) FROM farms WHERE id = $wantedFarm")->fetchColumn(); if ($exists) $farm = $wantedFarm; } catch (Exception $e) { $farm = null; }
}
if (!$farm) { try { $farm = $pdo->query("SELECT id FROM farms ORDER BY id LIMIT 1")->fetchColumn(); } catch (Exception $e) { $farm = null; } }
$panelTitle   = 'Livestock Control Panel';
$panelModule  = 'Livestock';
$panelEntities = [];
$panelFarmId   = $farm ? (int) $farm : null;
$panelAddLink  = 'index.php?module=Livestock&action=add';
require __DIR__ . '/partials/panel_shell.php';
