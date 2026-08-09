<?php
namespace App\Modules\Dashboard;

class DashboardController
{
    private DashboardService $service;

    public function __construct()
    {
        $this->service = new DashboardService();
    }

    public function index(): void
    {
        // Group cards by domain, keeping only the ones this user may see. Groups
        // that end up empty for this role are dropped, so a single-role user
        // never sees an empty section header.
        $sections = [];
        foreach ($this->cards() as $group => $cards) {
            $visible = array_filter($cards, fn(DashboardCard $c) => $c->visible());
            if ($visible) {
                $sections[$group] = $visible;
            }
        }

        include __DIR__ . '/views/dashboard.php';
    }

    /**
     * The dashboard card registry, grouped into self-contained domain sections.
     * The key is the section heading; array order is display order (both of
     * sections and of cards within a section).
     *
     * To add a card: create a class under Cards/ extending DashboardCard, then
     * add it to the appropriate domain group below.
     */
    private function cards(): array
    {
        return [
            'RFQ Pipeline' => [
                new Cards\ActiveRfqsCard($this->service),
                new Cards\RfqValueByStageCard($this->service),
                new Cards\RecentRfqsCard($this->service),
                new Cards\WinRateByAccountCard($this->service),
                new Cards\ExpiringQuotesCard($this->service),
            ],
            'Campaigns' => [
                new Cards\ActiveCampaignsCard($this->service),
                new Cards\CampaignReachCard($this->service),
                new Cards\CampaignStatusCard($this->service),
                new Cards\UpcomingCampaignSendsCard($this->service),
                new Cards\OverdueCampaignSendsCard($this->service),
                new Cards\RecentCampaignSendsCard($this->service),
                new Cards\DraftCampaignsCard($this->service),
            ],
            'Customers' => [
                new Cards\TotalAccountsCard($this->service),
                new Cards\RecentInteractionsCard($this->service),
            ],
            'Inventory' => [
                new Cards\ReservedInventoryCard($this->service),
                new Cards\LowStockCard($this->service),
                new Cards\TopReservedProductsCard($this->service),
                new Cards\PendingReservationsCard($this->service),
                new Cards\HeavilyReservedCard($this->service),
            ],
        ];
    }
}
