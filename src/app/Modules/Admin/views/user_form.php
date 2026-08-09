<?php
$isEdit      = $user !== null;
$title       = $isEdit ? 'Edit User' : 'Add User';
$postUrl     = $isEdit
    ? '/admin/users.php?action=edit&id=' . (int)$user['id']
    : '/admin/users.php?action=create';
$action      = $isEdit ? 'edit' : 'create';
$hasCustom   = $isEdit && !empty($user['owner_user_id']);
?>

<section class="card">

    <div class="page-header">
        <h1><?= $title ?></h1>
        <div class="module-header-actions">
            <a href="/admin/users.php" class="btn btn-secondary">Cancel</a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error" style="margin-bottom:1.5rem;">
        <ul style="margin:0;padding-left:1.2rem;">
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= htmlspecialchars($postUrl) ?>">
        <?= App\Core\Csrf::field() ?>
        <input type="hidden" name="_action" value="<?= $action ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <?php endif; ?>

        <div class="form-group">
            <label for="name">Full Name <span class="required">*</span></label>
            <input type="text" id="name" name="name" class="form-control"
                   value="<?= htmlspecialchars($input['name'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email Address <span class="required">*</span></label>
            <input type="email" id="email" name="email" class="form-control"
                   value="<?= htmlspecialchars($input['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label for="password">
                Password <?= $isEdit ? '<span class="text-muted">(leave blank to keep current)</span>' : '<span class="required">*</span>' ?>
            </label>
            <input type="password" id="password" name="password" class="form-control"
                   autocomplete="new-password"
                   placeholder="<?= $isEdit ? 'Leave blank to keep unchanged' : 'Min. 8 characters' ?>">
        </div>

        <?php if ($hasCustom): ?>
        <!-- User has a custom role — show read-only role info instead of the dropdown -->
        <div class="form-group">
            <label>Role</label>
            <div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0;">
                <span class="rfq-badge rfq-badge-warning"><?= htmlspecialchars($user['role_name']) ?></span>
                <a href="/admin/permissions.php" class="btn btn-secondary btn-sm">Edit Permissions</a>
            </div>
        </div>
        <?php else: ?>
        <div class="form-group">
            <label for="role_id">Role <span class="required">*</span></label>
            <select id="role_id" name="role_id" class="form-control" required>
                <option value="">— Select a role —</option>
                <?php foreach ($roles as $role): ?>
                <option value="<?= (int)$role['id'] ?>"
                    <?= (string)($input['role_id'] ?? '') === (string)$role['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($role['role_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
            <a href="/admin/users.php" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</section>

<?php if ($isEdit): ?>

<?php if ($hasCustom): ?>
<!-- Remove custom role -->
<section class="card" style="border-color:var(--color-warning,#f59e0b);">
    <h2 class="rfq-detail-card-title" style="margin-bottom:0.5rem;">Remove Custom Role</h2>
    <p class="text-muted" style="margin-bottom:1rem;">
        Revert this user to a standard role. Their custom role and its permissions will be permanently deleted.
    </p>
    <form method="POST" action="/admin/users.php"
          onsubmit="return confirm('Remove custom role and revert to a standard role?');">
        <?= App\Core\Csrf::field() ?>
        <input type="hidden" name="_action"  value="remove_custom_role">
        <input type="hidden" name="user_id"  value="<?= (int)$user['id'] ?>">
        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
            <select name="role_id" class="form-control" style="max-width:220px;" required>
                <option value="">— Choose standard role —</option>
                <?php foreach ($roles as $role): ?>
                <option value="<?= (int)$role['id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-danger">Remove Custom Role</button>
        </div>
    </form>
</section>

<?php else: ?>
<!-- Create custom role -->
<section class="card">
    <h2 class="rfq-detail-card-title" style="margin-bottom:0.5rem;">Custom Role</h2>
    <p class="text-muted" style="margin-bottom:1rem;">
        Create a role unique to this user. You'll be taken to the permission matrix to configure it immediately.
    </p>
    <form method="POST" action="/admin/users.php"
          onsubmit="return confirm('Create a custom role for <?= htmlspecialchars(addslashes($user['name'])) ?>?');">
        <?= App\Core\Csrf::field() ?>
        <input type="hidden" name="_action" value="create_custom_role">
        <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
        <button type="submit" class="btn btn-secondary">+ Create Custom Role</button>
    </form>
</section>
<?php endif; ?>

<?php endif; ?>
