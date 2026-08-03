<div class="app-shell">
    <aside class="app-sidebar" id="app-sidebar">
        <div class="app-sidebar-top">
            <div class="app-brand">Typhon CRM</div>
            <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">&#8249;</button>
        </div>
        <nav class="app-nav">
            <?php
            // Only advertise what this user can actually open. The endpoints
            // enforce these same permissions themselves — this just stops the
            // nav from handing every role a row of links that 403.
            $navItems = [
                ['/dashboard.php',                   'Dashboard',    'dashboard.view'],
                ['/modules/customer/accounts.php',   'Customers',    'customers.view'],
                ['/modules/rfq/pipeline.php',        'RFQ Pipeline', 'rfqs.view'],
                ['/modules/campaign/campaigns.php',  'Campaigns',    'campaigns.view'],
                ['/modules/inventory/products.php',  'Inventory',    'inventory.view'],
                ['/admin/users.php',                 'Admin',        'admin.manage_users'],
            ];
            foreach ($navItems as [$href, $label, $permission]) {
                if (!\App\Core\Permissions::can($permission)) {
                    continue;
                }
                printf('<a href="%s">%s</a>', htmlspecialchars($href), htmlspecialchars($label));
            }
            ?>
            <!-- Logout is a POST: as a GET link any page could log the user out
                 with an <img src>, and it carried no CSRF token. -->
            <form method="post" action="/logout.php" class="app-nav-logout">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="app-nav-logout-btn">Logout</button>
            </form>
        </nav>
    </aside>
    <main class="app-main">
        <button class="sidebar-open-btn" id="sidebar-open-btn" aria-label="Open sidebar">&#9776;</button>

        <?php if (!empty($_SESSION['flash'])): ?>
        <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
        <div class="flash-banner flash-banner--<?= htmlspecialchars($flash['type']) ?>" role="alert" id="flash-banner">
            <span><?= htmlspecialchars($flash['message']) ?></span>
            <button class="flash-banner-close" onclick="this.parentElement.remove()" aria-label="Dismiss">&times;</button>
        </div>
        <?php endif; ?>
