<?php

require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\RFQ\RFQRepository;
use App\Core\PDF\SimplePDF;

Auth::requireLogin();

if (!Permissions::can('rfqs.view')) {
    layout_deny();
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    die("Missing RFQ ID");
}


// -------------------------
// Load RFQ + connected entities
// (same repository the on-screen detail view uses, so the PDF carries the
//  identical data — account, contact, quotes, reservations.)
// -------------------------

$repo = new RFQRepository();

$rfq = $repo->findById($id);

if (!$rfq) {
    die("RFQ not found");
}

$quotes       = $repo->getQuotesByRfqId($id);
$reservations = $repo->getReservationsByRfqId($id);

$catalogValue = array_sum(array_map(
    fn($r) => (float)$r['price'] * (int)$r['quantity_reserved'],
    $reservations
));


// -------------------------
// Generate PDF
// -------------------------

$pdf = new SimplePDF();

$pdf->title("TyphonCath CRM - RFQ Report");


// A running cursor so sections flow top-down. Helpers keep the y bookkeeping
// in one place (SimplePDF itself is a single fixed page).
$y = 700;

/** Emit one text line at the cursor and advance down. */
$line = function (string $text, float $x = 60, int $size = 11, bool $bold = false) use ($pdf, &$y): void {
    if ($y < 60) {                       // never overprint the footer
        return;
    }
    $pdf->text($x, $y, $text, $size, $bold);
    $y -= 16;
};

/** Start a new section: underlined heading + a little breathing room. */
$section = function (string $text) use ($pdf, &$y): void {
    $y -= 12;
    $pdf->heading($text, $y);
    $y -= 24;
};


// =========================
// RFQ DETAILS
// =========================

$section("RFQ Details");

$line("Title: " . ($rfq['title'] ?: 'N/A'), 60, 12, true);
$line("Stage: " . ($rfq['stage'] ?: 'N/A'));
$line("Created By: " . ($rfq['created_by_name'] ?: 'N/A')
    . "  (" . ($rfq['created_at'] ?? '') . ")");
$line("Last Updated: " . ($rfq['updated_at'] ?? 'N/A'));


// =========================
// ACCOUNT & CONTACT
// =========================

$section("Account & Contact");

if ($rfq['account_id']) {
    $line("Account: " . ($rfq['account_name'] ?: 'N/A'));
    $line("  Email: " . ($rfq['account_email'] ?: 'N/A'), 70);
    $line("  Phone: " . ($rfq['account_phone'] ?: 'N/A'), 70);
} else {
    $line("Account: (none)");
}

if ($rfq['contact_id'] && trim((string)$rfq['contact_name']) !== '') {
    $line("Contact: " . trim($rfq['contact_name'])
        . ($rfq['contact_title'] ? " - " . $rfq['contact_title'] : ''));
    $line("  Email: " . ($rfq['contact_email'] ?: 'N/A'), 70);
    $line("  Phone: " . ($rfq['contact_phone'] ?: 'N/A'), 70);
} else {
    $line("Contact: (none)");
}


// =========================
// DESCRIPTION
// =========================

if (!empty($rfq['description'])) {

    $section("Description");

    // SimplePDF has no wrapping, so fold long text onto multiple lines.
    foreach (explode("\n", wordwrap($rfq['description'], 95, "\n", true)) as $descLine) {
        $line($descLine);
    }
}


// =========================
// QUOTES
// =========================

$section("Quotes");

if (count($quotes) > 0) {

    foreach ($quotes as $i => $q) {

        $net = (float)$q['quote_amount'] - (float)$q['discount'];

        $valid = ($q['validity_start_date'] ? $q['validity_start_date'] : '?')
            . " to "
            . ($q['validity_end_date'] ? $q['validity_end_date'] : '?');

        $line(
            "#" . ($i + 1)
            . "  Amount $" . number_format((float)$q['quote_amount'], 2)
            . "  Discount $" . number_format((float)$q['discount'], 2)
            . "  Net $" . number_format($net, 2)
        );
        $line("     Valid: " . $valid, 70);
    }

} else {
    $line("No quotes attached to this RFQ.");
}


// =========================
// INVENTORY RESERVATIONS
// =========================

$section("Inventory Reservations");

if (count($reservations) > 0) {

    foreach ($reservations as $res) {

        $lineTotal = (float)$res['price'] * (int)$res['quantity_reserved'];

        $line(
            ($res['product_name'] ?: 'Unnamed')
            . " (" . ($res['sku'] ?: 'no SKU') . ")"
            . "  $" . number_format((float)$res['price'], 2)
            . " x " . (int)$res['quantity_reserved']
            . " = $" . number_format($lineTotal, 2)
            . "  [" . ($res['reservation_status'] ?: 'N/A') . "]"
        );
    }

    $line("Catalog Value: $" . number_format($catalogValue, 2), 60, 12, true);

} else {
    $line("No inventory reserved for this RFQ.");
}


// -------------------------
// Footer
// -------------------------

$pdf->text(60, 50, "Generated: " . date("m/d/Y"));


// Entity name for the filename: the account, or the RFQ title when the RFQ
// isn't tied to an account (account_id is nullable).
$entityName = !empty($rfq['account_name']) ? $rfq['account_name'] : $rfq['title'];

$pdf->output(SimplePDF::filename('RFQ', $entityName));
