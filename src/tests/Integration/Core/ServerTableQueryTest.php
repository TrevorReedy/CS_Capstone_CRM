<?php
declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\DataTable\ServerTable;
use App\Modules\Customer\CustomerRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * ServerTable against real MySQL, using the real accounts list definition from
 * CustomerRepository::listTable().
 *
 * The unit tests prove the SQL is *built* safely; these prove it *runs* — which
 * is a different claim. A MATCH/AGAINST clause that is syntactically perfect
 * still fails with "Can't find FULLTEXT index matching the column list" if
 * indexes.sql was not applied, and that error only ever appears at runtime.
 */
#[CoversClass(ServerTable::class)]
final class ServerTableQueryTest extends IntegrationTestCase
{
    private ServerTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->table = CustomerRepository::listTable();
    }

    /** A DataTables request payload with the column set the accounts list uses. */
    private function request(array $overrides = []): array
    {
        return $overrides + [
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'search'  => ['value' => ''],
            'columns' => array_fill(0, 6, ['search' => ['value' => '']]),
        ];
    }

    #[Test]
    public function an_unfiltered_draw_returns_the_page_and_the_totals(): void
    {
        $response = $this->table->handle($this->request());

        $this->assertSame(1, $response['draw']);
        $this->assertSame($this->rowCount('accounts'), $response['recordsTotal']);
        $this->assertSame($response['recordsTotal'], $response['recordsFiltered'], 'nothing is filtered');
        $this->assertLessThanOrEqual(10, count($response['data']));
    }

    #[Test]
    public function paging_returns_different_rows_without_changing_the_totals(): void
    {
        $first  = $this->table->handle($this->request(['start' => 0, 'length' => 5]));
        $second = $this->table->handle($this->request(['start' => 5, 'length' => 5]));

        $this->assertSame($first['recordsTotal'], $second['recordsTotal']);
        $this->assertNotEquals(
            array_column($first['data'], 'id'),
            array_column($second['data'], 'id')
        );
    }

    #[Test]
    public function the_all_option_returns_every_row(): void
    {
        $response = $this->table->handle($this->request(['length' => -1]));

        $this->assertCount($response['recordsTotal'], $response['data']);
    }

    #[Test]
    public function ordering_ascending_and_descending_reverses_the_page(): void
    {
        $asc  = $this->table->handle($this->request(['order' => [['column' => 0, 'dir' => 'asc']], 'length' => -1]));
        $desc = $this->table->handle($this->request(['order' => [['column' => 0, 'dir' => 'desc']], 'length' => -1]));

        $this->assertSame(
            array_column($asc['data'], 'account_name'),
            array_reverse(array_column($desc['data'], 'account_name'))
        );
    }

    /**
     * The load-bearing runtime test: this query uses the ft_accounts_name
     * FULLTEXT index, so it fails outright if indexes.sql was skipped.
     */
    #[Test]
    public function a_fulltext_search_matches_and_narrows_the_result(): void
    {
        $account = $this->fetchOne("SELECT account_name FROM accounts WHERE account_name <> '' LIMIT 1");
        $this->assertNotNull($account);

        $word = strtok($account['account_name'], ' ');
        if (strlen($word) < 3) {
            $this->markTestSkipped('the first seeded account name has no word long enough for FULLTEXT');
        }

        $response = $this->table->handle($this->request(['search' => ['value' => $word]]));

        $this->assertGreaterThan(0, $response['recordsFiltered'], "search for '{$word}' should match something");
        $this->assertLessThanOrEqual($response['recordsTotal'], $response['recordsFiltered']);
    }

    #[Test]
    public function a_search_that_matches_nothing_returns_an_empty_page_not_an_error(): void
    {
        $response = $this->table->handle($this->request(['search' => ['value' => 'zzzznotanaccountzzzz']]));

        $this->assertSame(0, $response['recordsFiltered']);
        $this->assertSame([], $response['data']);
        $this->assertGreaterThan(0, $response['recordsTotal'], 'the unfiltered total is unaffected');
    }

    /** Short terms bypass FULLTEXT and use LIKE — they must still run. */
    #[Test]
    public function a_two_character_search_still_executes(): void
    {
        $response = $this->table->handle($this->request(['search' => ['value' => 'ab']]));

        $this->assertIsInt($response['recordsFiltered']);
    }

    #[Test]
    public function a_per_column_filter_matches_exactly(): void
    {
        $industry = $this->fetchOne("SELECT industry FROM accounts WHERE industry IS NOT NULL AND industry <> '' LIMIT 1");
        if ($industry === null) {
            $this->markTestSkipped('no seeded account carries an industry');
        }

        $columns = array_fill(0, 6, ['search' => ['value' => '']]);
        $columns[3] = ['search' => ['value' => $industry['industry']]];

        $response = $this->table->handle($this->request(['columns' => $columns, 'length' => -1]));

        $this->assertGreaterThan(0, $response['recordsFiltered']);
        foreach ($response['data'] as $row) {
            $this->assertSame($industry['industry'], $row['industry']);
        }
    }

    /**
     * The reason exportRows exists: a CSV/PDF export must contain exactly the
     * rows on screen, same filters and same window — not the whole table.
     */
    #[Test]
    public function export_rows_mirror_the_visible_page(): void
    {
        $request  = $this->request(['length' => 5, 'start' => 0, 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $onScreen = $this->table->handle($request);
        $exported = $this->table->exportRows($request);

        $this->assertSame(
            array_column($onScreen['data'], 'id'),
            array_column($exported, 'id')
        );
    }

    #[Test]
    public function a_row_formatter_shapes_the_data_without_touching_the_counts(): void
    {
        $response = $this->table->handle(
            $this->request(['length' => 3]),
            static fn(array $row): array => ['account_name' => strtoupper((string) $row['account_name'])]
        );

        $this->assertSame($this->rowCount('accounts'), $response['recordsTotal']);
        foreach ($response['data'] as $row) {
            $this->assertSame(array_keys($row), ['account_name']);
            $this->assertSame(strtoupper($row['account_name']), $row['account_name']);
        }
    }

    /**
     * The end-to-end counterpart to the unit test: a hostile payload must come
     * back as an empty result set, not an error and certainly not extra rows.
     */
    #[Test]
    public function an_injection_payload_is_treated_as_an_ordinary_search_string(): void
    {
        $response = $this->table->handle($this->request([
            'search' => ['value' => "' OR 1=1 -- "],
            'length' => -1,
        ]));

        $this->assertSame(0, $response['recordsFiltered'], 'the payload matched rows — it was not bound as a value');
        $this->assertSame($this->rowCount('accounts'), $response['recordsTotal']);
    }

    #[Test]
    public function an_out_of_range_order_column_does_not_break_the_query(): void
    {
        $response = $this->table->handle($this->request(['order' => [['column' => 99, 'dir' => 'desc']]]));

        $this->assertSame($this->rowCount('accounts'), $response['recordsTotal']);
    }
}
