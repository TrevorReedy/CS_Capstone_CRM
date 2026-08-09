<?php
declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Core\Database;
use App\Modules\Campaign\CampaignRepository;
use App\Modules\Campaign\CampaignService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\NeverConnectingPdo;

#[CoversClass(CampaignService::class)]
final class CampaignServiceTest extends TestCase
{
    private CampaignService $service;

    protected function setUp(): void
    {
        // CampaignRepository resolves Database::connection() in its
        // constructor, so building the service reaches for a connection before
        // any query runs. None of the methods under test issue one.
        Database::swap(new NeverConnectingPdo());
        $this->service = new CampaignService();
    }

    protected function tearDown(): void
    {
        Database::swap(null);
    }

    #[Test]
    public function every_seeded_type_and_status_is_accepted(): void
    {
        foreach (CampaignRepository::$types as $type) {
            $this->assertTrue($this->service->isValidType($type), "$type should be valid");
        }
        foreach (CampaignRepository::$statuses as $status) {
            $this->assertTrue($this->service->isValidStatus($status), "$status should be valid");
        }
    }

    #[Test]
    public function unknown_types_and_statuses_are_rejected(): void
    {
        $this->assertFalse($this->service->isValidType('Carrier Pigeon'));
        $this->assertFalse($this->service->isValidType('email'));
        $this->assertFalse($this->service->isValidStatus('Archived'));
        $this->assertFalse($this->service->isValidStatus(''));
    }

    #[Test]
    public function a_complete_campaign_passes_validation(): void
    {
        $errors = $this->service->validateCampaignInput([
            'campaign_name' => 'August newsletter',
            'campaign_type' => 'Email',
            'status'        => 'Draft',
        ]);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function a_campaign_needs_a_name(): void
    {
        $errors = $this->service->validateCampaignInput([
            'campaign_name' => '  ', 'campaign_type' => 'Email', 'status' => 'Draft',
        ]);

        $this->assertContains('Campaign name is required.', $errors);
    }

    /** The column is VARCHAR(255); a longer name would be truncated silently. */
    #[Test]
    public function a_campaign_name_is_capped_at_the_column_width(): void
    {
        $errors = $this->service->validateCampaignInput([
            'campaign_name' => str_repeat('a', 256), 'campaign_type' => 'Email', 'status' => 'Draft',
        ]);
        $this->assertContains('Campaign name must be 255 characters or fewer.', $errors);

        $ok = $this->service->validateCampaignInput([
            'campaign_name' => str_repeat('a', 255), 'campaign_type' => 'Email', 'status' => 'Draft',
        ]);
        $this->assertSame([], $ok);
    }

    #[Test]
    public function an_invalid_type_or_status_is_reported(): void
    {
        $errors = $this->service->validateCampaignInput([
            'campaign_name' => 'Newsletter', 'campaign_type' => 'Telepathy', 'status' => 'Pending',
        ]);

        $this->assertContains('Invalid campaign type.', $errors);
        $this->assertContains('Invalid status.', $errors);
    }

    #[Test]
    public function missing_fields_are_reported_rather_than_defaulted(): void
    {
        $errors = $this->service->validateCampaignInput([]);

        $this->assertCount(3, $errors);
    }

    // ── Audience segments ───────────────────────────────────────────────────

    #[Test]
    public function an_audience_segment_needs_a_name(): void
    {
        $errors = $this->service->validateAudienceInput(['segment_name' => '', 'tag_filter' => 'vip']);

        $this->assertContains('Segment name is required.', $errors);
    }

    /**
     * A segment with no targeting would resolve to an empty audience, and
     * simulateSend() would then record a sent_count of zero against a campaign
     * that looks like it went out.
     */
    #[Test]
    public function an_audience_segment_needs_at_least_one_target(): void
    {
        $errors = $this->service->validateAudienceInput(['segment_name' => 'Everyone']);

        $this->assertContains('Provide a tag filter or select at least one account or contact.', $errors);
    }

    #[Test]
    public function any_one_targeting_method_satisfies_the_segment(): void
    {
        $this->assertSame([], $this->service->validateAudienceInput(['segment_name' => 'S', 'tag_filter' => 'vip']));
        $this->assertSame([], $this->service->validateAudienceInput(['segment_name' => 'S', 'account_ids' => [1, 2]]));
        $this->assertSame([], $this->service->validateAudienceInput(['segment_name' => 'S', 'contact_ids' => [3]]));
    }

    #[Test]
    public function empty_target_arrays_do_not_count_as_targeting(): void
    {
        $errors = $this->service->validateAudienceInput([
            'segment_name' => 'S', 'account_ids' => [], 'contact_ids' => [], 'tag_filter' => '',
        ]);

        $this->assertContains('Provide a tag filter or select at least one account or contact.', $errors);
    }
}
