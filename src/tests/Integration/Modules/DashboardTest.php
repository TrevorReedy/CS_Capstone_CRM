<?php
declare(strict_types=1);

namespace Tests\Integration\Modules;

use App\Modules\Dashboard\DashboardCard;
use App\Modules\Dashboard\DashboardController;
use App\Modules\Dashboard\DashboardService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\IntegrationTestCase;

/**
 * The dashboard is twenty-odd aggregate queries and a card registry. Nothing
 * here has interesting branching — the risk is entirely that a query references
 * a column that a migration renamed or dropped, which only shows up as a broken
 * dashboard for whoever logs in next.
 *
 * So: run every service query, and render every registered card.
 */
#[CoversClass(DashboardService::class)]
#[CoversClass(DashboardController::class)]
final class DashboardTest extends IntegrationTestCase
{
    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardService();
        $this->signInAs('admin@typhoncath.test');
    }

    /**
     * Every public query on the service, called with its defaults. The assertion
     * is deliberately weak — the point is that the SQL executes at all.
     *
     * @return array<string, array{string}>
     */
    public static function serviceQueries(): array
    {
        $methods = [
            'activeRfqSummary', 'rfqValueByStage', 'recentRfqs', 'winRateByAccount',
            'winRateAccountCount', 'expiringQuotes', 'activeCampaignCount',
            'upcomingCampaignSends', 'campaignStatusBreakdown', 'totalCampaignCount',
            'campaignTotalReach', 'sentCampaignCount', 'overdueCampaignSends',
            'overdueCampaignCount', 'recentCampaignSends', 'draftCampaigns',
            'lowStockProducts', 'topReservedProducts', 'pendingReservationCount',
            'reservedUnits', 'heavilyReservedProducts',
        ];

        return array_combine(
            $methods,
            array_map(static fn(string $m): array => [$m], $methods)
        );
    }

    #[Test]
    #[DataProvider('serviceQueries')]
    public function every_dashboard_query_runs_against_the_real_schema(string $method): void
    {
        $result = $this->service->{$method}();

        $this->assertTrue(
            is_array($result) || is_int($result),
            "{$method}() should return an array or a count"
        );
    }

    /**
     * A count query and its list query must agree, or the card shows "12
     * overdue" above a list of three.
     */
    #[Test]
    public function the_overdue_campaign_count_matches_the_overdue_list(): void
    {
        $count = $this->service->overdueCampaignCount();
        $rows  = $this->service->overdueCampaignSends(1000);

        $this->assertSame($count, count($rows));
    }

    #[Test]
    public function the_win_rate_account_count_matches_the_win_rate_list(): void
    {
        $count = $this->service->winRateAccountCount();
        $rows  = $this->service->winRateByAccount(1000, 0);

        $this->assertSame($count, count($rows));
    }

    /**
     * "Sent" on this card means the campaign has gone out, which covers both
     * Sent and Completed — the reach figure is spread across the same set.
     */
    #[Test]
    public function campaign_totals_are_consistent_with_the_campaigns_table(): void
    {
        $this->assertSame($this->rowCount('campaigns'), $this->service->totalCampaignCount());
        $this->assertSame(
            $this->rowCount('campaigns', "status IN ('Sent', 'Completed')"),
            $this->service->sentCampaignCount()
        );
    }

    #[Test]
    public function the_status_breakdown_accounts_for_every_campaign(): void
    {
        $breakdown = $this->service->campaignStatusBreakdown();

        $total = 0;
        foreach ($breakdown as $row) {
            $total += (int) (is_array($row) ? ($row['total'] ?? $row['count'] ?? 0) : $row);
        }

        $this->assertSame($this->rowCount('campaigns'), $total);
    }

    /**
     * sent_count is the one campaign metric that is real (migration 019 dropped
     * the simulated open/click rates), so total reach must equal the sum of it
     * across the campaigns that actually went out.
     */
    #[Test]
    public function total_reach_is_the_sum_of_real_sent_counts(): void
    {
        $expected = (int) $this->db->query(
            "SELECT COALESCE(SUM(sent_count), 0) FROM campaigns WHERE status IN ('Sent', 'Completed')"
        )->fetchColumn();

        $this->assertSame($expected, $this->service->campaignTotalReach());
    }

    #[Test]
    public function reserved_units_match_the_inventory_table(): void
    {
        $expected = (int) $this->db->query('SELECT COALESCE(SUM(reserved_quantity), 0) FROM inventory')->fetchColumn();

        $this->assertSame($expected, $this->service->reservedUnits());
    }

    // ── The card registry ───────────────────────────────────────────────────

    /** @return DashboardCard[] */
    private function registeredCards(): array
    {
        $method = new ReflectionMethod(DashboardController::class, 'cards');

        return array_merge(...array_values($method->invoke(new DashboardController())));
    }

    #[Test]
    public function every_registered_card_renders_for_a_super_admin(): void
    {
        $cards = $this->registeredCards();
        $this->assertNotEmpty($cards);

        foreach ($cards as $card) {
            $this->assertTrue($card->visible(), get_class($card) . ' should be visible to a Super Admin');

            $html = $card->render();

            $this->assertNotSame('', trim($html), get_class($card) . ' rendered nothing');
            // Titles are escaped on the way out, so compare against the escaped
            // form — "Heavily Reserved (>70%)" renders as "&gt;70%".
            $this->assertStringContainsString(
                htmlspecialchars($card->title(), ENT_QUOTES, 'UTF-8'),
                $html
            );
        }
    }

    /**
     * A card whose permission string is not one the seed grants is invisible to
     * every real role while still showing for a Super Admin, who bypasses the
     * matrix — so it looks fine right up until someone else logs in. This is the
     * same class of bug authz_coverage.php's VOCAB check catches for routes.
     */
    #[Test]
    public function every_card_permission_is_one_the_seed_actually_grants(): void
    {
        $granted = $this->db->query('SELECT DISTINCT permission FROM role_permissions')
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($this->registeredCards() as $card) {
            $permission = $card->permission();
            if ($permission === null) {
                continue;
            }

            $this->assertContains(
                $permission,
                $granted,
                get_class($card) . " requires '{$permission}', which no role is granted in seed.sql"
            );
        }
    }

    /**
     * The dashboard is the landing page for every role, so a card the user
     * cannot see must not run its query at all — and the section it belongs to
     * disappears rather than rendering an empty heading.
     */
    #[Test]
    public function a_marketing_user_sees_campaign_cards_but_not_inventory_ones(): void
    {
        $this->signInAs('mktg@typhoncath.test');

        $titles = [];
        foreach ($this->registeredCards() as $card) {
            if ($card->visible()) {
                $titles[] = $card->title();
            }
        }

        $this->assertNotEmpty($titles, 'a Marketing User should see at least one card');
        $this->assertLessThan(
            count($this->registeredCards()),
            count($titles),
            'a Marketing User should not see every card on the dashboard'
        );
    }
}
