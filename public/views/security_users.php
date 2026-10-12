<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$users  = $data['users'] ?? [];
$errors = $data['errors'] ?? [];
$formData = $data['data'] ?? [];
$flash = SessionHelper::getFlash('success');
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-1 text-green-800">User Management</h1>
<p class="text-sm text-slate-500 mb-5">Create accounts and assign roles.</p>

<?php if ($flash): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 mb-6">
    <h2 class="text-xl font-semibold mb-4">Add User</h2>
    <?php
    $fields = [
        ['name' => 'username', 'label' => 'Username', 'value' => $formData['username'] ?? ''],
        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $formData['email'] ?? ''],
        ['name' => 'password', 'label' => 'Temporary Password', 'type' => 'password', 'help' => 'At least 6 characters.'],
        ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => ['worker' => 'Worker', 'manager' => 'Manager', 'accountant' => 'Accountant', 'admin' => 'Admin'], 'placeholder' => 'Select a role…', 'value' => $formData['role'] ?? 'worker'],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Security&action=users', 'POST', $errors);
    ?>
</div>

<div class="glass-card p-4 sm:p-6">
    <h2 class="text-xl font-semibold mb-4">Users (<?php echo count($users); ?>)</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead>
                <tr class="text-[11px] uppercase tracking-wide text-slate-400 border-b border-slate-200">
                    <th class="py-2 pr-4">Username</th>
                    <th class="py-2 pr-4">Email</th>
                    <th class="py-2 pr-4">Role</th>
                    <th class="py-2 pr-4">Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="border-b border-slate-100 text-slate-700">
                    <td class="py-2 pr-4 font-semibold"><?php echo htmlspecialchars($u['username']); ?></td>
                    <td class="py-2 pr-4"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td class="py-2 pr-4"><span class="text-[11px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800"><?php echo htmlspecialchars($u['role']); ?></span></td>
                    <td class="py-2 pr-4 text-slate-400"><?php echo htmlspecialchars($u['created_at'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="mt-4"><a href="index.php?module=Security" class="text-sm font-semibold text-green-700 hover:text-green-800">← Back to Security</a></p>
