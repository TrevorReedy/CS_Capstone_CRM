<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Base class for tests that need a real MySQL 8 database.
 *
 * Why a real database rather than a fake: nearly all of this application's
 * logic *is* SQL — FULLTEXT search, CHECK constraints, foreign keys, the
 * inventory movements ledger. A stubbed PDO would assert that we called the
 * methods we called, which proves nothing about whether the query is right.
 *
 * Lifecycle:
 *
 *   once per process   the test database is dropped, recreated, and loaded from
 *                      database/schema.sql -> seed.sql -> indexes.sql, in that
 *                      order. indexes.sql is not optional: without it every
 *                      FULLTEXT search fails outright.
 *   per test           the test runs inside a transaction that is rolled back
 *                      in tearDown, so tests cannot see each other's writes and
 *                      the seed stays pristine.
 *
 * Two things to know before writing a test here:
 *
 *   - MySQL implicitly commits on DDL. A test that issues CREATE/ALTER/DROP
 *     breaks the rollback isolation for everything after it; set
 *     $useTransaction = false and clean up after yourself.
 *   - InventoryService::transactional() checks inTransaction() and joins an
 *     open transaction rather than nesting, so the outer rollback still holds.
 *     A test that needs to observe a real COMMIT must opt out too.
 *
 * Connection settings come from the environment (see phpunit.xml for the
 * defaults, and .github/workflows/ci.yml for what CI passes).
 */
abstract class IntegrationTestCase extends TestCase
{
    /** Every database this suite is allowed to drop ends with this. */
    protected const TEST_DB_SUFFIX = '_test';

    private static ?PDO $connection = null;
    private static bool $loaded     = false;

    protected PDO $db;

    /** Set false in a subclass that issues DDL or needs a real commit. */
    protected bool $useTransaction = true;

    protected function setUp(): void
    {
        $this->db = self::database();
        Database::swap($this->db);

        if ($this->useTransaction) {
            $this->db->beginTransaction();
        }

        $_SESSION = [];
        $_POST    = [];
        $_GET     = [];
    }

    protected function tearDown(): void
    {
        if ($this->useTransaction && $this->db->inTransaction()) {
            $this->db->rollBack();
        }

        Database::swap(null);
    }

    // ── Connection and schema ───────────────────────────────────────────────

    /**
     * Connection settings for the test database.
     *
     * The name is derived, never taken verbatim. buildSchema() opens with
     * DROP DATABASE, and the app container exports DB_NAME=typhon_cath_crm —
     * the development database — so reading DB_NAME directly meant the command
     * both the README and CONTRIBUTING.md tell people to run
     * (`docker compose exec app composer test`) would have destroyed their
     * local data. Appending the suffix makes every caller converge on the same
     * throwaway database: the container, a bare `vendor/bin/phpunit`, and CI
     * (which already passes typhon_cath_crm_test) all land on one name, and
     * there is no value of DB_NAME that aims the suite at real data.
     *
     * Credentials are separate for the same reason they are separate in CI:
     * creating a database needs privileges the application's own user does not
     * have, and should not be given. DB_TEST_USER/DB_TEST_PASS carry them;
     * docker-compose.yml supplies them to the app container.
     */
    protected static function config(): array
    {
        $name = getenv('DB_TEST_NAME') ?: (getenv('DB_NAME') ?: 'typhon_cath_crm');

        if (!str_ends_with($name, self::TEST_DB_SUFFIX)) {
            $name .= self::TEST_DB_SUFFIX;
        }

        return [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => getenv('DB_PORT') ?: '3306',
            'name' => $name,
            'user' => getenv('DB_TEST_USER') ?: (getenv('DB_USER') ?: 'root'),
            'pass' => getenv('DB_TEST_PASS') ?: (getenv('DB_PASS') ?: ''),
        ];
    }

    /**
     * A connection with no database selected — used to create the test schema
     * and, in the migration test, a throwaway database alongside it.
     */
    protected static function serverConnection(): PDO
    {
        $c = self::config();

        return new PDO(
            sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $c['host'], $c['port']),
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    /** The shared, schema-loaded connection every test in the run shares. */
    protected static function database(): PDO
    {
        if (self::$connection === null) {
            $c = self::config();

            if (!self::$loaded) {
                self::buildSchema($c['name']);
                self::$loaded = true;
            }

            self::$connection = new PDO(
                sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']),
                $c['user'],
                $c['pass'],
                [
                    // Mirrors app/Core/Database.php exactly. If these drift, the
                    // tests stop describing how the application actually talks
                    // to MySQL — the int-vs-string return types in particular.
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }

        return self::$connection;
    }

    /** Drop and rebuild the test database from the checked-in SQL files. */
    private static function buildSchema(string $database): void
    {
        // config() already guarantees the suffix. This re-checks it immediately
        // before the DROP because that is the statement that cannot be undone,
        // and a future caller reaching buildSchema() by another route should
        // fail loudly rather than take somebody's data with it.
        if (!str_ends_with($database, self::TEST_DB_SUFFIX)) {
            throw new RuntimeException(
                "Refusing to drop '{$database}': the integration suite only ever "
                . "rebuilds databases whose name ends in '" . self::TEST_DB_SUFFIX . "'."
            );
        }

        $server = self::serverConnection();
        $quoted = '`' . str_replace('`', '``', $database) . '`';

        $server->exec("DROP DATABASE IF EXISTS {$quoted}");
        $server->exec("CREATE DATABASE {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $server->exec("USE {$quoted}");

        // seed_dev_users.sql is not part of the application's normal setup — it
        // is explicitly development-only. It is loaded here because seed.sql
        // creates a single Super Admin, and Super Admin bypasses the permission
        // matrix: without one user per role there is no way to prove the matrix
        // restricts anything at all.
        foreach (['schema.sql', 'seed.sql', 'indexes.sql', 'seed_dev_users.sql'] as $file) {
            self::runSqlFile($server, __DIR__ . '/../database/' . $file);
        }
    }

    /**
     * Execute every statement in a .sql file.
     *
     * Statements are run one at a time rather than handing the whole file to
     * exec(): with multi-statement execution, a failure partway through is easy
     * to miss, and "the schema silently half-loaded" is the worst possible way
     * for an integration suite to fail. None of these files use DELIMITER or
     * define triggers/procedures, so splitting on `;` is sound — the guard
     * below keeps that assumption honest.
     */
    protected static function runSqlFile(PDO $pdo, string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException("SQL file not found: {$path}");
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Could not read: {$path}");
        }

        if (stripos($sql, 'DELIMITER') !== false) {
            throw new RuntimeException(
                basename($path) . ' uses DELIMITER; the naive splitter in ' . __METHOD__ . ' cannot handle it.'
            );
        }

        foreach (self::splitStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * Split a script into statements, ignoring semicolons inside string
     * literals and `--` comments (schema.sql's constraint comments contain
     * both apostrophes and semicolons).
     *
     * @return string[]
     */
    private static function splitStatements(string $sql): array
    {
        $statements = [];
        $current    = '';
        $quote      = null;      // active quote character, if inside a literal
        $length     = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $sql[++$i];          // escaped character
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            // Line comment: skip to end of line.
            if (($char === '-' && ($sql[$i + 1] ?? '') === '-') || $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $current .= "\n";
                continue;
            }

            // Block comment.
            if ($char === '/' && ($sql[$i + 1] ?? '') === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i   = $end === false ? $length : $end + 1;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === ';') {
                $statements[] = $current;
                $current      = '';
                continue;
            }

            $current .= $char;
        }

        $statements[] = $current;

        return array_values(array_filter(
            array_map('trim', $statements),
            static fn(string $s): bool => $s !== ''
        ));
    }

    // ── Assertions and helpers ──────────────────────────────────────────────

    protected function rowCount(string $table, string $where = '1', array $params = []): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** Sign a user in the way Auth::attempt() does, without needing a password. */
    protected function signInAs(string $email): void
    {
        $user = $this->fetchOne(
            'SELECT u.id, u.name, u.email, r.role_name
               FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.email = ?',
            [$email]
        );

        if ($user === null) {
            throw new RuntimeException("No seeded user with email {$email}");
        }

        $perms = $this->db->prepare(
            'SELECT rp.permission FROM role_permissions rp
               JOIN users u ON u.role_id = rp.role_id WHERE u.id = ?'
        );
        $perms->execute([$user['id']]);

        $_SESSION['user'] = [
            'id'          => (int) $user['id'],
            'name'        => $user['name'],
            'email'       => $user['email'],
            'role'        => $user['role_name'],
            'permissions' => $perms->fetchAll(PDO::FETCH_COLUMN),
        ];
        $_SESSION['permissions_synced_at'] = time();
    }
}
