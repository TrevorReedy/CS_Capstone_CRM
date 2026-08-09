<?php
use App\Modules\Campaign\CampaignRepository;
?>

<section class="card">
    <div class="page-header">
        <h1>Campaigns</h1>
        <a href="/modules/campaign/create.php" class="btn btn-primary">+ Create Campaign</a>
    </div>

    <table class="table js-dt"
           data-dt-url="/modules/campaign/campaigns_data.php"
           data-dt-export="/modules/campaign/campaigns_export.php">
        <thead>
            <tr class="dt-title">
                <th data-col="id">#</th>
                <th data-col="campaign_name">Name</th>
                <th data-col="campaign_type">Type</th>
                <th data-col="status">Status</th>
                <th data-col="sent_count">Sent</th>
                <th data-col="created_at">Created</th>
            </tr>
            <tr class="dt-filter">
                <th data-filter="text"></th>
                <th data-filter="text"></th>
                <th data-filter="select" data-options='<?= htmlspecialchars(json_encode(CampaignRepository::$types), ENT_QUOTES) ?>'></th>
                <th data-filter="select" data-options='<?= htmlspecialchars(json_encode(CampaignRepository::$statuses), ENT_QUOTES) ?>'></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</section>

<?php include __DIR__ . '/../../../Shared/datatables_assets.php'; ?>
