<?php
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use App\Core\Auth;
use App\Core\Permissions;
use App\Modules\Customer\CustomerRepository;

Auth::requireLogin();

if (!Permissions::can('customers.view')) {
    layout_deny();
    exit;
}

// List is now a client-driven DataTable (server-side processing). This page just
// renders the shell; rows come from accounts_data.php. We only need the distinct
// industry/source values to populate the per-column filter dropdowns.
$repo       = new CustomerRepository();
$industries = $repo->distinctValues('industry');
$sources    = $repo->distinctValues('source');

layout_open();
include __DIR__ . '/../../../app/Modules/Customer/views/accounts_list.php';
layout_close();
