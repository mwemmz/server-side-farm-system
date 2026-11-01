<div class="space-y-6">
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Planting what-if planner</h1>
    <form method="get" action="index.php" class="glass-card p-5 grid md:grid-cols-3 gap-3">
        <input type="hidden" name="module" value="Insights"><input type="hidden" name="view" value="planner">
        <input name="area" type="number" min="0.01" step="0.01" required placeholder="Area (ha)" class="border rounded-lg p-2">
        <input name="input_cost" type="number" min="0" step="0.01" required placeholder="Input cost / ha" class="border rounded-lg p-2">
        <input name="expected_yield" type="number" min="0" step="0.01" required placeholder="Expected yield / ha" class="border rounded-lg p-2">
        <input name="price_forecast" type="number" min="0" step="0.01" required placeholder="Price forecast / unit" class="border rounded-lg p-2">
        <input name="plant_month" type="number" min="1" max="12" required placeholder="Planting month (1-12)" class="border rounded-lg p-2">
        <button class="bg-green-700 text-white rounded-lg p-2" type="submit">Calculate</button>
    </form>
    <div id="planner-result" class="glass-card p-5 text-slate-600">Submit assumptions to see expected revenue, cost, profit, and weather risk.</div>
</div>
<script>
const form = document.querySelector('form');
form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const query = new URLSearchParams(new FormData(form));
  const result = await fetch('index.php?module=Insights&action=planner&' + query.toString()).then(r => r.json());
  document.querySelector('#planner-result').textContent = result.success
    ? `Revenue K${result.data.expected_revenue}; cost K${result.data.expected_cost}; profit K${result.data.expected_profit}; ${result.data.risk_notes}`
    : result.error;
});
</script>
