<?php

require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Core\Database;
use App\Core\PDF\SimplePDF;

Auth::requireLogin();

if (!Permissions::can('customers.view')) {
    layout_deny();
    exit;
}

$accountId = $_GET['id'] ?? null;

if (!$accountId) {
    die("Missing account ID");
}


$db = Database::connection();


// -------------------------
// Load Account
// -------------------------

$stmt = $db->prepare("
    SELECT *
    FROM accounts
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$accountId]);

$account = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$account) {
    die("Account not found");
}



// -------------------------
// Load Contacts
// -------------------------

$stmt = $db->prepare("
    SELECT *
    FROM contacts
    WHERE account_id = ?
    ORDER BY first_name, last_name
");

$stmt->execute([$accountId]);

$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);



// -------------------------
// Load Interactions
// -------------------------

$stmt = $db->prepare("
    SELECT 
        i.*,
        u.name AS user_name
    FROM interactions i
    LEFT JOIN users u 
        ON i.user_id = u.id
    WHERE i.account_id = ?
    ORDER BY i.interaction_date DESC
");

$stmt->execute([$accountId]);

$interactions = $stmt->fetchAll(PDO::FETCH_ASSOC);




// -------------------------
// Generate PDF
// -------------------------

$pdf = new SimplePDF();


// Title

$pdf->title(
    "TyphonCath CRM - Account Report"
);



// =========================
// ACCOUNT INFORMATION
// =========================

$pdf->heading(
    "Account Information",
    690
);


$pdf->multiline(
    60,
    665,
    [
        "Company: " . ($account['account_name'] ?: 'N/A'),
        "Email: " . ($account['email'] ?: 'N/A'),
        "Phone: " . ($account['phone'] ?: 'N/A'),
        "Address: " . ($account['address'] ?: 'N/A'),
        "Industry: " . ($account['industry'] ?: 'N/A')
    ],
    16
);



// =========================
// CONTACTS
// =========================

$pdf->heading(
    "Contacts",
    545
);


$y = 520;


if (count($contacts) > 0) {

    foreach ($contacts as $contact) {

        $name =
            trim(
                ($contact['first_name'] ?? '') .
                " " .
                ($contact['last_name'] ?? '')
            );


        $pdf->text(
            60,
            $y,
            ($name ?: 'Unnamed Contact') .
            " | " .
            ($contact['email'] ?: 'No Email') .
            " | " .
            ($contact['phone'] ?: 'No Phone')
        );


        $y -= 18;
    }

} else {

    $pdf->text(
        60,
        $y,
        "No contacts found."
    );
}



// =========================
// INTERACTION HISTORY
// =========================

$pdf->heading(
    "Interaction History",
    390
);


$y = 365;


if (count($interactions) > 0) {

    foreach ($interactions as $interaction) {


        $pdf->text(
            60,
            $y,
            ($interaction['interaction_type'] ?? 'Note') .
            " - " .
            ($interaction['interaction_subject'] ?? 'No Subject') .
            " (" .
            ($interaction['interaction_date'] ?? '') .
            ")"
        );


        $y -= 18;


        if (!empty($interaction['notes'])) {

            $pdf->text(
                80,
                $y,
                "Notes: " . $interaction['notes']
            );

            $y -= 18;
        }


        // blank line between interactions
        $y -= 8;
    }


} else {

    $pdf->text(
        60,
        $y,
        "No interactions found."
    );
}



// -------------------------
// Footer
// -------------------------

$pdf->text(
    60,
    50,
    "Generated: " . date("m/d/Y")
);



$pdf->output(
    SimplePDF::filename('Customer', $account['account_name'])
);