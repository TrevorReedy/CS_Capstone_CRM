<?php
namespace App\Modules\Customer;

use App\Core\Database;
use App\Core\DataTable\ServerTable;
use PDO;

class CustomerRepository
{
    /**
     * Server-side DataTables source for the accounts list. Shared by the data
     * and export endpoints. Name search uses the ft_accounts_name FULLTEXT index;
     * industry/source are exact per-column select filters (index-friendly).
     */
    public static function listTable(): ServerTable
    {
        return new ServerTable(
            Database::connection(),
            'accounts',
            'id, account_name, email, phone, industry, source, tags',
            [
                ['data' => 'account_name', 'sql' => 'account_name', 'order' => true, 'search' => 'fulltext', 'ft' => 'account_name'],
                ['data' => 'email',        'sql' => 'email',        'order' => true, 'search' => 'like'],
                ['data' => 'phone',        'sql' => 'phone',        'order' => true, 'search' => 'like'],
                ['data' => 'industry',     'sql' => 'industry',     'order' => true, 'search' => 'exact'],
                ['data' => 'source',       'sql' => 'source',       'order' => true, 'search' => 'exact'],
                ['data' => 'tags',         'sql' => 'tags',         'order' => true, 'search' => 'like'],
            ],
            'account_name',
            'ASC'
        );
    }

    /** Distinct non-empty values of a whitelisted column, for a per-column filter <select>. */
    public function distinctValues(string $column): array
    {
        if (!in_array($column, ['industry', 'source'], true)) {
            return [];
        }
        $db   = Database::connection();
        $stmt = $db->query(
            "SELECT DISTINCT {$column} AS v FROM accounts
             WHERE {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} ASC"
        );
        return array_map(static fn($r) => $r['v'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function all(): array
    {
        $db = Database::connection();

        $stmt = $db->query("
            SELECT *
            FROM accounts
            ORDER BY account_name
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search(
        string $search = '',
        string $industry = '',
        string $source = '',
        int $limit = 25,
        int $offset = 0
    ): array {
        $db = Database::connection();

        [$where, $params] = $this->buildAccountWhere($search, $industry, $source);
        $limit  = max(1, $limit);   // guard the interpolated LIMIT/OFFSET
        $offset = max(0, $offset);

        $sql  = "SELECT * FROM accounts{$where} ORDER BY account_name LIMIT {$limit} OFFSET {$offset}";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Total accounts matching the same filters (for pagination).
    public function searchCount(string $search = '', string $industry = '', string $source = ''): int
    {
        $db = Database::connection();
        [$where, $params] = $this->buildAccountWhere($search, $industry, $source);
        $stmt = $db->prepare("SELECT COUNT(*) FROM accounts{$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** Total number of contacts across all accounts (dashboard Total Accounts card). */
    public function contactCount(): int
    {
        $db = Database::connection();
        return (int) $db->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
    }

    // Shared WHERE builder so search() and searchCount() always agree.
    private function buildAccountWhere(string $search, string $industry, string $source): array
    {
        $clauses = [];
        $params  = [];

        if ($search !== '') {
            $clauses[] = "account_name LIKE :search";
            $params['search'] = "%{$search}%";
        }
        if ($industry !== '') {
            $clauses[] = "industry LIKE :industry";
            $params['industry'] = "%{$industry}%";
        }
        if ($source !== '') {
            $clauses[] = "source LIKE :source";
            $params['source'] = "%{$source}%";
        }

        $where = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
        return [$where, $params];
    }

    public function create(array $data): void
    {
        $db = Database::connection();

        $stmt = $db->prepare("
            INSERT INTO accounts
            (
                account_name,
                email,
                phone,
                address,
                industry,
                source,
                tags
            )
            VALUES
            (
                :account_name,
                :email,
                :phone,
                :address,
                :industry,
                :source,
                :tags
            )
        ");

        $stmt->execute($data);
    }

    public function delete(int $id): void
    {
        $db = Database::connection();

        $stmt = $db->prepare("
            DELETE FROM accounts
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id
        ]);
    }

    // A contact must belong to an account (contacts.account_id is NOT NULL).
    public function createContact(array $data): void
    {
        $db = Database::connection();

        $stmt = $db->prepare("
            INSERT INTO contacts
            (
                account_id,
                first_name,
                last_name,
                email,
                phone,
                title
            )
            VALUES
            (
                :account_id,
                :first_name,
                :last_name,
                :email,
                :phone,
                :title
            )
        ");

        $stmt->execute($data);
    }

    public function recentInteractions(int $limit = 5): array
    {
        $db = Database::connection();

        $sql = "
            SELECT
                i.interaction_type,
                i.interaction_subject,
                a.account_name,
                i.interaction_date
            FROM interactions i
            JOIN accounts a
                ON a.id = i.account_id
            ORDER BY i.interaction_date DESC
            LIMIT ?
        ";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $db = Database::connection();

        $stmt = $db->prepare("
            SELECT *
            FROM accounts
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        return $account ?: null;
    }

}
