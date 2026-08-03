<?php
/**
 * db_setup.php — Database setup & migration console for TyphonCath CRM.
 *
 * A single-file web interface to inspect and update the database:
 *   - View every table, its columns, row counts, and live drift vs schema.sql
 *   - Auto-discovers migration files from ./migrations (no registry to keep in sync)
 *   - Tracks applied migrations in a `schema_migrations` table (any migration type)
 *   - Run all pending migrations, run/re-run one, or baseline (mark applied w/o running)
 *   - Run schema.sql (fresh install) and seed.sql (demo data) behind typed confirmation
 *   - Reset the demo admin password
 *
 * Credentials come from ../config/database.php (same as the app) — nothing hardcoded.
 *
 * SECURITY: this file can modify/destroy the database. On a live server, set the
 * DB_SETUP_KEY env var to require a key, and delete this file when you're done.
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
session_start();

$BASE    = __DIR__;
$DOCROOT = $_SERVER['DOCUMENT_ROOT'] ?? '';
$DOCUP   = $DOCROOT !== '' ? dirname($DOCROOT) : '';  // one level above the web root

// Resolve resources by probing likely locations, so it works whether this file
// sits in database/ (repo layout) or was copied into public/ (web root) on the server.
$migrationsDir = locate_path([
    $BASE . '/migrations', $BASE . '/database/migrations', $BASE . '/../database/migrations',
    $BASE . '/../migrations', $DOCROOT . '/migrations', $DOCROOT . '/database/migrations',
    $DOCUP . '/database/migrations',
], $BASE . '/migrations');
$schemaFile = locate_path([
    $BASE . '/schema.sql', $BASE . '/database/schema.sql', $BASE . '/../database/schema.sql',
    $DOCROOT . '/database/schema.sql', $DOCUP . '/database/schema.sql',
], $BASE . '/schema.sql');
$seedFile = locate_path([
    $BASE . '/seed.sql', $BASE . '/database/seed.sql', $BASE . '/../database/seed.sql',
    $DOCROOT . '/database/seed.sql', $DOCUP . '/database/seed.sql',
], $BASE . '/seed.sql');
$configFile = locate_path([
    $BASE . '/../config/database.php', $BASE . '/config/database.php',
    $BASE . '/../secret/database.php', $BASE . '/../../secret/database.php',
    $DOCROOT . '/config/database.php', $DOCUP . '/config/database.php', $DOCUP . '/secret/database.php',
], $BASE . '/../config/database.php');

// Manual escape hatch: ?migrations=/absolute/path
if (!empty($_GET['migrations']) && is_dir($_GET['migrations'])) $migrationsDir = $_GET['migrations'];

// ── Optional access key ──────────────────────────────────────────────────────
$requiredKey = getenv('DB_SETUP_KEY') ?: '';
if ($requiredKey !== '') {
    if (isset($_POST['setup_key']) && hash_equals($requiredKey, (string)$_POST['setup_key'])) {
        $_SESSION['db_setup_auth'] = true;
    }
    if (empty($_SESSION['db_setup_auth'])) {
        render_key_form(!empty($_POST));
        exit;
    }
}

// ── Connect (reuse the app's config) ─────────────────────────────────────────
$cfg = is_file($configFile) ? require $configFile : [
    'host' => getenv('DB_HOST') ?: 'localhost', 'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: '', 'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
];
$dbname       = (string)($cfg['database'] ?? '');
$pdo          = null;
$connectError = '';
try {
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $cfg['host'], $cfg['port'], $cfg['database']);
    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
        // Buffer each result set on execute so mixing exec() with prepared
        // information_schema queries never leaves an "unbuffered query active"
        // (MySQL error 2014) — the failure mode on Bluehost's MySQL build.
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
} catch (Throwable $e) {
    $connectError = $e->getMessage();
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Return the first candidate path that exists (file or dir), else $fallback. */
function locate_path(array $candidates, string $fallback): string {
    foreach ($candidates as $c) {
        if ($c !== '' && (is_dir($c) || is_file($c))) return $c;
    }
    return $fallback;
}

/** Split a SQL blob into individual statements, respecting quotes and comments. */
function split_sql(string $sql): array {
    $stmts = []; $buf = ''; $n = strlen($sql);
    $inS = $inD = $lineC = $blockC = false;
    for ($i = 0; $i < $n; $i++) {
        $c = $sql[$i]; $nx = $i + 1 < $n ? $sql[$i + 1] : '';
        if ($lineC)  { if ($c === "\n") $lineC = false; continue; }
        if ($blockC) { if ($c === '*' && $nx === '/') { $blockC = false; $i++; } continue; }
        if ($inS)    { $buf .= $c; if ($c === '\\') { $buf .= $nx; $i++; } elseif ($c === "'") $inS = false; continue; }
        if ($inD)    { $buf .= $c; if ($c === '\\') { $buf .= $nx; $i++; } elseif ($c === '"') $inD = false; continue; }
        if ($c === '-' && $nx === '-') { $lineC = true; $i++; continue; }
        if ($c === '#')                { $lineC = true; continue; }
        if ($c === '/' && $nx === '*') { $blockC = true; $i++; continue; }
        if ($c === "'") { $inS = true; $buf .= $c; continue; }
        if ($c === '"') { $inD = true; $buf .= $c; continue; }
        if ($c === ';') { $t = trim($buf); if ($t !== '') $stmts[] = $t; $buf = ''; continue; }
        $buf .= $c;
    }
    $t = trim($buf); if ($t !== '') $stmts[] = $t;
    // Drop connection-scope statements — we're already on the target DB.
    return array_values(array_filter($stmts, fn($s) =>
        !preg_match('/^\s*(USE|CREATE\s+DATABASE)\b/i', $s)));
}

function is_stub_file(string $path): bool {
    if (!is_file($path)) return true;
    $sql = preg_replace('/--[^\n]*|\/\*.*?\*\//s', '', file_get_contents($path));
    return trim((string)$sql) === '';
}

/** MySQL error codes that mean "this change is already present". */
function is_benign_code(int $code): bool {
    return in_array($code, [1050, 1060, 1061, 1062, 1091], true);
    // 1050 table exists · 1060 dup column · 1061 dup key · 1062 dup entry (INSERT
    // already done) · 1091 can't drop, doesn't exist.
    // NOTE: 1054 (unknown column) is intentionally NOT benign — it usually means a
    // real dependency is missing (e.g. an ADD ... AFTER <col> where <col> doesn't
    // exist yet), which must surface as an error, not be silently marked applied.
}

function table_exists(PDO $pdo, string $db, string $t): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=? LIMIT 1");
    $s->execute([$db, $t]); return (bool)$s->fetchColumn();
}
function all_tables(PDO $pdo, string $db): array {
    $s = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME");
    $s->execute([$db]); return $s->fetchAll(PDO::FETCH_COLUMN);
}
function table_columns(PDO $pdo, string $db, string $t): array {
    $s = $pdo->prepare("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY
        FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");
    $s->execute([$db, $t]); return $s->fetchAll();
}
function row_count(PDO $pdo, string $t): int {
    try { return (int)$pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '', $t) . "`")->fetchColumn(); }
    catch (Throwable $e) { return -1; }
}

/** Parse schema.sql -> [table => [colname,...]] for drift detection. */
function parse_schema(string $file): array {
    if (!is_file($file)) return [];
    $sql = file_get_contents($file); $out = [];
    if (preg_match_all('/CREATE TABLE\s+`?(\w+)`?\s*\((.*?)\n\);/s', $sql, $m, PREG_SET_ORDER)) {
        foreach ($m as $blk) {
            $cols = [];
            foreach (explode("\n", $blk[2]) as $line) {
                $line = trim(rtrim(trim($line), ','));
                if ($line === '' || str_starts_with($line, '--')) continue;
                if (preg_match('/^(PRIMARY|FOREIGN|UNIQUE|KEY|CONSTRAINT|INDEX|CHECK)\b/i', $line)) continue;
                $cols[] = trim(explode(' ', $line)[0], '`');
            }
            $out[$blk[1]] = $cols;
        }
    }
    return $out;
}

function ensure_migrations_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        filename VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        note VARCHAR(255) NULL
    )");
}
function applied_migrations(PDO $pdo): array {
    try { return $pdo->query("SELECT filename, applied_at, note FROM schema_migrations")->fetchAll(PDO::FETCH_UNIQUE); }
    catch (Throwable $e) { return []; }
}
function discover_migrations(string $dir): array {
    if (!is_dir($dir)) return [];
    $files = glob($dir . '/*.sql') ?: [];
    $names = array_map('basename', $files);
    sort($names, SORT_NATURAL);
    return $names;
}
function record_migration(PDO $pdo, string $file, ?string $note = null): void {
    $s = $pdo->prepare("INSERT INTO schema_migrations (filename, note) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE applied_at = CURRENT_TIMESTAMP, note = VALUES(note)");
    $s->execute([$file, $note]);
}

/** Run every statement in one migration/SQL file. Returns [messages[], hadRealError]. */
function run_sql_file(PDO $pdo, string $path): array {
    $msgs = []; $realError = false;
    foreach (split_sql(file_get_contents($path)) as $i => $stmt) {
        $label = 'stmt ' . ($i + 1);
        try {
            $affected = $pdo->exec($stmt);
            $msgs[] = ['ok', "{$label}: OK" . ($affected !== false ? " ({$affected} row(s))" : '')];
        } catch (PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if (is_benign_code($code)) {
                $msgs[] = ['skip', "{$label}: already applied (MySQL {$code}) — " . first_line($e->getMessage())];
            } else {
                $msgs[] = ['err', "{$label}: {$e->getMessage()}"];
                $realError = true;
            }
        }
    }
    return [$msgs, $realError];
}
function first_line(string $s): string { return trim(explode("\n", $s)[0]); }

// ── POST actions ─────────────────────────────────────────────────────────────
$out = ['title' => '', 'msgs' => []];
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    ensure_migrations_table($pdo);
    $action  = $_POST['action'] ?? '';
    $applied = applied_migrations($pdo);

    if ($action === 'run_all_migrations') {
        $out['title'] = 'Run all pending migrations';
        $ran = 0;
        foreach (discover_migrations($migrationsDir) as $file) {
            if (isset($applied[$file])) continue;
            $path = $migrationsDir . '/' . $file;
            if (is_stub_file($path)) { record_migration($pdo, $file, 'stub - nothing to run'); $out['msgs'][] = ['skip', "{$file}: stub, marked applied"]; continue; }
            [$m, $err] = run_sql_file($pdo, $path);
            foreach ($m as $x) $out['msgs'][] = [$x[0], "{$file} · {$x[1]}"];
            if (!$err) { record_migration($pdo, $file); $out['msgs'][] = ['done', "{$file}: recorded as applied"]; $ran++; }
            else       { $out['msgs'][] = ['err', "{$file}: NOT recorded — fix the error above and re-run"]; }
        }
        $out['msgs'][] = ['done', $ran ? "Applied {$ran} migration(s)." : 'Nothing pending — database is up to date.'];

    } elseif ($action === 'run_migration') {
        $file = basename($_POST['file'] ?? '');
        $path = $migrationsDir . '/' . $file;
        $out['title'] = "Run {$file}";
        if (!is_file($path)) { $out['msgs'][] = ['err', "Not found: {$file}"]; }
        elseif (is_stub_file($path)) { record_migration($pdo, $file, 'stub'); $out['msgs'][] = ['skip', 'Stub — marked applied.']; }
        else {
            [$m, $err] = run_sql_file($pdo, $path); $out['msgs'] = array_merge($out['msgs'], $m);
            if (!$err) { record_migration($pdo, $file); $out['msgs'][] = ['done', 'Recorded as applied.']; }
            else       { $out['msgs'][] = ['err', 'Left as pending due to error.']; }
        }

    } elseif ($action === 'mark_applied') {
        $file = basename($_POST['file'] ?? '');
        record_migration($pdo, $file, 'baseline - marked without running');
        $out['title'] = "Baseline {$file}"; $out['msgs'][] = ['done', "{$file} marked applied (not executed)."];

    } elseif ($action === 'unmark') {
        $file = basename($_POST['file'] ?? '');
        $pdo->prepare("DELETE FROM schema_migrations WHERE filename=?")->execute([$file]);
        $out['title'] = "Un-mark {$file}"; $out['msgs'][] = ['done', "{$file} set back to pending."];

    } elseif ($action === 'baseline_all') {
        $out['title'] = 'Baseline: mark all migrations applied';
        foreach (discover_migrations($migrationsDir) as $file) record_migration($pdo, $file, 'baseline');
        $out['msgs'][] = ['done', 'All discovered migrations marked applied (none executed).'];

    } elseif ($action === 'run_schema') {
        $out['title'] = 'Run schema.sql';
        if (($_POST['confirm'] ?? '') !== $dbname) { $out['msgs'][] = ['warn', 'Type the database name exactly to confirm.']; }
        elseif (!is_file($schemaFile)) { $out['msgs'][] = ['err', 'schema.sql not found.']; }
        else { [$m] = run_sql_file($pdo, $schemaFile); $out['msgs'] = $m; }

    } elseif ($action === 'run_seed') {
        $out['title'] = 'Run seed.sql';
        if (($_POST['confirm'] ?? '') !== $dbname) { $out['msgs'][] = ['warn', 'Type the database name exactly to confirm.']; }
        elseif (!is_file($seedFile)) { $out['msgs'][] = ['err', 'seed.sql not found.']; }
        else { [$m] = run_sql_file($pdo, $seedFile); $out['msgs'] = $m; }

    } elseif ($action === 'reset_password') {
        $out['title'] = 'Reset admin password';
        try {
            $hash = password_hash('password', PASSWORD_DEFAULT);
            $s = $pdo->prepare("UPDATE users SET password_hash=? WHERE email='admin@typhoncath.test'");
            $s->execute([$hash]);
            $out['msgs'][] = $s->rowCount() > 0
                ? ['done', 'Reset — login: admin@typhoncath.test / password']
                : ['warn', 'No admin user found — run seed first.'];
        } catch (Throwable $e) { $out['msgs'][] = ['err', $e->getMessage()]; }
    }
}

// ── Gather current state for rendering ───────────────────────────────────────
$schemaTables = parse_schema($schemaFile);         // expected: table => [cols]
$liveTables   = $pdo ? all_tables($pdo, $dbname) : [];
if ($pdo) ensure_migrations_table($pdo);
$appliedNow   = $pdo ? applied_migrations($pdo) : [];
$migrationList = discover_migrations($migrationsDir);
$migDirExists  = is_dir($migrationsDir);
$migDirReal    = $migDirExists ? (realpath($migrationsDir) ?: $migrationsDir) : $migrationsDir;
$viewTable    = isset($_GET['view']) && in_array($_GET['view'], $liveTables, true) ? $_GET['view'] : null;

// Union of expected + live tables for the dashboard
$allTableNames = array_values(array_unique(array_merge(array_keys($schemaTables), $liveTables)));
sort($allTableNames);

$pendingCount = 0;
foreach ($migrationList as $f) if (!isset($appliedNow[$f]) && !is_stub_file($migrationsDir . '/' . $f)) $pendingCount++;

function render_key_form(bool $tried): void { ?>
<!doctype html><meta charset="utf-8"><title>DB Setup — locked</title>
<div style="font-family:system-ui;max-width:340px;margin:15vh auto;text-align:center">
  <h2>DB Setup</h2>
  <?php if ($tried): ?><p style="color:#b91c1c">Wrong key.</p><?php endif; ?>
  <form method="post"><input type="password" name="setup_key" placeholder="Setup key" autofocus
    style="padding:.5rem;width:100%;box-sizing:border-box"><button style="margin-top:.5rem;padding:.5rem 1rem">Unlock</button></form>
</div>
<?php }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DB Setup — <?= h($dbname) ?></title>
<style>
  *{box-sizing:border-box}
  body{font-family:system-ui,Arial,sans-serif;font-size:14px;color:#111;background:#f4f5f7;margin:0;line-height:1.45}
  .wrap{max-width:1080px;margin:0 auto;padding:1.25rem 1rem 4rem}
  h1{font-size:1.3rem;margin:0}
  h2{font-size:1rem;margin:0 0 .75rem}
  a{color:#1a56db}
  .head{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.5rem;border-bottom:2px solid #1a56db;padding-bottom:.75rem;margin-bottom:1rem}
  .pill{display:inline-flex;gap:.4rem;align-items:center;padding:.3rem .7rem;border-radius:999px;font-weight:600;font-size:.8rem}
  .pill.ok{background:#e8f6ef;color:#198754}.pill.bad{background:#fde8e8;color:#b91c1c}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem 1.1rem;margin-bottom:1rem}
  .banner{background:#fef2f2;border:1px solid #fca5a5;color:#7f1d1d;border-radius:6px;padding:.6rem .8rem;font-size:.82rem;margin-bottom:1rem}
  table{width:100%;border-collapse:collapse}
  th,td{text-align:left;padding:.4rem .5rem;border-bottom:1px solid #eee;vertical-align:top}
  th{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280}
  .badge{display:inline-block;padding:.15rem .5rem;border-radius:999px;font-size:.72rem;font-weight:700;white-space:nowrap}
  .b-ok{background:#e8f6ef;color:#198754}.b-pend{background:#fffbeb;color:#b7791f}.b-stub{background:#f0f0f0;color:#6b7280}
  .b-miss{background:#fde8e8;color:#b91c1c}.b-drift{background:#fef3c7;color:#92400e}
  button{font:inherit;cursor:pointer;border:1px solid #d1d5db;background:#fff;border-radius:6px;padding:.3rem .6rem}
  button.primary{background:#1a56db;color:#fff;border-color:#1a56db}
  button.danger{background:#fef2f2;color:#b91c1c;border-color:#fca5a5}
  form.inline{display:inline}
  .out{background:#1e1e2e;color:#cdd6f4;border-radius:8px;padding:.9rem 1rem;font-family:ui-monospace,monospace;font-size:.8rem;max-height:320px;overflow:auto}
  .out .ok{color:#a6e3a1}.out .err{color:#f38ba8;font-weight:bold}.out .warn{color:#f9e2af}.out .skip{color:#9399b2}.out .done{color:#89b4fa;font-weight:bold}
  details summary{cursor:pointer;color:#374151;font-weight:600}
  code{background:#f3f4f6;padding:.05rem .3rem;border-radius:4px;font-size:.85em}
  .muted{color:#6b7280;font-size:.8rem}
  .cols{margin:.4rem 0 0;padding-left:1rem;columns:2;font-size:.82rem}
  .actions button{margin:.1rem .1rem 0 0}
</style>
</head>
<body>
<div class="wrap">

  <div class="head">
    <div>
      <h1>Database Setup Console</h1>
      <div class="muted"><?= h($cfg['host'] ?? '') ?> · <code><?= h($dbname) ?></code></div>
    </div>
    <?php if ($pdo): ?><span class="pill ok">● connected</span>
    <?php else: ?><span class="pill bad">● not connected</span><?php endif; ?>
  </div>

  <div class="banner"><strong>⚠ This tool can modify or destroy the database.</strong>
    On a live server set the <code>DB_SETUP_KEY</code> env var and delete this file when finished.</div>

  <?php if (!$pdo): ?>
    <div class="card"><h2>Connection failed</h2><div class="out"><span class="err"><?= h($connectError) ?></span></div>
    <p class="muted">Checked <code><?= h($configFile) ?></code> and <code>DB_*</code> env vars.</p></div>
    </div></body></html><?php exit; endif; ?>

  <?php if ($out['title']): ?>
  <div class="card">
    <h2><?= h($out['title']) ?></h2>
    <div class="out"><?php foreach ($out['msgs'] as [$lvl, $txt]): ?><div class="<?= h($lvl) ?>"><?= h($txt) ?></div><?php endforeach; ?></div>
  </div>
  <?php endif; ?>

  <!-- ── Migrations ────────────────────────────────────────────── -->
  <div class="card">
    <h2>Migrations
      <?php if (!$migrationList): ?><span class="badge b-miss">no files found</span>
      <?php elseif ($pendingCount): ?><span class="badge b-pend"><?= $pendingCount ?> pending</span>
      <?php else: ?><span class="badge b-ok">up to date</span><?php endif; ?>
    </h2>
    <p class="muted">
      Looking in <code><?= h($migDirReal) ?></code> —
      <?php if ($migDirExists): ?><strong><?= count($migrationList) ?></strong> file(s) found.
      <?php else: ?><strong style="color:#b91c1c">folder not found</strong>.<?php endif; ?>
      Applied state is tracked in <code>schema_migrations</code>.
    </p>
    <?php if (!$migrationList): ?>
      <div class="banner"><strong>No migration files are visible to this tool</strong> —
        so "up to date" only means there was nothing to run. It expects a <code>migrations/</code> folder
        right next to <code>db_setup.php</code>, i.e. at <code><?= h($migrationsDir) ?></code>.
        Upload your <code>migrations/</code> folder to that location and reload.</div>
    <?php endif; ?>
    <form class="inline" method="post"><input type="hidden" name="action" value="run_all_migrations">
      <button class="primary" type="submit">▶ Run all pending migrations</button></form>
    <form class="inline" method="post" onsubmit="return confirm('Mark ALL migrations applied WITHOUT running them? Use only if the DB already matches.')">
      <input type="hidden" name="action" value="baseline_all"><button type="submit">Baseline all (mark applied)</button></form>
    <table style="margin-top:.75rem">
      <thead><tr><th>Migration</th><th>Status</th><th>Applied</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$migrationList): ?>
        <tr><td colspan="4" class="muted">No migration files found in the folder above.</td></tr>
      <?php endif; ?>
      <?php foreach ($migrationList as $file):
        $isStub = is_stub_file($migrationsDir . '/' . $file);
        $isApplied = isset($appliedNow[$file]);
        $note = $appliedNow[$file]['note'] ?? '';
        $when = $appliedNow[$file]['applied_at'] ?? '';
      ?>
        <tr>
          <td><code><?= h($file) ?></code><?php if ($note): ?><div class="muted"><?= h($note) ?></div><?php endif; ?></td>
          <td><?php if ($isApplied): ?><span class="badge b-ok">applied</span>
              <?php elseif ($isStub): ?><span class="badge b-stub">stub</span>
              <?php else: ?><span class="badge b-pend">pending</span><?php endif; ?></td>
          <td class="muted"><?= h($when) ?></td>
          <td class="actions">
            <?php if (!$isApplied): ?>
              <form class="inline" method="post"><input type="hidden" name="action" value="run_migration"><input type="hidden" name="file" value="<?= h($file) ?>"><button type="submit">Run</button></form>
              <form class="inline" method="post"><input type="hidden" name="action" value="mark_applied"><input type="hidden" name="file" value="<?= h($file) ?>"><button type="submit">Mark applied</button></form>
            <?php else: ?>
              <form class="inline" method="post"><input type="hidden" name="action" value="unmark"><input type="hidden" name="file" value="<?= h($file) ?>"><button type="submit">Un-mark</button></form>
              <?php if (!$isStub): ?><form class="inline" method="post" onsubmit="return confirm('Re-run this migration SQL now?')"><input type="hidden" name="action" value="run_migration"><input type="hidden" name="file" value="<?= h($file) ?>"><button type="submit">Re-run</button></form><?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Tables & drift ────────────────────────────────────────── -->
  <div class="card">
    <h2>Tables <span class="muted"><?= count($liveTables) ?> live · <?= count($schemaTables) ?> defined in schema.sql</span></h2>
    <table>
      <thead><tr><th>Table</th><th>Status</th><th>Rows</th><th>Columns / drift</th></tr></thead>
      <tbody>
      <?php foreach ($allTableNames as $t):
        if ($t === 'schema_migrations') continue;
        $exists   = in_array($t, $liveTables, true);
        $expected = $schemaTables[$t] ?? null;                 // null = not in schema.sql
        $liveCols = $exists ? array_column(table_columns($pdo, $dbname, $t), 'COLUMN_NAME') : [];
        $missing  = $expected ? array_values(array_diff($expected, $liveCols)) : [];
        $extra    = $expected ? array_values(array_diff($liveCols, $expected)) : [];
        $drift    = $missing || $extra;
      ?>
        <tr>
          <td><code><?= h($t) ?></code><?php if ($expected === null): ?><div class="muted">not in schema.sql</div><?php endif; ?></td>
          <td>
            <?php if (!$exists): ?><span class="badge b-miss">missing</span>
            <?php elseif ($drift): ?><span class="badge b-drift">drift</span>
            <?php else: ?><span class="badge b-ok">ok</span><?php endif; ?>
          </td>
          <td><?= $exists ? h((string)row_count($pdo, $t)) : '—' ?>
              <?php if ($exists): ?><div class="muted"><a href="?view=<?= h($t) ?>">view rows</a></div><?php endif; ?></td>
          <td>
            <?php if ($missing): ?><div><span class="badge b-miss">missing</span> <?= h(implode(', ', $missing)) ?></div><?php endif; ?>
            <?php if ($extra): ?><div><span class="badge b-drift">extra</span> <?= h(implode(', ', $extra)) ?></div><?php endif; ?>
            <?php if ($exists): ?>
              <details><summary><?= count($liveCols) ?> columns</summary>
                <div class="cols"><?php foreach (table_columns($pdo, $dbname, $t) as $c): ?>
                  <div><?= h($c['COLUMN_NAME']) ?> <span class="muted"><?= h($c['COLUMN_TYPE']) ?><?= $c['IS_NULLABLE']==='NO'?' · NOT NULL':'' ?></span></div>
                <?php endforeach; ?></div>
              </details>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($viewTable): ?>
  <div class="card">
    <h2>Rows in <code><?= h($viewTable) ?></code> <a class="muted" href="?">✕ close</a></h2>
    <div style="overflow:auto">
      <?php $rows = $pdo->query("SELECT * FROM `" . str_replace('`','',$viewTable) . "` LIMIT 50")->fetchAll(); ?>
      <?php if (!$rows): ?><p class="muted">No rows.</p><?php else: ?>
        <table><thead><tr><?php foreach (array_keys($rows[0]) as $k): ?><th><?= h($k) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= h(mb_strimwidth((string)$v, 0, 80, '…')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
        <p class="muted">Showing up to 50 rows.</p>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── One-shot actions ─────────────────────────────────────── -->
  <div class="card">
    <h2>Setup actions</h2>
    <details>
      <summary>Run <code>schema.sql</code> — create base tables (fresh install)</summary>
      <p class="muted">Creates the tables defined in schema.sql. On a DB that already has them this reports "already applied" per table. Type the DB name to confirm.</p>
      <form method="post"><input type="hidden" name="action" value="run_schema">
        <input name="confirm" placeholder="<?= h($dbname) ?>" style="padding:.3rem"><button class="danger" type="submit">Run schema.sql</button></form>
    </details>
    <details style="margin-top:.6rem">
      <summary>Run <code>seed.sql</code> — demo data ⚠ overwrites matching IDs</summary>
      <p class="muted"><strong>Not for production.</strong> Uses fixed demo IDs with ON DUPLICATE KEY UPDATE — it will overwrite real rows (including the admin user) that share those IDs. Type the DB name to confirm.</p>
      <form method="post"><input type="hidden" name="action" value="run_seed">
        <input name="confirm" placeholder="<?= h($dbname) ?>" style="padding:.3rem"><button class="danger" type="submit">Run seed.sql</button></form>
    </details>
    <details style="margin-top:.6rem">
      <summary>Reset admin password</summary>
      <p class="muted">Sets <code>admin@typhoncath.test</code> back to password <code>password</code>.</p>
      <form method="post"><input type="hidden" name="action" value="reset_password"><button type="submit">Reset admin password</button></form>
    </details>
  </div>

</div>
</body>
</html>
