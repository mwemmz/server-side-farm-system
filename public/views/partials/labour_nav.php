<?php
// Shared sub-section navigation bar for the Labour/HR module.
// Expects $sub (current sub-section key).
$labourNav = [
    'employees'   => 'Employee Records',
    'departments' => 'Departments',
    'training'    => 'Training & Certifications',
    'payroll'     => 'Payroll',
    'leave'       => 'Leave Management',
    'shifts'      => 'Shifts & Attendance',
    'grievances'  => 'Cases & Grievances',
];
$labourIcons = [
    'employees'   => 'M21 13.255A23.931 23.931 0 0 1 12 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2m4 6h.01M5 20h14a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z',
    'departments' => 'M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4',
    'training'    => 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 0 1 .665 6.479A11.952 11.952 0 0 0 12 20.055a11.952 11.952 0 0 0-6.824-2.998 12.078 12.078 0 0 1 .665-6.479L12 14zm-4 6v-7.5l4-2.222',
    'payroll'     => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
    'leave'       => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z',
    'shifts'      => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z',
    'grievances'  => 'M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2z',
];
?>
<div class="glass-card p-3 sm:p-4 mb-6">
    <div class="flex flex-wrap items-center gap-1.5">
        <a href="index.php?module=Labour" class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-slate-500 hover:text-green-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 17l-5-5 5-5M18 17l-5-5 5-5"/></svg>
            HR Dashboard
        </a>
        <span class="hidden sm:inline text-slate-300">|</span>
        <?php foreach ($labourNav as $key => $label): ?>
            <a href="index.php?module=Labour&subsection=<?php echo $key; ?>"
               class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition-all
               <?php echo ($sub === $key) ? 'bg-gradient-to-r from-green-600 to-emerald-600 text-white shadow' : 'text-slate-600 hover:bg-emerald-50 hover:text-green-700'; ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="<?php echo $labourIcons[$key]; ?>"/></svg>
                <?php echo $label; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
