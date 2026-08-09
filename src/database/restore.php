<?php
/**
 * Database restore — the other half of backup.php (NFR5).
 *
 * A backup nobody has restored is a hope, not a backup. This script exists so
 * the restore path is exercised rather than assumed.
 *
 * Usage:
 *   php database/restore.php <dump.sql>                 # restore into the configured DB
 *   php database/restore.php <dump.sql> --into=scratch  # restore into another DB first
 *
 * The --into form is the one to use for a drill: restore last night's dump into
 * an empty scratch database, confirm the row counts, drop it. That verifies the
 * dump without touching live data.
 *
 * Restoring over the configured database is destructive and therefore requires
 * typing the database name to confirm.
 */

declare(strict_types=1);

$config = require __DIR__ . '/../config/database.php';

$args = array_slice($argv, 1);
$file = null;
$into = null;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--into=')) {
        $into = substr($arg, 7);
        continue;
    }
    $file ??= $arg;
}

if ($file === null || !is_file($file)) {
    fwrite(STDERR, "Usage: php database/restore.php <dump.sql> [--into=other_db]\n");
    exit(1);
}

$target = $into ?? $config['database'];

// Restoring over the live database wipes whatever is currently there.
if ($into === null) {
    fwrite(STDOUT, "About to restore '{$file}' over database '{$target}'.\n");
    fwrite(STDOUT, "This REPLACES current data. Type the database name to continue: ");
    $typed = trim((string)fgets(STDIN));
    if ($typed !== $target) {
        fwrite(STDERR, "Aborted — '{$typed}' does not match '{$target}'.\n");
        exit(1);
    }
}

// Same temp-defaults-file trick as backup.php: keeps the password out of the
// process list, where `ps` would otherwise show it to every user on the box.
// DB_SSL_MODE: see the note in backup.php — MySQL 8 clients verify TLS by
// default and reject the self-signed certificate a stock container presents,
// and MariaDB's client spells the option differently.
$sslMode = strtoupper(getenv('DB_SSL_MODE') ?: '');
$sslLine = '';

if ($sslMode !== '') {
    $isMariaDb = stripos(shell_exec('mysql --version 2>&1') ?: '', 'mariadb') !== false;
    $sslLine   = $isMariaDb
        ? ($sslMode === 'DISABLED' ? "ssl=0\n" : "ssl=1\n")
        : "ssl-mode={$sslMode}\n";
}

$defaultsFile = tempnam(sys_get_temp_dir(), 'mysqlrestore_');
chmod($defaultsFile, 0600);
file_put_contents($defaultsFile, sprintf(
    "[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n%s",
    $config['host'],
    $config['port'],
    $config['username'],
    $config['password'],
    $sslLine
));

$run = static function (string $cmd) use ($defaultsFile): array {
    $output = [];
    $status = 0;
    exec($cmd . ' 2>&1', $output, $status);
    return [$status, $output];
};

// Create the target if it does not exist (the scratch-database case).
[$status, $output] = $run(sprintf(
    'mysql --defaults-extra-file=%s -e %s',
    escapeshellarg($defaultsFile),
    escapeshellarg("CREATE DATABASE IF NOT EXISTS `{$target}`")
));

if ($status !== 0) {
    unlink($defaultsFile);
    fwrite(STDERR, "Could not create/select '{$target}':\n" . implode("\n", $output) . "\n");

    // The app's own DB user deliberately has no CREATE DATABASE grant, so the
    // scratch-database drill needs a more privileged account. Say so, rather
    // than leaving someone to decode "ERROR 1044".
    if (str_contains(implode(' ', $output), 'Access denied')) {
        fwrite(STDERR,
            "\nThe application database user cannot create databases — that is intentional.\n"
            . "For a restore drill, have an admin create the scratch database first:\n"
            . "    mysql -u root -p -e \"CREATE DATABASE {$target}\"\n"
            . "then re-run this command.\n");
    }

    exit($status);
}

[$status, $output] = $run(sprintf(
    'mysql --defaults-extra-file=%s %s < %s',
    escapeshellarg($defaultsFile),
    escapeshellarg($target),
    escapeshellarg($file)
));

unlink($defaultsFile);

if ($status !== 0) {
    fwrite(STDERR, "Restore FAILED (exit {$status}):\n" . implode("\n", $output) . "\n");
    exit($status);
}

printf("Restored %s into '%s'.\n", $file, $target);
printf("Verify with:  mysql %s -e 'SELECT COUNT(*) FROM accounts; SELECT COUNT(*) FROM rfqs;'\n", $target);
