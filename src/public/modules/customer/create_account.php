<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Customer\CustomerRepository;

Auth::requireLogin();

// Reject state-changing (POST) requests without a valid CSRF token.
require_once __DIR__ . '/../../../app/Middleware/csrf.php';

// This page creates either an account or a contact. Reaching the form needs one
// of the two; the POST branch below re-checks the specific one for the submitted
// entity_type, so holding only customers.create can't be used to insert contacts.
if (!Permissions::can('customers.create') && !Permissions::can('contacts.create')) {
    layout_deny();
    exit;
}

$repo     = new CustomerRepository();
$accounts = $repo->all();   // for the contact → account picker
$errors   = [];
$input     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $entityType = $_POST['entity_type'] ?? '';

    // Per-entity authorization: the page-level check above only proves the user
    // may create one of the two kinds.
    $needed = $entityType === 'contact' ? 'contacts.create' : 'customers.create';
    if (!Permissions::can($needed)) {
        layout_deny();
        exit;
    }

    // Capture everything for re-populating the form on a validation error.
    $input = [
        'entity_type'  => $entityType,
        'account_name' => trim($_POST['account_name'] ?? ''),
        'email'        => trim($_POST['email']        ?? ''),
        'phone'        => trim($_POST['phone']        ?? ''),
        'address'      => trim($_POST['address']      ?? ''),
        'industry'     => trim($_POST['industry']     ?? ''),
        'source'       => trim($_POST['source']       ?? ''),
        'tags'         => trim($_POST['tags']         ?? ''),
        'account_id'   => trim($_POST['account_id']   ?? ''),
        'first_name'   => trim($_POST['first_name']   ?? ''),
        'last_name'    => trim($_POST['last_name']    ?? ''),
        'title'        => trim($_POST['title']        ?? ''),
    ];

    if ($entityType === 'account') {

        if ($input['account_name'] === '') {
            $errors[] = 'Account name is required.';
        }

        if (empty($errors)) {
            $repo->create([
                'account_name' => $input['account_name'],
                'email'        => $input['email'],
                'phone'        => $input['phone'],
                'address'      => $input['address'],
                'industry'     => $input['industry'],
                'source'       => $input['source'],
                'tags'         => $input['tags'],
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Account \"{$input['account_name']}\" created."];
            header('Location: accounts.php');
            exit;
        }

    } elseif ($entityType === 'contact') {

        // Cardinality: a contact cannot exist without an account.
        if ($input['account_id'] === '') {
            $errors[] = 'Please select the account this contact belongs to.';
        }
        if ($input['first_name'] === '') {
            $errors[] = 'First name is required.';
        }
        if ($input['last_name'] === '') {
            $errors[] = 'Last name is required.';
        }

        if (empty($errors)) {
            $repo->createContact([
                'account_id' => (int)$input['account_id'],
                'first_name' => $input['first_name'],
                'last_name'  => $input['last_name'],
                'email'      => $input['email'],
                'phone'      => $input['phone'],
                'title'      => $input['title'],
            ]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => "Contact \"{$input['first_name']} {$input['last_name']}\" added."];
            header('Location: account_detail.php?id=' . (int)$input['account_id']);
            exit;
        }

    } else {
        $errors[] = 'Please choose whether to add an Account or a Contact.';
    }
}

layout_open();
include __DIR__ . '/../../../app/Modules/Customer/views/create_account.php';
layout_close();
