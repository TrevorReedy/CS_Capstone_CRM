<?php
declare(strict_types=1);

namespace Tests\Support;

use PDO;

/**
 * A PDO that never opens a connection.
 *
 * Several repositories (Admin, Campaign) resolve Database::connection() in
 * their constructor, so merely instantiating a service that owns one would
 * otherwise try to reach MySQL — even for a test that only calls a pure
 * validation method. Installing this via Database::swap() satisfies the
 * constructor without a database.
 *
 * It is not a mock: any attempt to actually query through it will fail loudly,
 * which is the intent. A unit test that reaches a query has strayed into
 * integration territory and should move to tests/Integration.
 */
final class NeverConnectingPdo extends PDO
{
    public function __construct()
    {
        // Deliberately does not call parent::__construct() — there is nothing
        // to connect to, and nothing here should be trying.
    }
}
