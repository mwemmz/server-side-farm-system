<?php
require_once __DIR__ . '/../../src/Modules/Analytics/AnalyticsModel.php';
$analyticsModel = new AnalyticsModel($pdo);
$totalCost = $analyticsModel->getTotalProcurementCost() ?? 0;
$totalSales = $analyticsModel->getTotalSalesAmount() ?? 0;
$totalInventory = $analyticsModel->getTotalInventoryCount() ?? 0;
?>
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 animate-fade-in">
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Dashboard</h1>
            <p class="text-sm text-slate-500 mt-1.5 max-w-3xl">Welcome to the Intelligent Farm Management System.</p>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 stagger">
        <!-- Total Costs -->
        <div class="glass-card card-glow p-4 md:p-5 group">
            <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-rose-500 to-pink-500 opacity-70 rounded-t-2xl"></div>
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Costs</div>
                    <div class="mt-1.5 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums">$<span class="counter" data-target="<?php echo $totalCost; ?>">0</span></div>
                    <div class="mt-1 text-[11px] text-slate-400">Procurement expenses</div>
                </div>
                <div class="shrink-0 w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
            </div>
        </div>

        <!-- Total Sales -->
        <div class="glass-card card-glow p-4 md:p-5 group">
            <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-emerald-500 to-teal-500 opacity-70 rounded-t-2xl"></div>
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Sales</div>
                    <div class="mt-1.5 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums">$<span class="counter" data-target="<?php echo $totalSales; ?>">0</span></div>
                    <div class="mt-1 text-[11px] text-slate-400">Revenue generated</div>
                </div>
                <div class="shrink-0 w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>
                </div>
            </div>
        </div>

        <!-- Inventory Items -->
        <div class="glass-card card-glow p-4 md:p-5 group">
            <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-indigo-500 to-blue-500 opacity-70 rounded-t-2xl"></div>
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Inventory Items</div>
                    <div class="mt-1.5 text-xl md:text-2xl font-extrabold text-slate-900 tabular-nums"><span class="counter" data-target="<?php echo $totalInventory; ?>">0</span></div>
                    <div class="mt-1 text-[11px] text-slate-400">Active stock items</div>
                </div>
                <div class="shrink-0 w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center transition-transform duration-300 group-hover:scale-110">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="glass-card p-5 md:p-6">
        <div class="flex items-center gap-2 mb-4">
            <span class="h-1.5 w-1.5 rounded-full bg-gradient-to-r from-green-500 to-emerald-500"></span>
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Cost vs Sales</h3>
        </div>
        <canvas id="costSalesChart" height="200"></canvas>
    </div>
</div>

<script>
// Animated counters
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        const target = parseFloat(counter.getAttribute('data-target')) || 0;
        const duration = 1500;
        const start = performance.now();
        const isDecimal = target % 1 !== 0;
        function update(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = target * eased;
            counter.textContent = isDecimal ? current.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : Math.round(current).toLocaleString();
            if (progress < 1) requestAnimationFrame(update);
        }
        requestAnimationFrame(update);
    });

    // Chart.js
    const ctx = document.getElementById('costSalesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total'],
                datasets: [
                    {
                        label: 'Costs',
                        data: [<?php echo $totalCost; ?>],
                        backgroundColor: 'rgba(244, 63, 94, 0.6)',
                        borderColor: 'rgba(244, 63, 94, 1)',
                        borderWidth: 1,
                        borderRadius: 8,
                    },
                    {
                        label: 'Sales',
                        data: [<?php echo $totalSales; ?>],
                        backgroundColor: 'rgba(16, 185, 129, 0.6)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1,
                        borderRadius: 8,
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
