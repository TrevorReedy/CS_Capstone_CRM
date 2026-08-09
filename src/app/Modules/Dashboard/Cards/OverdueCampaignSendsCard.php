<?php
namespace App\Modules\Dashboard\Cards;

use App\Modules\Dashboard\DashboardCard;

/**
 * OWNER: Trevor (Campaign)
 * Preview card — campaigns still marked Scheduled whose send time has already
 * passed. Sends are simulated manually (there is no cron), so nothing moves these
 * along on its own; they sit here until someone sends or reschedules them. The
 * counterpart to UpcomingCampaignSendsCard.
 */
class OverdueCampaignSendsCard extends DashboardCard
{
    private const LIMIT = 5;

    public function title(): string { return 'Overdue Sends'; }

    public function permission(): ?string { return 'campaigns.view'; }

    public function body(): string
    {
        $rows = [];

        foreach ($this->service->overdueCampaignSends(self::LIMIT) as $c) {
            $days = (int)$c['days_overdue'];

            $rows[] = [
                'label'       => $c['campaign_name'],
                'meta'        => $c['campaign_type'],
                'date'        => 'Was due ' . date('M j, g:i A', strtotime($c['scheduled_at'])),
                'badge'       => $days <= 0 ? 'Due now' : $days . 'd late',
                'badge_class' => $days >= 7 ? 'rfq-badge-danger' : 'rfq-badge-warning',
                'href'        => '/modules/campaign/detail.php?id=' . (int)$c['id'],
            ];
        }

        // The list is capped at LIMIT; say so when more are waiting behind it.
        $total = $this->service->overdueCampaignCount();
        $label = $total > self::LIMIT
            ? 'View all ' . $total . ' overdue'
            : 'View campaigns';

        return $this->preview($rows, '/modules/campaign/campaigns.php', $label);
    }
}
