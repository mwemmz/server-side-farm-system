<?php
require_once __DIR__ . '/../../src/Modules/Analytics/AnalyticsModel.php';
$analyticsModel = new AnalyticsModel($pdo);
$totalCost = $analyticsModel->getTotalProcurementCost();
$totalSales = $analyticsModel->getTotalSalesAmount();
$totalInventory = $analyticsModel->getTotalInventoryCount();
?>
<div class="bg-white p-6 rounded-lg shadow-md">
    <h1 class="text-2xl font-bold mb-4">Dashboard</h1>
    <p>Welcome to the Intelligent Farm Management System.</p>
    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="bg-red-100 p-4 rounded shadow">
            <h3 class="font-bold">Total Costs</h3>
            <p class="text-xl">$<?php echo number_format($totalCost, 2); ?></p>
        </div>
        <div class="bg-green-100 p-4 rounded shadow">
            <h3 class="font-bold">Total Sales</h3>
            <p class="text-xl">$<?php echo number_format($totalSales, 2); ?></p>
        </div>
        <div class="bg-blue-100 p-4 rounded shadow">
            <h3 class="font-bold">Inventory Items</h3>
            <p class="text-xl"><?php echo number_format($totalInventory); ?></p>
        </div>
    </div>
</div>
