<?php
namespace App\Modules\Dashboard;

use App\Modules\RFQ\RFQRepository;
use App\Modules\Campaign\CampaignRepository;

/**
 * DashboardService — the single place the dashboard reaches module data.
 *
 * Request flow:  Dashboard View → DashboardController → DashboardService →
 *                RFQ/Campaign repositories → Database
 *
 * The service only *coordinates*: every aggregate is computed in SQL by a
 * narrowly-scoped repository method, and the service reshapes/labels the result
 * for the cards. It never writes SQL itself and never duplicates RFQ or Campaign
 * business logic — it composes the two module repositories and shares one
 * campaign-stats read across every campaign stat card.
 */
class DashboardService
{
    /**
     * Which RFQ stages count as "active" for the Active RFQs card.
     * Quoted + Negotiation: late-pipeline RFQs that have real money in play.
     */
    public const ACTIVE_RFQ_STAGES = ['Quoted', 'Negotiation'];

    /**
     * Units at/below which a product counts as "low stock" on the dashboard.
     * A flat threshold (the Inventory module has no per-product reorder column) —
     * mirrors InventoryService::LOW_STOCK_THRESHOLD so both views agree.
     */
    public const LOW_STOCK_THRESHOLD = 10;

    /** Reservation share above which a product is "heavily reserved" (0.70 = 70%). */
    public const HEAVILY_RESERVED_RATIO = 0.70;

    private RFQRepository $rfq;
    private CampaignRepository $campaign;
    private DashboardRepository $dashboard;

    /** Memoised single read of campaign summary stats (shared by four cards). */
    private ?array $campaignStats = null;

    public function __construct(
        ?RFQRepository $rfq = null,
        ?CampaignRepository $campaign = null,
        ?DashboardRepository $dashboard = null
    ) {
        $this->rfq       = $rfq       ?? new RFQRepository();
        $this->campaign  = $campaign  ?? new CampaignRepository();
        $this->dashboard = $dashboard ?? new DashboardRepository();
    }

    // ── RFQ ───────────────────────────────────────────────────────────────────

    /** @return array{count:int,total_value:float} Active RFQ count + total quoted value. */
    public function activeRfqSummary(): array
    {
        return $this->rfq->stageSummary(self::ACTIVE_RFQ_STAGES);
    }

    /** Per-stage count + total/avg quoted value (RFQ Value by Stage card). */
    public function rfqValueByStage(): array
    {
        return $this->rfq->totalValueByStage();
    }

    /** The $limit most recently updated RFQs (Recent RFQs card). */
    public function recentRfqs(int $limit = 5): array
    {
        return $this->rfq->recentRfqs($limit);
    }

    /** Top accounts by win rate (Win Rate by Account card / drill-down page). */
    public function winRateByAccount(int $limit = 5, int $offset = 0): array
    {
        return $this->rfq->winRateByAccount($limit, $offset);
    }

    /** Population size for the win-rate drill-down pager. */
    public function winRateAccountCount(): int
    {
        return $this->rfq->winRateByAccountCount();
    }

    /** Soonest-expiring / overdue quotes on active RFQs (Expiring Quotes card). */
    public function expiringQuotes(int $limit = 5): array
    {
        return $this->rfq->quotesExpiringSoon($limit);
    }

    // ── Campaign ──────────────────────────────────────────────────────────────

    /** Campaigns that are Scheduled or Sent (Active Campaigns card). */
    public function activeCampaignCount(): int
    {
        return (int)($this->campaignStats()['active'] ?? 0);
    }

    /** Campaigns scheduled to go out soonest (Upcoming Sends card). */
    public function upcomingCampaignSends(int $limit = 5): array
    {
        return $this->campaign->upcomingScheduledSends($limit);
    }

    /**
     * Campaign count per status, in workflow order (Campaign Status card).
     * Reshaped from the shared stats read — no extra query.
     *
     * @return array<int, array{status:string, count:int}>
     */
    public function campaignStatusBreakdown(): array
    {
        $stats = $this->campaignStats();
        $rows  = [];

        foreach (CampaignRepository::$statuses as $status) {
            $rows[] = [
                'status' => $status,
                'count'  => (int)($stats[strtolower($status)] ?? 0),
            ];
        }

        return $rows;
    }

    /** Total campaigns on record, all statuses (denominator for the status shares). */
    public function totalCampaignCount(): int
    {
        return (int)($this->campaignStats()['total'] ?? 0);
    }

    /** Recipients reached across every campaign that has gone out (Total Reach card). */
    public function campaignTotalReach(): int
    {
        return (int)($this->campaignStats()['total_reach'] ?? 0);
    }

    /** How many campaigns that reach is spread across (Total Reach sub-line). */
    public function sentCampaignCount(): int
    {
        return (int)($this->campaignStats()['sent_completed'] ?? 0);
    }

    /** Scheduled campaigns whose send time has passed (Overdue Sends card). */
    public function overdueCampaignSends(int $limit = 5): array
    {
        return $this->campaign->overdueScheduledSends($limit);
    }

    /** Total overdue count, so the card can say how many it truncated. */
    public function overdueCampaignCount(): int
    {
        return (int)($this->campaignStats()['overdue'] ?? 0);
    }

    /** Campaigns that have already gone out, newest first (Recent Sends card). */
    public function recentCampaignSends(int $limit = 5): array
    {
        return $this->campaign->recentSends($limit);
    }

    /** Drafts still in progress, oldest first, flagged for audience (Drafts card). */
    public function draftCampaigns(int $limit = 5): array
    {
        return $this->campaign->draftCampaigns($limit);
    }

    // ── Inventory ─────────────────────────────────────────────────────────────

    /** Products at or below the low-stock threshold (Low Stock card). */
    public function lowStockProducts(int $limit = 5): array
    {
        return $this->dashboard->lowStock(self::LOW_STOCK_THRESHOLD, $limit);
    }

    /** Products with the most units reserved across active RFQs (Top Reserved card). */
    public function topReservedProducts(int $limit = 5): array
    {
        return $this->dashboard->topReserved($limit);
    }

    /** Count of reservations still held (not released/converted) (Pending Reservations card). */
    public function pendingReservationCount(): int
    {
        return $this->dashboard->pendingReservationCount();
    }

    /** Total units held across active reservations (Reserved Inventory card). */
    public function reservedUnits(): int
    {
        return $this->dashboard->reservedUnits();
    }

    /** Products whose reserved share exceeds the heavily-reserved ratio (Heavily Reserved card). */
    public function heavilyReservedProducts(int $limit = 10): array
    {
        return $this->dashboard->heavilyReserved(self::HEAVILY_RESERVED_RATIO, $limit);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function campaignStats(): array
    {
        return $this->campaignStats ??= $this->campaign->dashboardStats();
    }
}
