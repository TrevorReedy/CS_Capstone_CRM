<?php
namespace App\Modules\Dashboard\Cards;

use App\Modules\Dashboard\DashboardCard;

/**
 * OWNER: Trevor (Campaign)
 * Stat card — total recipients reached across every campaign that has gone out,
 * summed in SQL as part of the shared campaign stats read. Drafts and pending
 * Scheduled campaigns are excluded: they have not reached anyone yet.
 */
class CampaignReachCard extends DashboardCard
{
    public function title(): string { return 'Total Reach'; }

    public function permission(): ?string { return 'campaigns.view'; }

    public function body(): string
    {
        $reach     = $this->service->campaignTotalReach();
        $campaigns = $this->service->sentCampaignCount();

        $sub = $campaigns === 0
            ? 'No campaigns sent yet'
            : 'Recipients across ' . $campaigns . ' sent campaign' . ($campaigns === 1 ? '' : 's');

        return $this->stat(
            number_format($reach),
            $sub,
            '/modules/campaign/campaigns.php',
            'View campaigns'
        );
    }
}
