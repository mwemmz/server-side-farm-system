<div class="space-y-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Farm-to-market traceability</h1>
        <p class="text-sm text-slate-500 mt-1">Create harvest batches and share a tokenized provenance record.</p>
    </div>
    <?php if (!empty($data['flash'])): ?><div class="bg-green-100 text-green-800 rounded-lg p-3"><?php echo htmlspecialchars($data['flash']); ?></div><?php endif; ?>
    <?php if (!empty($data['error'])): ?><div class="bg-red-100 text-red-800 rounded-lg p-3"><?php echo htmlspecialchars($data['error']); ?></div><?php endif; ?>
    <div class="glass-card p-5">
        <form method="post" action="index.php?module=Traceability&action=create" class="grid md:grid-cols-3 gap-3">
            <?php echo SessionHelper::csrfField(); ?>
            <input name="batch_code" required pattern="[A-Za-z0-9._-]{3,80}" placeholder="Batch code" class="border rounded-lg p-2">
            <input name="quantity" required type="number" min="0.01" step="0.01" placeholder="Quantity" class="border rounded-lg p-2">
            <input name="unit" placeholder="Unit" class="border rounded-lg p-2">
            <input name="field_id" type="number" min="1" placeholder="Field ID (optional)" class="border rounded-lg p-2">
            <input name="crop_id" type="number" min="1" placeholder="Crop ID (optional)" class="border rounded-lg p-2">
            <input name="harvest_date" type="date" value="<?php echo date('Y-m-d'); ?>" class="border rounded-lg p-2">
            <input name="location" placeholder="Location" class="border rounded-lg p-2">
            <input name="notes" placeholder="Notes" class="border rounded-lg p-2 md:col-span-2">
            <button class="bg-green-700 text-white rounded-lg p-2" type="submit">Create batch</button>
        </form>
    </div>
    <div class="glass-card p-5 text-sm text-slate-600">
        Public provenance URLs use <code>index.php?module=Traceability&amp;action=public&amp;token=...</code>.
        The create endpoint returns the token in the JSON API response for QR label generation.
    </div>
</div>
