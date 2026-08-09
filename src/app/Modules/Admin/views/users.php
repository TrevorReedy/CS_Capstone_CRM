<section class="card">

    <div class="page-header">
        <h1>Users</h1>
        <div class="module-header-actions">
            <a href="/admin/permissions.php" class="btn btn-secondary">Permission Matrix</a>
            <a href="/admin/users.php?action=create" class="btn btn-primary">+ Add User</a>
        </div>
    </div>

    <table class="table js-dt"
           data-dt-url="/admin/users_data.php"
           data-dt-export="/admin/users_export.php">
        <thead>
            <tr class="dt-title">
                <th data-col="name">Name</th>
                <th data-col="email">Email</th>
                <th data-col="role">Role</th>
                <th data-col="created_at">Created</th>
                <th data-col="actions" data-orderable="false" data-searchable="false">Actions</th>
            </tr>
            <tr class="dt-filter">
                <th data-filter="text"></th>
                <th data-filter="text"></th>
                <th data-filter="text"></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

</section>

<?php include __DIR__ . '/../../../Shared/datatables_assets.php'; ?>
