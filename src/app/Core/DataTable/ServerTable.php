<?php
namespace App\Core\DataTable;

use PDO;

/**
 * Generic server-side processor for DataTables (client `serverSide: true`).
 *
 * One instance describes one list: the FROM/JOIN, the SELECT expressions, and a
 * per-column config (which SQL column it maps to, whether it is orderable /
 * searchable, and how it is searched). It translates a DataTables request
 * ($_GET) into a windowed `WHERE … ORDER BY … LIMIT/OFFSET` query plus the two
 * counts DataTables needs, and returns the JSON-ready response array.
 *
 * Safety: every SQL fragment (baseFrom, selectSql, each column's `sql`/`ft`)
 * is developer-supplied and constant. User input only (a) chooses an order
 * column by *index* (bounds-checked against the whitelist) and (b) supplies
 * search *values*, which are always bound parameters. There is no user string
 * concatenated into SQL.
 *
 * Column config entry:
 *   ['data' => 'title', 'sql' => 'r.title',
 *    'order' => true, 'search' => 'like'|'exact'|'fulltext'|false, 'ft' => 'r.title']
 * The `data` key must match both the SELECT alias and the client column `data`.
 * `ft` (fulltext target) is required only when `search === 'fulltext'`.
 */
final class ServerTable
{
    /** InnoDB default ft_min_token_size — shorter terms fall back to LIKE. */
    private const FT_MIN_TOKEN = 3;

    public function __construct(
        private PDO    $db,
        private string $baseFrom,          // e.g. "rfqs r LEFT JOIN accounts a ON a.id = r.account_id"
        private string $selectSql,         // e.g. "r.id, r.title, a.account_name AS account_name, ..."
        private array  $columns,           // ordered list of column config entries
        private string $defaultOrderSql = '',
        private string $defaultOrderDir = 'DESC',
    ) {}

    /**
     * Handle a DataTables draw request.
     *
     * @param array          $req          The request params (typically $_GET).
     * @param callable|null  $rowFormatter fn(array $row): array — maps a raw DB
     *                                      row to the display cells DataTables renders.
     * @return array{draw:int,recordsTotal:int,recordsFiltered:int,data:array}
     */
    public function handle(array $req, ?callable $rowFormatter = null): array
    {
        $draw = (int)($req['draw'] ?? 0);

        [$where, $params] = $this->buildWhere($req);
        $orderSql = $this->buildOrder($req);

        $recordsTotal = (int)$this->db->query("SELECT COUNT(*) FROM {$this->baseFrom}")->fetchColumn();

        // Skip the second count entirely when nothing is filtered (common case).
        if ($where === '') {
            $recordsFiltered = $recordsTotal;
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->baseFrom}{$where}");
            $stmt->execute($params);
            $recordsFiltered = (int)$stmt->fetchColumn();
        }

        $limitSql = $this->buildLimit($req, 25);

        $stmt = $this->db->prepare(
            "SELECT {$this->selectSql} FROM {$this->baseFrom}{$where}{$orderSql}{$limitSql}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data = $rowFormatter ? array_map($rowFormatter, $rows) : $rows;

        return [
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => array_values($data),
        ];
    }

    /**
     * The rows behind the current view — same filters, sort AND page window as
     * the table the user is looking at. Used by the export endpoints, so a
     * CSV/XML/PDF contains exactly the rows on screen: the selected page length
     * (25 / 50 / 100) starting at the current page, or every filtered row when
     * the length menu is set to "All" (DataTables sends length = -1).
     * Returns raw DB rows (export formats them itself).
     */
    public function exportRows(array $req): array
    {
        [$where, $params] = $this->buildWhere($req);
        $orderSql = $this->buildOrder($req);
        $limitSql = $this->buildLimit($req);
        $stmt = $this->db->prepare(
            "SELECT {$this->selectSql} FROM {$this->baseFrom}{$where}{$orderSql}{$limitSql}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── internals ───────────────────────────────────────────────────────────

    private function buildWhere(array $req): array
    {
        $clauses = [];
        $params  = [];
        $i       = 0; // unique bound-param counter

        // Global search: OR across every searchable column.
        $global = trim((string)($req['search']['value'] ?? ''));
        if ($global !== '') {
            $or = [];
            foreach ($this->columns as $col) {
                $frag = $this->searchFragment($col, $global, $params, $i);
                if ($frag !== null) {
                    $or[] = $frag;
                }
            }
            if ($or) {
                $clauses[] = '(' . implode(' OR ', $or) . ')';
            }
        }

        // Per-column search: AND together each non-empty column filter.
        foreach ($this->columns as $idx => $col) {
            $val = trim((string)($req['columns'][$idx]['search']['value'] ?? ''));
            if ($val === '') {
                continue;
            }
            $frag = $this->searchFragment($col, $val, $params, $i, true);
            if ($frag !== null) {
                $clauses[] = $frag;
            }
        }

        $where = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }

    /**
     * Build one search predicate for a column, binding its value into $params.
     * Returns null when the column is not searchable. `$exactAllowed` enables the
     * `exact` mode (per-column select filters); global search treats exact
     * columns as LIKE so a typed word still matches.
     */
    private function searchFragment(array $col, string $value, array &$params, int &$i, bool $exactAllowed = false): ?string
    {
        $mode = $col['search'] ?? false;
        if ($mode === false) {
            return null;
        }

        if ($mode === 'exact' && $exactAllowed) {
            $ph = "p{$i}"; $i++;
            $params[$ph] = $value;
            return "{$col['sql']} = :{$ph}";
        }

        if ($mode === 'fulltext' && mb_strlen($value) >= self::FT_MIN_TOKEN) {
            $term = $this->booleanTerm($value);
            if ($term !== '') {
                $ph = "p{$i}"; $i++;
                $params[$ph] = $term;
                return "MATCH({$col['ft']}) AGAINST (:{$ph} IN BOOLEAN MODE)";
            }
        }

        // Default / fallback: substring LIKE.
        $ph = "p{$i}"; $i++;
        $params[$ph] = '%' . $value . '%';
        return "{$col['sql']} LIKE :{$ph}";
    }

    /**
     * Window the result set to the requested page. `length === -1` is
     * DataTables' "All" and yields no LIMIT at all. $default applies when the
     * request carries no length (e.g. an export URL opened by hand).
     * Both values are cast to int, so nothing user-supplied reaches the SQL.
     */
    private function buildLimit(array $req, int $default = -1): string
    {
        $length = isset($req['length']) ? (int)$req['length'] : $default;
        if ($length === -1) {
            return '';
        }
        $length = max(1, $length);
        $start  = max(0, (int)($req['start'] ?? 0));
        return " LIMIT {$length} OFFSET {$start}";
    }

    private function buildOrder(array $req): string
    {
        $order = $req['order'][0] ?? null;
        if (is_array($order) && isset($order['column'])) {
            $col = $this->columns[(int)$order['column']] ?? null;
            if ($col && ($col['order'] ?? false)) {
                $dir = strtoupper((string)($order['dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
                return " ORDER BY {$col['sql']} {$dir}";
            }
        }
        return $this->defaultOrderSql !== ''
            ? " ORDER BY {$this->defaultOrderSql} {$this->defaultOrderDir}"
            : '';
    }

    /** Turn a raw query into a safe BOOLEAN-mode prefix search: "foo bar" -> "+foo* +bar*". */
    private function booleanTerm(string $s): string
    {
        $s      = preg_replace('/[+\-><()~*"@]+/', ' ', $s) ?? '';
        $tokens = preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(' ', array_map(static fn($t) => '+' . $t . '*', $tokens));
    }
}
