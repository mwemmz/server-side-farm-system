<?php
require_once __DIR__ . '/../../src/Modules/Analytics/AnalyticsModel.php';
$analyticsModel = new AnalyticsModel($pdo);
$totalCost = $analyticsModel->getTotalProcurementCost();
$totalSales = $analyticsModel->getTotalSalesAmount();
$totalInventory = $analyticsModel->getTotalInventoryCount();
?>
<div class="space-y-6">
    <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
    <p class="text-gray-600">Welcome to the Intelligent Farm Management System.</p>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
            <div class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Costs</div>
            <div class="mt-2 text-3xl font-bold text-red-600">$<?php echo number_format($totalCost, 2); ?></div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
            <div class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Sales</div>
            <div class="mt-2 text-3xl font-bold text-green-600">$<?php echo number_format($totalSales, 2); ?></div>
        </div>
        <div class="bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
            <div class="text-sm font-medium text-gray-500 uppercase tracking-wider">Inventory Items</div>
            <div class="mt-2 text-3xl font-bold text-blue-600"><?php echo number_format($totalInventory); ?></div>
        </div>
    </div>
</div>
