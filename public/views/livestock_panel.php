<?php
$farm = null;
try { $farm = $pdo->query("SELECT id FROM farms ORDER BY id LIMIT 1")->fetchColumn(); } catch (Exception $e) { $farm = null; }
$panelTitle   = 'Livestock Control Panel';
$panelModule  = 'Livestock';
$panelEntities = [];
$panelFarmId   = $farm ? (int) $farm : null;
$panelAddLink  = 'index.php?module=Livestock&action=add';
require __DIR__ . '/partials/panel_shell.php';
