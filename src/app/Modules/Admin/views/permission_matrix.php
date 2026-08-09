<?php
// Group permissions by module for visual separation
$groupedPerms = [];
foreach ($allPerms as $perm) {
    $groupedPerms[$perm['module']][] = $perm;
}

// Mark Super Admin role IDs — these get all-checked + disabled cells
$superAdminIds = [];
foreach ($roles as $role) {
    if ($role['role_name'] === 'Super Admin') {
        $superAdminIds[] = (int)$role['id'];
    }
}

$moduleColors = [
    'Dashboard'    => 'rfq-badge-neutral',
    'Customers'    => 'rfq-badge-info',
    'Contacts'     => 'rfq-badge-info',
    'Interactions' => 'rfq-badge-info',
    'RFQ'          => 'rfq-badge-quoted',
    'Quotes'       => 'rfq-badge-quoted',
    'Reservations' => 'rfq-badge-warning',
    'Campaigns'    => 'rfq-badge-success',
    'Inventory'    => 'rfq-badge-warning',
    'Reports'      => 'rfq-badge-neutral',
    'Admin'        => 'rfq-badge-danger',
];
?>

<section class="card">

    <div class="page-header">
        <h1>Role Permission Matrix</h1>
    </div>

    <p class="text-muted" style="margin-bottom:1.5rem;">
        Controls what each role can do across all modules.
        <strong>Super Admin</strong> always has full access and cannot be modified.
        Changes apply immediately when you click <strong>Save Changes</strong>.
    </p>

    <form method="POST" action="" id="perm-matrix-form">
        <?= App\Core\Csrf::field() ?>

        <div class="perm-matrix-scroll">
            <table class="table perm-matrix-table">
                <thead>
                    <tr>
                        <th class="perm-label-col">Permission</th>
                        <?php foreach ($roles as $role): ?>
                        <th class="perm-role-col">
                            <?= htmlspecialchars($role['role_name']) ?>
                            <?php if (in_array((int)$role['id'], $superAdminIds, true)): ?>
                            <br><span class="perm-role-note">full access</span>
                            <?php endif; ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groupedPerms as $module => $perms): ?>

                    <!-- Module section header -->
                    <tr class="perm-module-row">
                        <td colspan="<?= count($roles) + 1 ?>">
                            <span class="rfq-badge <?= $moduleColors[$module] ?? 'rfq-badge-neutral' ?>" style="font-size:0.75rem;letter-spacing:0.03em;">
                                <?= htmlspecialchars($module) ?>
                            </span>
                        </td>
                    </tr>

                    <?php foreach ($perms as $perm): ?>
                    <tr>
                        <td class="perm-label-col">
                            <span class="perm-label-text"><?= htmlspecialchars($perm['label']) ?></span>
                            <span class="perm-key"><?= htmlspecialchars($perm['action']) ?></span>
                        </td>
                        <?php foreach ($roles as $role): ?>
                        <td class="perm-check-col">
                            <?php if (in_array((int)$role['id'], $superAdminIds, true)): ?>
                                <input type="checkbox" checked disabled class="perm-check" title="Super Admin always has full access">
                            <?php else: ?>
                                <?php $isGranted = isset($granted[(int)$role['id'] . ':' . $perm['action']]); ?>
                                <input type="checkbox"
                                       name="permissions[<?= (int)$role['id'] ?>][]"
                                       value="<?= htmlspecialchars($perm['action']) ?>"
                                       class="perm-check"
                                       <?= $isGranted ? 'checked' : '' ?>>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>

                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions" style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>

    </form>

</section>

<!-- Role legend -->
<section class="card">
    <h2 class="rfq-detail-card-title" style="margin-bottom:1rem;">Role Descriptions</h2>
    <div class="rfq-detail-grid">
        <?php foreach ($roles as $role): ?>
        <div class="rfq-detail-section">
            <h3 class="rfq-detail-section-title">
                <?= htmlspecialchars($role['role_name']) ?>
                <?php if (in_array((int)$role['id'], $superAdminIds, true)): ?>
                <span class="rfq-badge rfq-badge-danger" style="font-size:0.7rem;margin-left:4px;">wildcard</span>
                <?php endif; ?>
            </h3>
            <p class="rfq-detail-meta" style="margin:0;"><?= htmlspecialchars($role['description'] ?? '—') ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>
