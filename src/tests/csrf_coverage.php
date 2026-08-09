<?php
declare(strict_types=1);

/**
 * csrf_coverage.php — static CSRF wiring test (no server, no DB, no framework).
 *
 * Runs three checks across the codebase and exits non-zero if any fail, so it
 * can be wired into CI or a pre-push hook:
 *
 *   1. FORM check    — every <form method="POST"> in a view renders
 *                      Csrf::field() before its </form>. A form without it is
 *                      either unprotected or will 403 on submit.
 *
 *   2. HANDLER check — every public .php that processes a POST enforces the
 *                      CSRF middleware (require .../Middleware/csrf.php) or calls
 *                      Csrf::check() directly. A handler missing this silently
 *                      accepts forged requests.
 *
 *   3. LINT check    — no PHP file has an output-before-headers landmine:
 *                      a `?>` inside a // or # single-line comment (the exact
 *                      bug that broke login), a UTF-8 BOM, or whitespace before
 *                      the opening <?php tag.
 *
 * Usage:   php tests/csrf_coverage.php
 * Exit:    0 = all passed, 1 = one or more failures.
 */

$root      = dirname(__DIR__);            // .../src
$appDir    = $root . '/app';
$publicDir = $root . '/public';

$failures = 0;
$passes   = 0;

/** Print a PASS line. */
function pass(string $msg): void {
    global $passes;
    $passes++;
    fwrite(STDOUT, "PASS  $msg\n");
}

/** Print a FAIL line. */
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

/** 1-based line number of a byte offset within $content. */
function line_at(string $content, int $offset): int {
    return substr_count($content, "\n", 0, $offset) + 1;
}

/** Path shown in output — relative to the project src/ root. */
function rel(string $path): string {
    global $root;
    return ltrim(str_replace($root, '', $path), '/');
}

// ---------------------------------------------------------------------------
// 1. FORM check: POST forms must contain Csrf::field()
// ---------------------------------------------------------------------------
fwrite(STDOUT, "== FORM check: <form method=POST> must render Csrf::field() ==\n");

$viewFiles = array_merge(
    php_files($appDir . '/Modules'),   // module views
    [$appDir . '/Shared/login.php']    // login form lives in Shared/
);
$viewFiles = array_values(array_filter($viewFiles, 'is_file'));

foreach ($viewFiles as $file) {
    $content = (string)file_get_contents($file);

    // Find every opening <form ...> tag.
    if (!preg_match_all('/<form\b[^>]*>/i', $content, $m, PREG_OFFSET_CAPTURE)) {
        continue; // no forms in this view
    }

    foreach ($m[0] as $match) {
        $openTag    = $match[0];
        $openOffset = $match[1];
        $line       = line_at($content, $openOffset);

        // Determine the method. HTML defaults to GET; we only enforce on POST.
        $method = 'get';
        if (preg_match('/method\s*=\s*["\']?\s*(post|get)/i', $openTag, $mm)) {
            $method = strtolower($mm[1]);
        }
        if ($method !== 'post') {
            continue; // GET form — no token required
        }

        // The matching </form> is the next one (HTML forbids nested forms).
        $closeOffset = stripos($content, '</form>', $openOffset);
        $span = $closeOffset === false
            ? substr($content, $openOffset)                       // unterminated — scan to EOF
            : substr($content, $openOffset, $closeOffset - $openOffset);

        if (strpos($span, 'Csrf::field(') !== false) {
            pass(rel($file) . ":$line  POST form has Csrf::field()");
        } else {
            fail(rel($file) . ":$line  POST form is MISSING Csrf::field()");
        }
    }
}

// ---------------------------------------------------------------------------
// 2. HANDLER check: POST-processing pages must enforce Csrf::check()
// ---------------------------------------------------------------------------
fwrite(STDOUT, "\n== HANDLER check: POST handlers must enforce CSRF ==\n");

foreach (php_files($publicDir) as $file) {
    $content = (string)file_get_contents($file);

    // Does this page process a POST? (as opposed to a pure read-only page)
    $processesPost =
        (strpos($content, 'REQUEST_METHOD') !== false && stripos($content, 'POST') !== false)
        || preg_match('/->\s*handle\w*Post\s*\(/', $content)
        || strpos($content, '$_POST[') !== false;

    if (!$processesPost) {
        continue;
    }

    $enforcesCsrf =
        strpos($content, 'Middleware/csrf.php') !== false
        || strpos($content, 'Csrf::check') !== false;

    if ($enforcesCsrf) {
        pass(rel($file) . "  enforces Csrf::check()");
    } else {
        fail(rel($file) . "  processes POST but does NOT enforce CSRF");
    }
}

// ---------------------------------------------------------------------------
// 3. LINT check: output-before-headers landmines
// ---------------------------------------------------------------------------
fwrite(STDOUT, "\n== LINT check: no output-before-headers hazards ==\n");

/**
 * Find single-line comments (// or #) that are terminated by a close tag in a
 * way that leaks the following PHP source as page output — the exact bug that
 * broke the login middleware.
 *
 * Uses PHP's own tokenizer, so it understands strings, <?= tags, and literal
 * '#' characters in HTML (e.g. "#<?= $id ?>") — none of which are comments.
 * A `<?php // note ?>` on one line in a template is deliberately NOT flagged,
 * because what follows the close tag is intended HTML, not leaked PHP.
 *
 * @return array<int> line numbers of offending comments (empty if clean)
 */
function comment_breakouts(string $src): array {
    $out    = [];
    $tokens = @token_get_all($src);
    $count  = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $t = $tokens[$i];
        if (!is_array($t) || $t[0] !== T_COMMENT) {
            continue;
        }
        $text = $t[1];

        // Only single-line comments (// or #). Block comments /* */ are safe:
        // a close tag inside them does not end the block.
        $isSingleLine = strncmp($text, '//', 2) === 0
            || ($text[0] === '#' && strncmp($text, '#[', 2) !== 0);
        if (!$isSingleLine) {
            continue;
        }

        // Was the comment cut short by a close tag? If so the very next token
        // is T_CLOSE_TAG. A normally newline-terminated comment is not.
        $next = $tokens[$i + 1] ?? null;
        if (!is_array($next) || $next[0] !== T_CLOSE_TAG) {
            continue;
        }

        // Distinguish a real leak from a legitimate template close: look at
        // what follows the close tag. Leaked PHP (more comment lines, use/
        // require, a $variable, etc.) is the bug; intended HTML is fine.
        $after  = $tokens[$i + 2] ?? null;
        $leaked = is_array($after) ? $after[1] : (is_string($after) ? $after : '');
        $firstLine = '';
        foreach (explode("\n", $leaked) as $ln) {
            if (trim($ln) !== '') { $firstLine = $ln; break; }
        }
        $dangerous = $firstLine !== ''
            && (bool)preg_match('~^\s*(//|#|\*|use\s|require|declare|namespace|function|class|\$)~', $firstLine);

        // A comment that swallowed an opening echo tag (as in the original
        // middleware bug) is the classic case regardless of what follows.
        if (!$dangerous && strpos($text, '<?') !== false) {
            $dangerous = true;
        }

        if ($dangerous) {
            $out[] = $t[2]; // token line number
        }
    }

    return $out;
}

// config/ is included in the lint sweep because it is exactly the kind of file
// nobody looks at twice: config/app.php carried an HTML comment above its
// opening tag for months, which would have emitted output before headers the
// moment anything required it.
$allPhp = array_merge(php_files($appDir), php_files($publicDir), php_files($root . '/config'));
foreach ($allPhp as $file) {
    $raw = (string)file_get_contents($file);
    $problems = [];

    // (a) UTF-8 BOM at the very start.
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") {
        $problems[] = "UTF-8 BOM at start of file";
    }

    // (b) Output before the opening <?php.
    //
    // Whitespace is always a bug: it is invisible, and it is emitted the instant
    // the file is loaded, which kills any header() or session_start() that
    // follows.
    //
    // Literal *text* is a bug only in files that are not templates. A view under
    // views/ or in Shared/ opens with markup on purpose — that is the entire job
    // of a template, and it is included long after headers are sent. Core,
    // config and the public entry points are different: they run before or
    // during header emission, and config/app.php sat there for months with an
    // HTML comment above its opening tag that nobody noticed because nothing
    // included it.
    $isTemplate = (bool) preg_match('#/(views|Shared)/#', str_replace('\\', '/', $file));

    $pos = strpos($raw, '<?php');
    if ($pos === false) {
        // A .php file with no <?php is fine only if it's pure HTML; skip.
    } elseif ($pos > 0) {
        $before = substr($raw, 0, $pos);
        if (trim($before) === '') {
            $problems[] = "whitespace before opening <?php tag";
        } elseif (!$isTemplate) {
            $problems[] = "text before opening <?php tag (emitted as output): "
                . json_encode(mb_substr(trim($before), 0, 80));
        }
    }

    // (c) a close tag that cuts off a // or # comment and leaks PHP as output.
    foreach (comment_breakouts($raw) as $lineNo) {
        $problems[] = "single-line comment terminated by a close tag on line $lineNo (leaks PHP as output)";
    }

    if ($problems) {
        foreach ($problems as $p) {
            fail(rel($file) . "  $p");
        }
    } else {
        pass(rel($file) . "  clean");
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
fwrite(STDOUT, "\n----------------------------------------\n");
fwrite(STDOUT, sprintf("%d passed, %d failed\n", $passes, $failures));

exit($failures > 0 ? 1 : 0);
 