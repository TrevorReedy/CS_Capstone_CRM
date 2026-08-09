<?php
namespace App\Modules\Dashboard\Cards;

use App\Modules\Dashboard\DashboardCard;
use App\Modules\Customer\CustomerRepository;

/**
 * Preview card — the most recently logged customer interactions, backed by
 * CustomerRepository::recentInteractions().
 */

class RecentInteractionsCard extends DashboardCard
{
    public function body(): string
    {
        $repo = new CustomerRepository();

        $interactions = $repo->recentInteractions();

        $rows = [];

        foreach ($interactions as $interaction) {
            $rows[] = [
                'label' => $interaction['interaction_subject'],
                'badge' => ucfirst($interaction['interaction_type']),
                'badge_class' => $this->badgeClass($interaction['interaction_type']),
                'meta' => mb_strimwidth($interaction['account_name'], 0, 25, '...'),
                'date' => date('M j, g:i A', strtotime($interaction['interaction_date']))
            ];
        }

        return $this->preview(
            $rows,
            '/modules/customer/accounts.php',
            'View accounts'
        );
    }

    public function title(): string { return 'Recent Interactions'; }

    public function permission(): ?string { return 'customers.view'; }

    private function badgeClass(string $type): string
    {
        return match (strtolower($type)) {
            'call'    => 'rfq-badge-info',
            'email'   => 'rfq-badge-success',
            'meeting' => 'rfq-badge-warning',
            'note'    => 'rfq-badge-neutral',
            default   => 'rfq-badge-info',
        };
    }
}