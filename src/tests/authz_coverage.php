<?php
declare(strict_types=1);

/**
 * authz_coverage.php — static authorization wiring test (no server, no DB).
 *
 * Companion to csrf_coverage.php. CSRF proves a request came from our own form;
 * this proves the sender was allowed to make it at all. The two are independent:
 * a page can be fully CSRF-covered and still let any logged-in user rewrite the
 * permission matrix, which is exactly the hole this file was written to close.
 *
 * Checks:
 *
 *   1. GATE check   — every public .php entry point either calls
 *                     Permissions::can()/require(), or is on the PUBLIC_ROUTES
 *                     allow-list below. Login-only is NOT sufficient: hiding a
 *                     button in the sidebar does not stop a direct URL.
 *
 *   2. ORDER check  — the permission gate appears before the first controller
 *                     dispatch / POST handler in the file. A check that runs
 *                     after the write has already happened is decoration.
 *                     (This is the campaign/edit.php bug: handleUpdatePost()
 *                     ran, and only the GET render was gated.)
 *
 *   3. VOCAB check  — every permission string referenced in code is one the
 *                     seed actually grants. A typo'd permission is deny-all for
 *                     real roles but invisible to a Super Admin, so it hides
 *                     until someone else hits it.
 *
 * Usage:   php tests/authz_coverage.php
 * Exit:    0 = all passed, 1 = one or more failures.
 */

$root      = dirname(__DIR__);            // .../src
$publicDir = $root . '/public';
$seedFile  = $root . '/database/seed.sql';

$failures = 0;
$passes   = 0;

/**
 * Entry points that are deliberately reachable without a permission.
 * Anything added here is a decision, not an oversight — say why.
 */
const PUBLIC_ROUTES = [
    'public/index.php'  => 'bare redirect to dashboard.php; no data access',
    'public/login.php'  => 'authentication entry point',
    'public/logout.php' => 'must work for any authenticated session',
];

function pass(string $msg): void {
    global $passes;
    $passes++;
    fwrite(STDOUT, "PASS  $msg\n");
}

function fail(string $msg): void {
    global $failures;
    $failures++;
    fwrite(STDOUT, "FAIL  $msg\n");
}

/** Recursively list *.php files under a directory. */
function php_files(string $dir): array {
    if (!is_dir($dir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
            $out[] = $f->getPathname();
        }
    }
    sort($out);
    return $out;
}

/** Path shown in output — relative to the project src/ root. */
function rel(string $path): string {
    global $root;
    return ltrim(str_replace($root, '', $path), '/\\');
}

/**
 * File contents with comments blanked out, newlines preserved so byte offsets
 * and line numbers stay accurate. Several files (Middleware/require_role.php,
 * Core/Permissions.php) carry commented-out Permissions::require() examples that
 * would otherwise read as real checks — or as typo'd permission names.
 */
function code_only(string $path): string {
    $src = file_get_contents($path);
    $out = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token)) {
            $text = $token[1];
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                // Keep the line count identical; drop everything else.
                $out .= str_repeat("\n", substr_count($text, "\n"));
                continue;
            }
            $out .= $text;
            continue;
        }
        $out .= $token;
    }
    return $out;
}

$publicFiles = php_files($publicDir);

// ─────────────────────────────────────────────────────────────────────────────
// 1. GATE — every entry point is permission-gated or explicitly allow-listed.
// ─────────────────────────────────────────────────────────────────────────────
fwrite(STDOUT, "── Gate coverage ──────────────────────\n");

foreach ($publicFiles as $file) {
    $relPath = rel($file);
    $src     = code_only($file);

    if (isset(PUBLIC_ROUTES[$relPath])) {
        pass("$relPath  intentionally public (" . PUBLIC_ROUTES[$relPath] . ')');
        continue;
    }

    if (!preg_match('/Permissions::(can|require)\s*\(/', $src)) {
        fail("$relPath  reachable by ANY logged-in user — no Permissions:: check");
        continue;
    }

    pass("$relPath  gated");
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. ORDER — the gate precedes the first dispatch/handler call.
// ─────────────────────────────────────────────────────────────────────────────
fwrite(STDOUT, "\n── Gate ordering ──────────────────────\n");

foreach ($publicFiles as $file) {
    $relPath = rel($file);
    if (isset(PUBLIC_ROUTES[$relPath])) {
        continue;
    }

    $src = code_only($file);

    if (!preg_match('/Permissions::(can|require)\s*\(/', $src, $m, PREG_OFFSET_CAPTURE)) {
        continue;   // already reported by the GATE check
    }
    $gateAt = $m[0][1];

    // First call that can mutate state: a controller handle*Post(), or a direct
    // write through PDO from the page itself.
    $dispatchAt = null;
    if (preg_match('/->handle[A-Za-z]*Post\s*\(|->save\s*\(|\$db->prepare\s*\(\s*"\s*(?:UPDATE|DELETE|INSERT)/i',
                   $src, $dm, PREG_OFFSET_CAPTURE)) {
        $dispatchAt = $dm[0][1];
    }

    if ($dispatchAt === null) {
        pass("$relPath  no state-changing dispatch");
        continue;
    }

    if ($gateAt > $dispatchAt) {
        $line = substr_count($src, "\n", 0, $dispatchAt) + 1;
        fail("$relPath  permission check runs AFTER the write at line $line");
        continue;
    }

    pass("$relPath  gate precedes dispatch");
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. VOCAB — permission strings in code exist in the seed.
// ─────────────────────────────────────────────────────────────────────────────
fwrite(STDOUT, "\n── Permission vocabulary ──────────────\n");

$seeded = [];
if (is_file($seedFile)) {
    // Matches the "UNION ALL SELECT 'x' AS permission" rows in seed.sql.
    if (preg_match_all("/SELECT\s+'([a-z_]+\.[a-z_]+)'\s+AS permission/i", file_get_contents($seedFile), $sm)) {
        $seeded = array_unique($sm[1]);
    }
}

if (!$seeded) {
    fail('database/seed.sql  could not parse any seeded permissions');
} else {
    pass('database/seed.sql  ' . count($seeded) . ' permissions seeded');

    $used = [];
    foreach (array_merge($publicFiles, php_files($root . '/app')) as $file) {
        if (preg_match_all("/Permissions::(?:can|require)\s*\(\s*'([^']+)'/", code_only($file), $um)) {
            foreach ($um[1] as $perm) {
                $used[$perm][] = rel($file);
            }
        }
    }

    // Permission strings held in variables (e.g. inventory's denyUnlessAllowed()
    // and the account_detail write map) are checked by their own literals below.
    foreach (php_files($root . '/public') as $file) {
        if (preg_match_all("/=>\s*'([a-z_]+\.[a-z_]+)'/", code_only($file), $vm)) {
            foreach ($vm[1] as $perm) {
                $used[$perm][] = rel($file);
            }
        }
    }

    ksort($used);
    foreach ($used as $perm => $files) {
        if (!in_array($perm, $seeded, true)) {
            fail("'$perm'  used in " . $files[0] . ' but never seeded — denies every non-Super-Admin');
            continue;
        }
        pass("'$perm'  seeded (" . count($files) . ' use' . (count($files) === 1 ? '' : 's') . ')');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
fwrite(STDOUT, "\n" . str_repeat('-', 40) . "\n");
fwrite(STDOUT, "$passes passed, $failures failed\n");
exit($failures > 0 ? 1 : 0);
