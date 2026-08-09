<?php
use App\Modules\RFQ\RFQRepository;
?>
<section class="card">
    <div class="rfq-list-toolbar">
        <div class="page-header">
            <h2 class="rfq-list-title">All RFQs</h2>
            <a href="/modules/rfq/create.php" class="btn btn-primary" title="Create RFQ">+ New RFQ</a>
        </div>
    </div>

    <table class="table rfq-list-table js-dt"
           data-dt-url="/modules/rfq/pipeline_data.php"
           data-dt-export="/modules/rfq/pipeline_export.php">
        <thead>
            <tr class="dt-title">
                <th data-col="id">#</th>
                <th data-col="title">Title</th>
                <th data-col="account_name">Account</th>
                <th data-col="stage">Stage</th>
                <th data-col="created_at">Created</th>
                <th data-col="updated_at">Updated</th>
            </tr>
            <tr class="dt-filter">
                <th data-filter="text"></th>
                <th data-filter="text"></th>
                <th data-filter="text"></th>
                <th data-filter="select" data-options='<?= htmlspecialchars(json_encode(RFQRepository::$stages), ENT_QUOTES) ?>'></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<?php include __DIR__ . '/../../../Shared/datatables_assets.php'; ?>
