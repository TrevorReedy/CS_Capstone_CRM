<?php
namespace App\Modules\Dashboard\Cards;

use App\Modules\Dashboard\DashboardCard;

/**
 * OWNER: Trevor (Campaign)
 * Preview card — drafts still being put together, oldest first so the stalest
 * surface first. The badge flags the one thing that blocks a draft from going
 * out: no audience selected yet.
 */
class DraftCampaignsCard extends DashboardCard
{
    private const LIMIT = 5;

    public function title(): string { return 'Drafts in Progress'; }

    public function permission(): ?string { return 'campaigns.view'; }

    public function body(): string
    {
        $rows = [];

        foreach ($this->service->draftCampaigns(self::LIMIT) as $c) {
            $hasAudience = (bool)$c['has_audience'];
            $days        = (int)$c['days_old'];

            $rows[] = [
                'label'       => $c['campaign_name'],
                'meta'        => $c['campaign_type'],
                'date'        => $days === 0 ? 'Created today' : 'Created ' . $days . 'd ago',
                'badge'       => $hasAudience ? 'Ready' : 'No audience',
                'badge_class' => $hasAudience ? 'rfq-badge-success' : 'rfq-badge-warning',
                'href'        => '/modules/campaign/detail.php?id=' . (int)$c['id'],
            ];
        }

        return $this->preview($rows, '/modules/campaign/campaigns.php', 'View campaigns');
    }
}
