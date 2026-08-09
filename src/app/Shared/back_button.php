<?php
/**
 * Adaptive back button.
 *
 * Usage — set $backUrl to the logical parent before including:
 *   <?php $backUrl = '/modules/rfq/pipeline.php'; ?>
 *   <?php include __DIR__ . '/back_button.php'; ?>
 *
 * Behaviour:
 *   - If the user navigated here from within the same site, goes one step back
 *     in browser history (preserves scroll position, avoids a redundant round-trip).
 *   - If the user landed directly (new tab, bookmark, external link), navigates
 *     to $backUrl so they always have somewhere to go.
 *
 * TODO: IMPLEMENT LATER, INTER MODULE BACK BUTTON
 *   When cross-module navigation is built (e.g. RFQ detail → Account detail → back to RFQ),
 *   replace the referrer check with a server-side navigation stack so the logical
 *   parent is always deterministic regardless of history.
 */
$_back_fallback = htmlspecialchars($backUrl ?? '/dashboard.php', ENT_QUOTES);
?>
<button
    type="button"
    class="btn btn-secondary"
    onclick="document.referrer.startsWith(window.location.origin) ? history.back() : (window.location.href='<?= $_back_fallback ?>')"
>&#8592; Back</button>
<?php unset($_back_fallback); ?>
