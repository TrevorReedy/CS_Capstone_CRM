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
// Text wrapping helper
// -------------------------

function wrapText(
    string $text,
    int $length = 85
): array {

    return explode(
        "\n",
        wordwrap(
            $text,
            $length,
            "\n",
            true
        )
    );
}




// -------------------------
// Generate PDF
// -------------------------

$pdf = new SimplePDF();



// -------------------------
// Title
// -------------------------

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

$contactsHeadingY = 545;


$pdf->heading(
    "Contacts",
    $contactsHeadingY
);


$y = $contactsHeadingY - 25;


if (count($contacts) > 0) {


    foreach ($contacts as $contact) {


        $name =
            trim(
                ($contact['first_name'] ?? '') .
                " " .
                ($contact['last_name'] ?? '')
            );


        $contactText =
            ($name ?: 'Unnamed Contact') .
            " | " .
            ($contact['email'] ?: 'No Email') .
            " | " .
            ($contact['phone'] ?: 'No Phone');



        foreach (
            wrapText($contactText, 85)
            as $line
        ) {


            $pdf->text(
                60,
                $y,
                $line
            );


            $y -= 16;


            $pdf->checkPageBreak($y);
        }


        $y -= 4;
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


$pdf->checkPageBreak($y);


$interactionHeadingY = $y - 20;


$pdf->heading(
    "Interaction History",
    $interactionHeadingY
);


$y = $interactionHeadingY - 25;



if (count($interactions) > 0) {


    foreach ($interactions as $interaction) {


        $interactionTitle =
            ($interaction['interaction_type'] ?? 'Note') .
            " - " .
            ($interaction['interaction_subject'] ?? 'No Subject') .
            " (" .
            ($interaction['interaction_date'] ?? '') .
            ")";



        foreach (
            wrapText($interactionTitle, 85)
            as $line
        ) {


            $pdf->text(
                60,
                $y,
                $line
            );


            $y -= 16;


            $pdf->checkPageBreak($y);
        }




        if (!empty($interaction['notes'])) {


            foreach (
                wrapText(
                    "Notes: " . $interaction['notes'],
                    80
                )
                as $line
            ) {


                $pdf->text(
                    80,
                    $y,
                    $line
                );


                $y -= 16;


                $pdf->checkPageBreak($y);
            }

        }



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