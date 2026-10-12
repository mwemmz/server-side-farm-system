<?php
$panelTitle   = 'Financial Control Panel';
$panelModule  = 'Finance';
$panelEntities = [];
$panelFarmId   = null;
$panelAddLink  = 'index.php?module=Finance&action=add';
$panelExtraLinks = [
    ['label' => 'Loans', 'href' => 'index.php?module=Finance&action=loans'],
    ['label' => 'Budgets', 'href' => 'index.php?module=Finance&action=budgets'],
];
require __DIR__ . '/partials/panel_shell.php';
