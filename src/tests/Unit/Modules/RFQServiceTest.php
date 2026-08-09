<?php
declare(strict_types=1);

namespace Tests\Unit\Modules;

use App\Core\Database;
use App\Modules\RFQ\RFQRepository;
use App\Modules\RFQ\RFQService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\NeverConnectingPdo;

/**
 * The validation and stage rules only — none of them touch the repository.
 * Write operations belong to the integration suite.
 */
#[CoversClass(RFQService::class)]
final class RFQServiceTest extends TestCase
{
    private RFQService $service;

    protected function setUp(): void
    {
        // RFQRepository resolves Database::connection() in its constructor.
        Database::swap(new NeverConnectingPdo());
        $this->service = new RFQService(new RFQRepository());
    }

    protected function tearDown(): void
    {
        Database::swap(null);
    }

    // ── Stage rules ─────────────────────────────────────────────────────────

    #[Test]
    public function every_seeded_stage_is_valid(): void
    {
        foreach (RFQRepository::$stages as $stage) {
            $this->assertTrue($this->service->isValidStage($stage), "$stage should be valid");
        }
    }

    #[Test]
    public function stage_validation_rejects_unknown_and_mis_cased_values(): void
    {
        $this->assertFalse($this->service->isValidStage('Archived'));
        $this->assertFalse($this->service->isValidStage('won'));
        $this->assertFalse($this->service->isValidStage(''));
    }

    /**
     * A quote is what these stages are *about* — an RFQ cannot be Quoted, under
     * Negotiation, Won or Lost without one.
     */
    #[Test]
    public function the_post_quote_stages_require_a_quote(): void
    {
        foreach (RFQService::QUOTE_REQUIRED_STAGES as $stage) {
            $this->assertTrue($this->service->requiresQuote($stage), "$stage should require a quote");
        }

        $this->assertFalse($this->service->requiresQuote('New'));
        $this->assertFalse($this->service->requiresQuote('In Review'));
    }

    #[Test]
    public function the_quote_required_stages_are_all_real_stages(): void
    {
        foreach (RFQService::QUOTE_REQUIRED_STAGES as $stage) {
            $this->assertContains($stage, RFQRepository::$stages);
        }
    }

    // ── RFQ input ───────────────────────────────────────────────────────────

    #[Test]
    public function a_complete_rfq_passes_validation(): void
    {
        $errors = $this->service->validateRFQInput([
            'title'      => 'Catheter tubing quote',
            'account_id' => '4',
            'stage'      => 'New',
        ]);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function an_rfq_needs_a_title(): void
    {
        $errors = $this->service->validateRFQInput(['account_id' => '4', 'stage' => 'New', 'title' => '   ']);

        $this->assertContains('Title is required.', $errors);
    }

    /**
     * account_id is nullable in the schema (migration 010) precisely so an RFQ
     * can hang off a contact instead — but it cannot hang off neither.
     */
    #[Test]
    public function an_rfq_needs_either_an_account_or_a_contact(): void
    {
        $errors = $this->service->validateRFQInput(['title' => 'T', 'stage' => 'New']);
        $this->assertContains('At least one of Account or Contact is required.', $errors);

        $this->assertSame([], $this->service->validateRFQInput(['title' => 'T', 'stage' => 'New', 'contact_id' => '9']));
        $this->assertSame([], $this->service->validateRFQInput(['title' => 'T', 'stage' => 'New', 'account_id' => '9']));
    }

    #[Test]
    public function an_rfq_needs_a_stage_from_the_whitelist(): void
    {
        $errors = $this->service->validateRFQInput(['title' => 'T', 'account_id' => '4', 'stage' => 'Bogus']);

        $this->assertContains('Invalid stage selected.', $errors);
    }

    // ── Quote input ─────────────────────────────────────────────────────────

    #[Test]
    public function a_complete_quote_passes_validation(): void
    {
        $errors = $this->service->validateQuoteInput([
            'rfq_id'       => '12',
            'quote_amount' => '1500.00',
            'discount'     => '100',
        ]);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function a_quote_needs_a_real_rfq_id(): void
    {
        foreach (['', '0', '-3', 'abc'] as $bad) {
            $errors = $this->service->validateQuoteInput(['rfq_id' => $bad, 'quote_amount' => '10']);
            $this->assertContains('Invalid RFQ.', $errors, "rfq_id '$bad' should be rejected");
        }
    }

    #[Test]
    public function a_quote_amount_must_be_present_and_numeric(): void
    {
        $this->assertContains(
            'Quote amount is required and must be a number.',
            $this->service->validateQuoteInput(['rfq_id' => '1'])
        );
        $this->assertContains(
            'Quote amount is required and must be a number.',
            $this->service->validateQuoteInput(['rfq_id' => '1', 'quote_amount' => 'free'])
        );
    }

    /**
     * These mirror the chk_quotes_* CHECK constraints in schema.sql. Validating
     * here is what turns a 500 from a rejected INSERT into a form error.
     */
    #[Test]
    public function a_quote_amount_cannot_be_negative(): void
    {
        $this->assertContains(
            'Quote amount cannot be negative.',
            $this->service->validateQuoteInput(['rfq_id' => '1', 'quote_amount' => '-5'])
        );
    }

    #[Test]
    public function a_discount_cannot_be_negative_or_non_numeric(): void
    {
        $this->assertContains(
            'Discount cannot be negative.',
            $this->service->validateQuoteInput(['rfq_id' => '1', 'quote_amount' => '100', 'discount' => '-1'])
        );
        $this->assertContains(
            'Discount must be a number.',
            $this->service->validateQuoteInput(['rfq_id' => '1', 'quote_amount' => '100', 'discount' => 'half'])
        );
    }

    #[Test]
    public function a_discount_cannot_exceed_the_quote_amount(): void
    {
        $this->assertContains(
            'Discount cannot be greater than the quote amount.',
            $this->service->validateQuoteInput(['rfq_id' => '1', 'quote_amount' => '100', 'discount' => '101'])
        );
    }

    #[Test]
    public function a_discount_equal_to_the_quote_amount_is_allowed(): void
    {
        $this->assertSame(
            [],
            $this->service->validateQuoteInput(['rfq_id' => '1', 'quote_amount' => '100', 'discount' => '100'])
        );
    }

    #[Test]
    public function an_omitted_discount_is_treated_as_zero_not_an_error(): void
    {
        $this->assertSame([], $this->service->validateQuoteInput(['rfq_id' => '1', 'quote_amount' => '100']));
        $this->assertSame([], $this->service->validateQuoteInput(['rfq_id' => '1', 'quote_amount' => '100', 'discount' => '']));
    }

    #[Test]
    public function quote_validity_cannot_end_before_it_starts(): void
    {
        $errors = $this->service->validateQuoteInput([
            'rfq_id'              => '1',
            'quote_amount'        => '100',
            'validity_start_date' => '2026-08-10',
            'validity_end_date'   => '2026-08-01',
        ]);

        $this->assertContains('Validity end date cannot be before the start date.', $errors);
    }

    #[Test]
    public function a_validity_window_that_starts_and_ends_the_same_day_is_allowed(): void
    {
        $errors = $this->service->validateQuoteInput([
            'rfq_id'              => '1',
            'quote_amount'        => '100',
            'validity_start_date' => '2026-08-10',
            'validity_end_date'   => '2026-08-10',
        ]);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function one_sided_validity_dates_are_not_compared(): void
    {
        $this->assertSame([], $this->service->validateQuoteInput([
            'rfq_id' => '1', 'quote_amount' => '100', 'validity_start_date' => '2026-08-10', 'validity_end_date' => '',
        ]));
    }

    // ── Reservation input ───────────────────────────────────────────────────

    #[Test]
    public function a_complete_reservation_passes_validation(): void
    {
        $errors = $this->service->validateReservationInput([
            'rfq_id'            => '3',
            'product_id'        => '7',
            'quantity_reserved' => '5',
        ]);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function a_reservation_needs_a_product(): void
    {
        $errors = $this->service->validateReservationInput(['rfq_id' => '3', 'quantity_reserved' => '5']);

        $this->assertContains('Product is required.', $errors);
    }

    #[Test]
    #[DataProvider('nonPositiveQuantities')]
    public function a_reservation_quantity_must_be_at_least_one(string $quantity): void
    {
        $errors = $this->service->validateReservationInput([
            'rfq_id' => '3', 'product_id' => '7', 'quantity_reserved' => $quantity,
        ]);

        $this->assertContains('Quantity must be at least 1.', $errors);
    }

    /** @return array<string, array{string}> */
    public static function nonPositiveQuantities(): array
    {
        return [
            'zero'        => ['0'],
            'negative'    => ['-4'],
            'not a number'=> ['many'],
        ];
    }
}
