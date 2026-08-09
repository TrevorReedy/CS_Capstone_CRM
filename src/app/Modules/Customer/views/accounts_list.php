<section class="card">

    <?php page_header('Customer Accounts', [
        'href'  => 'create_account.php',
        'label' => '+ New Customer',
        'title' => 'Add a new customer',
    ]); ?>

    <div class="toolbar">
        <h2 class="rfq-list-title">All Customers</h2>
    </div>

    <table class="table js-dt"
           data-dt-url="/modules/customer/accounts_data.php"
           data-dt-export="/modules/customer/accounts_export.php">
        <thead>
            <tr class="dt-title">
                <th data-col="account_name">Name</th>
                <th data-col="email">Email</th>
                <th data-col="phone">Phone</th>
                <th data-col="industry">Industry</th>
                <th data-col="source">Source</th>
                <th data-col="tags">Tags</th>
            </tr>
            <tr class="dt-filter">
                <th data-filter="text"></th>
                <th data-filter="text"></th>
                <th data-filter="text"></th>
                <th data-filter="select" data-options='<?= htmlspecialchars(json_encode(array_values($industries ?? [])), ENT_QUOTES) ?>'></th>
                <th data-filter="select" data-options='<?= htmlspecialchars(json_encode(array_values($sources ?? [])), ENT_QUOTES) ?>'></th>
                <th data-filter="text"></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

</section>

<?php include __DIR__ . '/../../../Shared/datatables_assets.php'; ?>
