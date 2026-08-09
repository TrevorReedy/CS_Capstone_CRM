<?php
// ─────────────────────────────────────────────────────────────────────────────
// NON-AUTHORITATIVE — reference only. DO NOT rely on this file at runtime.
//
// The live permission source of truth is the `role_permissions` DB table
// (seeded in database/seed.sql). Auth::attempt() loads a user's permissions
// from that table into $_SESSION, and Permissions::can() checks the session.
// Nothing in the app includes this file, so edits here have NO effect — they
// only drift from the database. To change a role's permissions, update
// `role_permissions` (or the Admin → Permission Matrix screen), not this array.
//
// Kept as a human-readable snapshot of the intended baseline. If it diverges
// from the DB, the DB wins.
// ─────────────────────────────────────────────────────────────────────────────
return [
    'Super Admin' => [
        '*',
    ],

    'Admin' => [
        'dashboard.view',
        'customers.view',
        'customers.create',
        'customers.edit',
        'customers.delete',
        'rfqs.view',
        'rfqs.create',
        'rfqs.edit',
        'rfqs.update_stage',
        'quotes.create',
        'quotes.edit',
        'campaigns.view',
        'campaigns.create',
        'campaigns.edit',
        'inventory.view',
        'inventory.create',
        'inventory.edit',
        'inventory.update_stock',
        'reports.view',
    ],

    'Sales User' => [
        'dashboard.view',
        'customers.view',
        'customers.create',
        'customers.edit',
        'interactions.create',
        'rfqs.view',
        'rfqs.create',
        'rfqs.edit',
        'rfqs.update_stage',
        'quotes.create',
        'quotes.edit',
        'inventory.view',
        'reports.view',
    ],

    'Marketing User' => [
        'dashboard.view',
        'customers.view',
        'campaigns.view',
        'campaigns.create',
        'campaigns.edit',
        'campaigns.metrics',
        'reports.view',
    ],

    'Inventory Manager' => [
        'dashboard.view',
        'inventory.view',
        'inventory.create',
        'inventory.edit',
        'inventory.update_stock',
        'inventory.reserve',
        'rfqs.view',
        'reports.view',
    ],
];