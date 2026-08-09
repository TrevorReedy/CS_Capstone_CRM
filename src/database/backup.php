<?php
/**
 * Database backup support (NFR5).
 *
 * Dumps the CRM database to a timestamped .sql file under database/backups/,
 * reading credentials from config/database.php (the same config the app uses).
 *
 * Usage:
 *   php database/backup.php                 # writes database/backups/<db>_<ts>.sql
 *   php database/backup.php /path/out.sql   # writes to a specific file
 *
 * Cron (daily 02:00, keep it simple — pair with logrotate/find for retention):
 *   0 2 * * *  php /path/to/src/database/backup.php >> /path/to/backup.log 2>&1
 *
 * The MySQL password is passed through a temporary --defaults-extra-file (mode
 * 0600) rather than on the command line, so it never appears in the process
 * list. The temp file is always removed, even on failure.
 */

declare(strict_types=1);

$config = require __DIR__ . '/../config/database.php';

$backupDir = __DIR__ . '/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Could not create backup directory: {$backupDir}\n");
    exit(1);
}

$outFile = $argv[1] ?? sprintf(
    '%s/%s_%s.sql',
    $backupDir,
    $config['database'],
    date('Ymd_His')
);

// Keep the password off the command line via a temp defaults file.
//
// DB_SSL_MODE: the MySQL 8 client verifies TLS by default, so a server with a
// self-signed certificate (the stock mysql:8.0 container, and some shared
// hosts) fails with "TLS/SSL error: self-signed certificate in certificate
// chain". Set DB_SSL_MODE=DISABLED for a local/container database, or
// REQUIRED/VERIFY_CA when the connection crosses a network you don't trust.
//
// The two clients spell this differently and each rejects the other's spelling
// outright ("unknown variable 'ssl-mode'"), so pick by what is actually
// installed: Debian/Ubuntu ship MariaDB's client as `default-mysql-client`,
// while cPanel and the mysql:8 image ship Oracle's.
$sslMode = strtoupper(getenv('DB_SSL_MODE') ?: '');
$sslLine = '';

if ($sslMode !== '') {
    $version   = shell_exec('mysqldump --version 2>&1') ?: '';
    $isMariaDb = stripos($version, 'mariadb') !== false;

    if ($isMariaDb) {
        // MariaDB understands ssl=0/1 only.
        $sslLine = $sslMode === 'DISABLED' ? "ssl=0\n" : "ssl=1\n";
    } else {
        $sslLine = "ssl-mode={$sslMode}\n";
    }
}

$defaultsFile = tempnam(sys_get_temp_dir(), 'mysqldump_');
chmod($defaultsFile, 0600);
file_put_contents($defaultsFile, sprintf(
    "[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n%s",
    $config['host'],
    $config['port'],
    $config['username'],
    $config['password'],
    $sslLine
));

// Redirect order matters. `… > dump.sql 2>&1` points stderr at the dump file,
// so a failure wrote its own error message into the backup and exec() captured
// nothing — every failure reported "Backup FAILED (exit N):" with no reason.
// `2>&1 > dump.sql` sends stderr to exec()'s pipe first, then stdout to the file.
$cmd = sprintf(
    'mysqldump --defaults-extra-file=%s --single-transaction --routines --triggers %s 2>&1 > %s',
    escapeshellarg($defaultsFile),
    escapeshellarg($config['database']),
    escapeshellarg($outFile)
);

$output = [];
$status = 0;
exec($cmd, $output, $status);
unlink($defaultsFile);

if ($status !== 0) {
    fwrite(STDERR, "Backup FAILED (exit {$status}):\n" . implode("\n", $output) . "\n");
    @unlink($outFile); // don't leave a truncated/empty dump behind
    exit($status);
}

printf("Backup written: %s (%s)\n", $outFile, number_format((int)@filesize($outFile)) . ' bytes');
