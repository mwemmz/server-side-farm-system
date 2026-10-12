<?php
require_once __DIR__ . '/../../src/Helpers/FormHelper.php';
require_once __DIR__ . '/../../src/Helpers/SessionHelper.php';

$errors = $data['errors'] ?? [];
$flash = SessionHelper::getFlash('success');
?>

<h1 class="text-2xl sm:text-3xl font-bold mb-1 text-green-800">Change Password</h1>
<p class="text-sm text-slate-500 mb-5">Update the password for your account.</p>

<?php if ($flash): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="glass-card p-4 sm:p-6 max-w-xl">
    <?php
    $fields = [
        ['name' => 'current_password', 'label' => 'Current Password', 'type' => 'password'],
        ['name' => 'new_password', 'label' => 'New Password', 'type' => 'password', 'help' => 'At least 6 characters.'],
        ['name' => 'confirm_password', 'label' => 'Confirm New Password', 'type' => 'password'],
    ];
    echo FormHelper::generateForm($fields, 'index.php?module=Security&action=change_password', 'POST', $errors);
    ?>
</div>
