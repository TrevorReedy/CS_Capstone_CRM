<?php
declare(strict_types=1);

namespace Tests\Integration\Modules;

use App\Modules\Campaign\CampaignRepository;
use App\Modules\Campaign\CampaignService;
use App\Modules\Customer\CustomerRepository;
use App\Modules\RFQ\RFQRepository;
use App\Modules\RFQ\RFQService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Read and write paths through the repositories, plus the two service
 * operations that span more than one table.
 */
#[CoversClass(CustomerRepository::class)]
#[CoversClass(CampaignRepository::class)]
#[CoversClass(RFQRepository::class)]
final class RepositoryTest extends IntegrationTestCase
{
    // ── Customers ───────────────────────────────────────────────────────────

    #[Test]
    public function accounts_can_be_listed_and_fetched_by_id(): void
    {
        $repo = new CustomerRepository();

        $all = $repo->all();
        $this->assertNotEmpty($all);
        $this->assertSame($this->rowCount('accounts'), count($all));

        $found = $repo->find((int) $all[0]['id']);
        $this->assertNotNull($found);
        $this->assertSame($all[0]['account_name'], $found['account_name']);
    }

    #[Test]
    public function fetching_a_missing_account_returns_null_rather_than_throwing(): void
    {
        $this->assertNull((new CustomerRepository())->find(987654));
    }

    #[Test]
    public function an_account_can_be_created_and_deleted(): void
    {
        $repo   = new CustomerRepository();
        $before = $this->rowCount('accounts');

        // Every named placeholder must be present: create() hands the array
        // straight to execute(), so a missing key is "Invalid parameter number"
        // rather than a NULL column.
        $repo->create([
            'account_name' => 'Integration Test Co',
            'email'        => 'contact@integration.test',
            'phone'        => '555-0100',
            'address'      => '1 Test Way',
            'industry'     => 'Testing',
            'source'       => 'Referral',
            'tags'         => 'test',
        ]);

        $this->assertSame($before + 1, $this->rowCount('accounts'));

        $created = $this->fetchOne('SELECT id FROM accounts WHERE account_name = ?', ['Integration Test Co']);
        $repo->delete((int) $created['id']);

        $this->assertSame($before, $this->rowCount('accounts'));
    }

    /**
     * distinctValues() interpolates the column name straight into the SQL, so
     * the whitelist is the only thing standing between it and injection. An
     * unlisted column must return nothing rather than reach the database.
     */
    #[Test]
    public function distinct_values_serves_only_whitelisted_columns(): void
    {
        $repo = new CustomerRepository();

        $this->assertNotEmpty($repo->distinctValues('industry'));
        $this->assertSame([], $repo->distinctValues('email'));
        $this->assertSame([], $repo->distinctValues('password_hash'));
        $this->assertSame([], $repo->distinctValues('industry FROM accounts UNION SELECT password_hash'));
    }

    #[Test]
    public function distinct_values_are_unique_and_never_blank(): void
    {
        $values = (new CustomerRepository())->distinctValues('industry');

        $this->assertSame(array_values(array_unique($values)), $values);
        $this->assertNotContains('', $values);
        $this->assertNotContains(null, $values);
    }

    // ── Campaigns ───────────────────────────────────────────────────────────

    #[Test]
    public function a_campaign_can_be_created_updated_and_read_back(): void
    {
        $service = new CampaignService();
        $userId  = (int) $this->fetchOne('SELECT id FROM users LIMIT 1')['id'];

        $id = $service->createCampaign([
            'campaign_name' => 'Integration campaign',
            'campaign_type' => 'Email',
            'status'        => 'Draft',
            'description'   => 'Created by the integration suite.',
        ], $userId);

        $campaign = (new CampaignRepository())->findById($id);
        $this->assertNotNull($campaign);
        $this->assertSame('Integration campaign', $campaign['campaign_name']);
        $this->assertSame('Draft', $campaign['status']);

        $service->updateCampaign($id, [
            'campaign_name' => 'Integration campaign (edited)',
            'campaign_type' => 'Email',
            'status'        => 'Scheduled',
            'description'   => 'Edited.',
        ]);

        $this->assertSame('Scheduled', (new CampaignRepository())->findById($id)['status']);
    }

    #[Test]
    public function fetching_a_missing_campaign_returns_null(): void
    {
        $this->assertNull((new CampaignRepository())->findById(987654));
    }

    /**
     * The send is simulated — no mail leaves the system — but sent_count is
     * real: it is the size of the campaign's audience. That is the whole reason
     * the fabricated open/click rates were dropped in migration 019.
     */
    #[Test]
    public function simulating_a_send_marks_the_campaign_sent_and_records_a_real_recipient_count(): void
    {
        $service = new CampaignService();
        $userId  = (int) $this->fetchOne('SELECT id FROM users LIMIT 1')['id'];

        $id = $service->createCampaign([
            'campaign_name' => 'Send test',
            'campaign_type' => 'Email',
            'status'        => 'Draft',
            'description'   => '',
        ], $userId);

        $accounts = $this->db->query('SELECT id FROM accounts LIMIT 3')->fetchAll(\PDO::FETCH_COLUMN);
        $service->addAudienceSegment($id, [
            'segment_name' => 'Test segment',
            'account_ids'  => $accounts,
        ]);

        $service->simulateSend($id);

        $campaign = (new CampaignRepository())->findById($id);
        $this->assertSame('Sent', $campaign['status']);
        $this->assertSame(count($accounts), (int) $campaign['sent_count']);
    }

    // ── RFQs ────────────────────────────────────────────────────────────────

    #[Test]
    public function an_rfq_can_be_created_with_a_quote_in_one_operation(): void
    {
        $service = new RFQService(new RFQRepository());
        $userId  = (int) $this->fetchOne('SELECT id FROM users LIMIT 1')['id'];
        $account = (int) $this->fetchOne('SELECT id FROM accounts LIMIT 1')['id'];

        $rfqId = $service->createRFQ(
            ['title' => 'Integration RFQ', 'account_id' => $account, 'stage' => 'Quoted', 'description' => ''],
            [
                'quote_amount'        => 2500.00,
                'discount'            => 250.00,
                'validity_start_date' => '2026-08-01',
                'validity_end_date'   => '2026-09-01',
            ],
            [],
            $userId
        );

        $this->assertSame(1, $this->rowCount('rfqs', 'id = ?', [$rfqId]));
        $this->assertSame(1, $this->rowCount('quotes', 'rfq_id = ?', [$rfqId]));
    }

    /**
     * validateQuoteInput() treats the validity window as optional, so a quote
     * with neither date is a supported path and must save without warnings —
     * failOnWarning in phpunit.xml is what makes that assertion real. Both
     * columns end up NULL rather than an empty string.
     */
    #[Test]
    public function an_rfq_can_be_created_with_a_quote_that_has_no_validity_window(): void
    {
        $service = new RFQService(new RFQRepository());
        $userId  = (int) $this->fetchOne('SELECT id FROM users LIMIT 1')['id'];
        $account = (int) $this->fetchOne('SELECT id FROM accounts LIMIT 1')['id'];

        $rfqId = $service->createRFQ(
            ['title' => 'No validity window', 'account_id' => $account, 'stage' => 'Quoted', 'description' => ''],
            ['quote_amount' => 900.00],
            [],
            $userId
        );

        $quote = $this->fetchOne('SELECT * FROM quotes WHERE rfq_id = ?', [$rfqId]);
        $this->assertNotNull($quote);
        $this->assertNull($quote['validity_start_date']);
        $this->assertNull($quote['validity_end_date']);
    }

    #[Test]
    public function changing_an_rfq_stage_persists(): void
    {
        $service = new RFQService(new RFQRepository());
        $rfqId   = (int) $this->fetchOne("SELECT id FROM rfqs WHERE stage <> 'Won' LIMIT 1")['id'];

        $service->changeStage($rfqId, 'Negotiation');

        $this->assertSame('Negotiation', $this->fetchOne('SELECT stage FROM rfqs WHERE id = ?', [$rfqId])['stage']);
    }

    /**
     * The stage filter interpolates its values into an IN (...) list, so it is
     * guarded by array_intersect against the whitelist rather than by binding.
     * A forged stage is dropped; a legitimate one alongside it still applies.
     */
    #[Test]
    public function the_rfq_stage_filter_ignores_values_outside_the_whitelist(): void
    {
        $repo = new RFQRepository();

        $this->assertSame(
            $this->rowCount('rfqs', "stage = 'Won'"),
            $repo->searchCount('', '', ['Won'])
        );

        // The forged entry is discarded and only 'Won' survives the whitelist.
        $this->assertSame(
            $this->rowCount('rfqs', "stage = 'Won'"),
            $repo->searchCount('', '', ['Won', "Lost') OR ('1'='1"]),
            'a forged stage must not widen the result set'
        );

        // A filter of nothing but forged values leaves no filter at all, which
        // is the unfiltered count — never an error, and never injected rows.
        $this->assertSame(
            $this->rowCount('rfqs'),
            $repo->searchCount('', '', ["Won') OR ('1'='1"])
        );
    }
}
