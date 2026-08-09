<?php

require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Campaign\CampaignRepository;
use App\Core\PDF\SimplePDF;

Auth::requireLogin();

if (!Permissions::can('campaigns.view')) {
    layout_deny();
    exit;
}

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    die("Missing campaign ID");
}


// -------------------------
// Load campaign + audience
// (same repository the on-screen metrics view uses, so the PDF carries the
//  identical data — core info, performance metrics, audience segments.)
// -------------------------

$repo = new CampaignRepository();

$campaign = $repo->findById($id);

if (!$campaign) {
    die("Campaign not found");
}

$audience = $repo->getAudienceByCampaignId($id);


// -------------------------
// Generate PDF
// -------------------------

$pdf = new SimplePDF();

$pdf->title("TyphonCath CRM - Campaign Report");


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
// CAMPAIGN DETAILS
// =========================

$section("Campaign Details");

$line("Name: " . ($campaign['campaign_name'] ?: 'N/A'), 60, 12, true);
$line("Status: " . ($campaign['status'] ?: 'N/A'));
$line("Type: " . ($campaign['campaign_type'] ?: 'N/A'));
$line("Created By: " . ($campaign['created_by_name'] ?: 'N/A')
    . "  (" . ($campaign['created_at'] ?? '') . ")");
$line("Last Updated: " . ($campaign['updated_at'] ?? 'N/A'));

if (!empty($campaign['scheduled_at'])) {
    $line("Scheduled For: " . $campaign['scheduled_at']);
}


// =========================
// PERFORMANCE METRICS
// =========================

$section("Performance Metrics");

$line("Sent: " . number_format((int)$campaign['sent_count']) . " recipients");

if ((int)$campaign['sent_count'] === 0) {
    $line("Campaign has not been sent yet.");
}


// =========================
// AUDIENCE
// =========================

$section("Audience");

if (count($audience) > 0) {

    foreach ($audience as $row) {

        $line(
            "Segment: " . ($row['segment_name'] ?: '-')
            . "   Tag: " . ($row['tag_filter'] ?: '-')
        );
        $line(
            "   Account: " . ($row['account_name'] ?: '-')
            . "   Contact: " . ($row['contact_name'] ?: '-'),
            70
        );
    }

} else {
    $line("No audience segments defined.");
}


// -------------------------
// Footer
// -------------------------

$pdf->text(60, 50, "Generated: " . date("m/d/Y"));


$pdf->output(SimplePDF::filename('Campaign', $campaign['campaign_name']));
