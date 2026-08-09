<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\DataTable\ServerTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Support\NeverConnectingPdo;

/**
 * ServerTable turns a DataTables $_GET payload into SQL. Everything a user can
 * influence arrives here: the sort column index, the page length and offset,
 * and the search strings. The class documents its safety argument — developer
 * SQL fragments are constant, user input is only ever an index or a bound
 * value — and these tests are what hold that argument to account.
 *
 * The builders are private, so they are exercised through reflection. That is
 * the deliberate trade: the alternative is widening the public API purely for
 * testing, and their MySQL-specific MATCH/AGAINST output cannot be checked
 * against a portable in-memory database anyway. Full-query behaviour is covered
 * end to end in tests/Integration/Core/ServerTableQueryTest.php.
 */
#[CoversClass(ServerTable::class)]
final class ServerTableTest extends TestCase
{
    /** Mirrors the shape of the real account/RFQ list definitions. */
    private function table(string $defaultOrder = 'account_name', string $defaultDir = 'ASC'): ServerTable
    {
        return new ServerTable(
            new NeverConnectingPdo(),
            'accounts',
            'id, account_name, email, industry, notes',
            [
                0 => ['data' => 'account_name', 'sql' => 'account_name', 'order' => true,  'search' => 'fulltext', 'ft' => 'account_name'],
                1 => ['data' => 'email',        'sql' => 'email',        'order' => true,  'search' => 'like'],
                2 => ['data' => 'industry',     'sql' => 'industry',     'order' => false, 'search' => 'exact'],
                3 => ['data' => 'notes',        'sql' => 'notes',        'order' => true,  'search' => false],
            ],
            $defaultOrder,
            $defaultDir
        );
    }

    /** @return array{0:string,1:array<string,string>} */
    private function where(ServerTable $t, array $req): array
    {
        $m = new ReflectionMethod($t, 'buildWhere');
        return $m->invoke($t, $req);
    }

    private function order(ServerTable $t, array $req): string
    {
        return (new ReflectionMethod($t, 'buildOrder'))->invoke($t, $req);
    }

    private function limit(ServerTable $t, array $req, int $default = -1): string
    {
        return (new ReflectionMethod($t, 'buildLimit'))->invoke($t, $req, $default);
    }

    // ── ORDER BY ────────────────────────────────────────────────────────────

    #[Test]
    public function orders_by_the_requested_column(): void
    {
        $sql = $this->order($this->table(), ['order' => [['column' => 1, 'dir' => 'desc']]]);

        $this->assertSame(' ORDER BY email DESC', $sql);
    }

    #[Test]
    public function order_direction_accepts_only_asc_or_desc(): void
    {
        $t = $this->table();

        $this->assertSame(' ORDER BY email ASC', $this->order($t, ['order' => [['column' => 1, 'dir' => 'asc']]]));
        $this->assertSame(' ORDER BY email DESC', $this->order($t, ['order' => [['column' => 1, 'dir' => 'DeSc']]]));
        // Anything that is not DESC falls back to ASC rather than being echoed.
        $this->assertSame(' ORDER BY email ASC', $this->order($t, ['order' => [['column' => 1, 'dir' => 'ASC; DROP TABLE accounts']]]));
    }

    /**
     * The bounds check is the whole defence for ORDER BY: the column index
     * selects from a developer-defined whitelist, so an out-of-range index must
     * fall back rather than index into nothing.
     */
    #[Test]
    public function an_out_of_range_column_index_falls_back_to_the_default_order(): void
    {
        $t = $this->table();

        $this->assertSame(' ORDER BY account_name ASC', $this->order($t, ['order' => [['column' => 99, 'dir' => 'asc']]]));
        $this->assertSame(' ORDER BY account_name ASC', $this->order($t, ['order' => [['column' => -1, 'dir' => 'asc']]]));
    }

    #[Test]
    public function a_non_orderable_column_falls_back_to_the_default_order(): void
    {
        $sql = $this->order($this->table(), ['order' => [['column' => 2, 'dir' => 'desc']]]);

        $this->assertSame(' ORDER BY account_name ASC', $sql);
    }

    #[Test]
    public function a_non_numeric_column_index_cannot_reach_the_sql(): void
    {
        $sql = $this->order($this->table(), ['order' => [['column' => 'email; DROP TABLE accounts', 'dir' => 'asc']]]);

        // (int) cast turns it into 0, which is a legitimate whitelisted column.
        $this->assertSame(' ORDER BY account_name ASC', $sql);
        $this->assertStringNotContainsString('DROP', $sql);
    }

    #[Test]
    public function omitting_order_uses_the_configured_default(): void
    {
        $this->assertSame(' ORDER BY account_name ASC', $this->order($this->table(), []));
        $this->assertSame(' ORDER BY id DESC', $this->order($this->table('id', 'DESC'), []));
    }

    #[Test]
    public function a_table_with_no_default_order_emits_no_order_clause(): void
    {
        $this->assertSame('', $this->order($this->table(''), []));
    }

    // ── LIMIT / OFFSET ──────────────────────────────────────────────────────

    #[Test]
    public function windows_the_result_set_to_the_requested_page(): void
    {
        $this->assertSame(' LIMIT 25 OFFSET 50', $this->limit($this->table(), ['length' => 25, 'start' => 50]));
    }

    /** DataTables sends length = -1 for the "All" option. */
    #[Test]
    public function length_of_minus_one_means_no_limit_at_all(): void
    {
        $this->assertSame('', $this->limit($this->table(), ['length' => -1, 'start' => 0]));
    }

    #[Test]
    public function the_default_applies_when_the_request_carries_no_length(): void
    {
        $this->assertSame('', $this->limit($this->table(), [], -1));
        $this->assertSame(' LIMIT 25 OFFSET 0', $this->limit($this->table(), [], 25));
    }

    #[Test]
    public function a_zero_or_negative_page_length_is_clamped_to_one_row(): void
    {
        $this->assertSame(' LIMIT 1 OFFSET 0', $this->limit($this->table(), ['length' => 0]));
        $this->assertSame(' LIMIT 1 OFFSET 0', $this->limit($this->table(), ['length' => -5]));
    }

    #[Test]
    public function a_negative_offset_is_clamped_to_zero(): void
    {
        $this->assertSame(' LIMIT 10 OFFSET 0', $this->limit($this->table(), ['length' => 10, 'start' => -20]));
    }

    #[Test]
    public function limit_and_offset_are_integers_so_nothing_user_supplied_survives(): void
    {
        $sql = $this->limit($this->table(), [
            'length' => '25; DROP TABLE accounts',
            'start'  => '0 UNION SELECT password_hash FROM users',
        ]);

        $this->assertSame(' LIMIT 25 OFFSET 0', $sql);
        $this->assertStringNotContainsString('DROP', $sql);
        $this->assertStringNotContainsString('UNION', $sql);
    }

    // ── WHERE ───────────────────────────────────────────────────────────────

    #[Test]
    public function no_search_produces_no_where_clause(): void
    {
        [$where, $params] = $this->where($this->table(), []);

        $this->assertSame('', $where);
        $this->assertSame([], $params);
    }

    #[Test]
    public function a_whitespace_only_search_is_ignored(): void
    {
        [$where, $params] = $this->where($this->table(), ['search' => ['value' => '   ']]);

        $this->assertSame('', $where);
        $this->assertSame([], $params);
    }

    /**
     * Global search ORs across every searchable column, uses FULLTEXT where the
     * column declares it, and skips columns marked search => false.
     */
    #[Test]
    public function global_search_spans_every_searchable_column(): void
    {
        [$where, $params] = $this->where($this->table(), ['search' => ['value' => 'acme']]);

        $this->assertStringContainsString('MATCH(account_name) AGAINST (:p0 IN BOOLEAN MODE)', $where);
        $this->assertStringContainsString('email LIKE :p1', $where);
        $this->assertStringNotContainsString('notes', $where, 'search => false must be skipped');
        $this->assertStringStartsWith(' WHERE (', $where);

        $this->assertSame('+acme*', $params['p0']);
        $this->assertSame('%acme%', $params['p1']);
    }

    /**
     * Global search deliberately treats an `exact` column as LIKE, so typing a
     * word into the search box still matches an industry. Exact matching is for
     * the per-column select filters, which is the next test.
     */
    #[Test]
    public function global_search_treats_exact_columns_as_like(): void
    {
        [$where, $params] = $this->where($this->table(), ['search' => ['value' => 'acme']]);

        $this->assertStringContainsString('industry LIKE :p2', $where);
        $this->assertSame('%acme%', $params['p2']);
    }

    #[Test]
    public function a_per_column_filter_on_an_exact_column_matches_exactly(): void
    {
        [$where, $params] = $this->where($this->table(), [
            'columns' => [2 => ['search' => ['value' => 'Manufacturing']]],
        ]);

        $this->assertSame(' WHERE industry = :p0', $where);
        $this->assertSame('Manufacturing', $params['p0']);
    }

    #[Test]
    public function per_column_filters_are_anded_together_and_with_the_global_search(): void
    {
        [$where] = $this->where($this->table(), [
            'search'  => ['value' => 'acme'],
            'columns' => [
                1 => ['search' => ['value' => 'example.test']],
                2 => ['search' => ['value' => 'Manufacturing']],
            ],
        ]);

        $this->assertSame(2, substr_count($where, ' AND '));
        $this->assertStringContainsString('industry = :', $where);
    }

    /**
     * InnoDB's ft_min_token_size is 3: a shorter term matches nothing in a
     * FULLTEXT index, so the column must fall back to LIKE or two-letter
     * searches would silently return no rows.
     */
    #[Test]
    public function a_search_term_shorter_than_the_fulltext_minimum_falls_back_to_like(): void
    {
        [$where, $params] = $this->where($this->table(), ['search' => ['value' => 'ab']]);

        $this->assertStringNotContainsString('MATCH', $where);
        $this->assertStringContainsString('account_name LIKE :p0', $where);
        $this->assertSame('%ab%', $params['p0']);
    }

    #[Test]
    public function a_term_at_exactly_the_fulltext_minimum_uses_fulltext(): void
    {
        [$where] = $this->where($this->table(), ['search' => ['value' => 'abc']]);

        $this->assertStringContainsString('MATCH(account_name)', $where);
    }

    /**
     * Boolean-mode operators in user input would otherwise change the meaning
     * of the query (or make it a syntax error). Each word becomes a required
     * prefix term instead.
     */
    #[Test]
    public function boolean_mode_operators_are_stripped_from_the_search_term(): void
    {
        [, $params] = $this->where($this->table(), ['search' => ['value' => 'acme* -corp +(ltd)']]);

        $this->assertSame('+acme* +corp* +ltd*', $params['p0']);
    }

    #[Test]
    public function multi_word_search_requires_every_word(): void
    {
        [, $params] = $this->where($this->table(), ['search' => ['value' => 'acme industries']]);

        $this->assertSame('+acme* +industries*', $params['p0']);
    }

    /**
     * A term made entirely of boolean operators reduces to nothing, which must
     * fall through to LIKE rather than emit AGAINST ('').
     */
    #[Test]
    public function a_search_of_only_operators_falls_back_to_like(): void
    {
        [$where, $params] = $this->where($this->table(), ['search' => ['value' => '+++***']]);

        $this->assertStringNotContainsString('MATCH', $where);
        $this->assertSame('%+++***%', $params['p0']);
    }

    /**
     * The load-bearing assertion for the whole class: whatever the user typed,
     * it appears only in the bound parameters, never in the SQL string.
     */
    #[Test]
    public function user_input_never_reaches_the_sql_string(): void
    {
        $payload = "' OR 1=1 -- UNION SELECT password_hash FROM users";

        [$where, $params] = $this->where($this->table(), [
            'search'  => ['value' => $payload],
            'columns' => [2 => ['search' => ['value' => $payload]]],
        ]);

        $this->assertStringNotContainsString('OR 1=1', $where);
        $this->assertStringNotContainsString('password_hash', $where);
        $this->assertStringNotContainsString("'", $where);
        $this->assertContains($payload, $params, 'the raw value belongs in the bound params');
    }

    #[Test]
    public function every_placeholder_in_the_clause_has_exactly_one_bound_value(): void
    {
        [$where, $params] = $this->where($this->table(), [
            'search'  => ['value' => 'acme'],
            'columns' => [2 => ['search' => ['value' => 'Manufacturing']]],
        ]);

        preg_match_all('/:(p\d+)/', $where, $matches);

        $this->assertSame(count($params), count($matches[1]));
        $this->assertSame(count($matches[1]), count(array_unique($matches[1])), 'placeholders must be unique');
        foreach ($matches[1] as $name) {
            $this->assertArrayHasKey($name, $params);
        }
    }
}
