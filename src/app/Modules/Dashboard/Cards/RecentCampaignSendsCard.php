<?php
namespace App\Modules\Dashboard\Cards;

use App\Modules\Dashboard\DashboardCard;

/**
 * OWNER: Trevor (Campaign)
 * Preview card — campaigns that have already gone out, newest first, with the
 * recipient count each one reached. Answers "what shipped lately" without
 * opening the module.
 */
class RecentCampaignSendsCard extends DashboardCard
{
    private const LIMIT = 5;

    public function title(): string { return 'Recent Sends'; }

    public function permission(): ?string { return 'campaigns.view'; }

    public function body(): string
    {
        $rows = [];

        foreach ($this->service->recentCampaignSends(self::LIMIT) as $c) {
            $sent = (int)$c['sent_count'];

            $rows[] = [
                'label'       => $c['campaign_name'],
                'meta'        => $c['campaign_type'] . ' · ' . $c['status'],
                'date'        => $c['sent_at'] ? date('M j, Y', strtotime($c['sent_at'])) : '',
                'badge'       => number_format($sent) . ($sent === 1 ? ' recipient' : ' recipients'),
                'badge_class' => $this->campaignStatusBadgeClass($c['status']),
                'href'        => '/modules/campaign/detail.php?id=' . (int)$c['id'],
            ];
        }

        return $this->preview($rows, '/modules/campaign/campaigns.php', 'View campaigns');
    }
}
