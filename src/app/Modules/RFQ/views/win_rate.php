<?php
/**
 * Win Rate by Account — paginated drill-down.
 *
 * The dashboard's "Win Rate by Account" card shows only the top few accounts and
 * links here for the full, paged breakdown. All aggregation is done in SQL by
 * RFQRepository::winRateByAccount(); this view only renders.
 *
 * Expects: $rows (page of accounts), $pager (App\Core\Paginator).
 */
$rows  = $rows  ?? [];
$pager = $pager ?? null;
?>
<section class="card">
    <div class="page-header">
        <h2>Win Rate by Account</h2>
        <a href="/modules/rfq/pipeline.php" class="btn btn-secondary">Back to RFQs</a>
    </div>

    <p class="text-muted">
        Win rate = won &divide; (won + lost) &times; 100. Open RFQs are excluded from the denominator.
    </p>

    <table class="table">
        <thead>
            <tr>
                <th>Account</th>
                <th>Total RFQs</th>
                <th>Won</th>
                <th>Lost</th>
                <th>Closed</th>
                <th>Win Rate</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-muted">No accounts with RFQs yet.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['account_name']) ?></td>
                <td><?= (int)$row['total_rfqs'] ?></td>
                <td><?= (int)$row['won'] ?></td>
                <td><?= (int)$row['lost'] ?></td>
                <td><?= (int)$row['closed'] ?></td>
                <td>
                    <?php if ($row['win_rate_pct'] !== null): ?>
                        <?php $pct = (float)$row['win_rate_pct']; ?>
                        <span class="rfq-badge <?= $pct >= 50 ? 'rfq-badge-success' : 'rfq-badge-danger' ?>">
                            <?= $pct ?>%
                        </span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
        if ($pager !== null) {
            $paginationClasses = [
                'container' => 'rfq-pagination',
                'item'      => 'rfq-page-btn',
                'nav'       => 'rfq-pagination-nav',
                'disabled'  => 'rfq-page-disabled',
                'active'    => 'rfq-page-active',
                'ellipsis'  => 'rfq-page-ellipsis',
            ];
            include __DIR__ . '/../../../Shared/pagination.php';
        }
    ?>
</section>
