<?php
/**
 * Dashboard view. Renders the permission-filtered cards passed in by
 * DashboardController::index() as $sections — a map of
 * "Section heading" => DashboardCard[].
 *
 * Each domain (RFQ, Campaigns, Customers, Inventory) is its own self-contained
 * section with a heading, so a user with several roles can see where one
 * module's cards end and the next begins. Empty sections are already dropped by
 * the controller, so we never render a bare heading.
 */
$sections = $sections ?? [];
?>
<section class="dashboard">
    <h1>Dashboard</h1>

    <?php if (empty($sections)): ?>
        <p class="text-muted">No dashboard cards are available for your role.</p>
    <?php else: ?>
        <?php foreach ($sections as $group => $cards): ?>
        <section class="dash-section">
            <h2 class="dash-section-title"><?= htmlspecialchars($group) ?></h2>
            <div class="dash-grid">
                <?php foreach ($cards as $card): ?>
                    <?= $card->render() ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
