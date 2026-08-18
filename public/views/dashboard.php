<?php
require_once __DIR__ . '/../../src/Modules/Analytics/AnalyticsModel.php';
$analyticsModel = new AnalyticsModel($pdo);
$totalCost = (float)$analyticsModel->getTotalProcurementCost();
$totalSales = (float)$analyticsModel->getTotalSalesAmount();
$totalInventory = (int)$analyticsModel->getTotalInventoryCount();
?>
<div class="space-y-6">
    <h1 class="text-3xl font-bold">Dashboard</h1>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="glass p-6 rounded-xl hover-tilt">
            <div class="text-sm font-medium uppercase tracking-wider opacity-70">Total Costs</div>
            <div class="mt-2 text-3xl font-bold text-red-500" data-target="<?php echo $totalCost; ?>">0</div>
        </div>
        <div class="glass p-6 rounded-xl hover-tilt">
            <div class="text-sm font-medium uppercase tracking-wider opacity-70">Total Sales</div>
            <div class="mt-2 text-3xl font-bold text-green-500" data-target="<?php echo $totalSales; ?>">0</div>
        </div>
        <div class="glass p-6 rounded-xl hover-tilt">
            <div class="text-sm font-medium uppercase tracking-wider opacity-70">Inventory Items</div>
            <div class="mt-2 text-3xl font-bold text-blue-500" data-target="<?php echo $totalInventory; ?>">0</div>
        </div>
    </div>

    <div class="glass p-6 rounded-xl">
        <h3 class="text-lg font-semibold mb-4">Cost vs. Sales</h3>
        <canvas id="financeChart" height="100"></canvas>
    </div>
</div>

<script>
    // Counter Animation
    document.querySelectorAll('[data-target]').forEach(el => {
        const target = parseFloat(el.getAttribute('data-target'));
        const update = () => {
            const current = parseFloat(el.innerText.replace(/[^0-9.]/g, ''));
            const increment = target / 100;
            if (current < target) {
                el.innerText = '$' + (current + increment).toFixed(2);
                setTimeout(update, 20);
            } else {
                el.innerText = '$' + target.toFixed(2);
            }
        };
        update();
    });

    // Chart
    new Chart(document.getElementById('financeChart'), {
        type: 'bar',
        data: {
            labels: ['Finance'],
            datasets: [
                { label: 'Costs', data: [<?php echo $totalCost; ?>], backgroundColor: '#ef4444' },
                { label: 'Sales', data: [<?php echo $totalSales; ?>], backgroundColor: '#22c55e' }
            ]
        }
    });
</script>
