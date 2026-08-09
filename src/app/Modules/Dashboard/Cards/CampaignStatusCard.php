<?php
namespace App\Modules\Dashboard\Cards;

use App\Modules\Dashboard\DashboardCard;

/**
 * OWNER: Trevor (Campaign)
 * Preview card — campaign count per status, in workflow order (Draft → Scheduled
 * → Sent → Completed). The counts come from the shared single-pass campaign stats
 * read, so this card adds no query of its own.
 */
class CampaignStatusCard extends DashboardCard
{
    public function title(): string { return 'Campaigns by Status'; }

    public function permission(): ?string { return 'campaigns.view'; }

    public function body(): string
    {
        $total = $this->service->totalCampaignCount();
        $rows  = [];

        foreach ($this->service->campaignStatusBreakdown() as $r) {
            $count = (int)$r['count'];

            // Share of the whole book, so the counts read as a distribution
            // rather than four unrelated numbers. Guarded against an empty table.
            $meta = $total > 0
                ? round($count / $total * 100) . '% of ' . $total
                : 'No campaigns yet';

            $rows[] = [
                'label'       => $r['status'],
                'meta'        => $meta,
                'badge'       => (string)$count,
                'badge_class' => $this->campaignStatusBadgeClass($r['status']),
            ];
        }

        return $this->preview($rows, '/modules/campaign/campaigns.php', 'View campaigns');
    }
}
