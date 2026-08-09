<?php
$campaign = $campaign ?? null;
$audience = $audience ?? [];

if ($campaign === null) {
    echo '<section class="card"><h1>Campaign Not Found</h1><p class="text-muted">No campaign with that ID.</p></section>';
    return;
}

$statusBadge = [
    'Draft'     => 'badge-neutral',
    'Scheduled' => 'badge-info',
    'Sent'      => 'badge-quoted',
    'Completed' => 'badge-success',
];
$typeBadge = [
    'Email'          => 'badge-info',
    'SMS Simulation' => 'badge-warning',
];
?>

<!-- ── Header ─────────────────────────────────────────── -->
<section class="card">
    <div class="page-header">
        <div class="detail-title-row">
            <h1><?= htmlspecialchars($campaign['campaign_name']) ?></h1>
            <span class="badge <?= $statusBadge[$campaign['status']] ?? 'badge-neutral' ?> detail-stage">
                <?= htmlspecialchars($campaign['status']) ?>
            </span>
            <span class="badge <?= $typeBadge[$campaign['campaign_type']] ?? 'badge-neutral' ?>">
                <?= htmlspecialchars($campaign['campaign_type']) ?>
            </span>
        </div>
        <div class="header-actions">
            <a href="/modules/campaign/campaign_pdf.php?id=<?= (int)$campaign['id'] ?>" class="btn btn-secondary" style="font-size:0.85rem;padding:6px 14px;" title="Download this campaign, its metrics &amp; audience as a PDF">Download PDF</a>
            <a href="/modules/campaign/edit.php?id=<?= (int)$campaign['id'] ?>" class="btn btn-primary" style="font-size:0.85rem;padding:6px 14px;">Edit</a>
            <a href="/modules/campaign/audience.php?campaign_id=<?= (int)$campaign['id'] ?>" class="btn btn-secondary" style="font-size:0.85rem;padding:6px 14px;">Audience</a>
            <form method="POST" action="/modules/campaign/detail.php?id=<?= (int)$campaign['id'] ?>" style="margin:0;"
                  onsubmit="return confirm('Delete this campaign? This cannot be undone.');">
                <?= App\Core\Csrf::field() ?>
                <input type="hidden" name="_action" value="delete">
                <button type="submit" class="btn btn-danger" style="font-size:0.85rem;padding:6px 14px;">Delete</button>
            </form>
            <a href="/modules/campaign/campaigns.php" class="btn btn-secondary">&#8592; Back</a>
        </div>
    </div>

    <!-- Core info grid -->
    <div class="detail-grid">

        <div class="detail-section">
            <h3 class="detail-section-title">Created By</h3>
            <p class="detail-value"><?= htmlspecialchars($campaign['created_by_name'] ?? '—') ?></p>
        </div>

        <div class="detail-section">
            <h3 class="detail-section-title">Created At</h3>
            <p class="detail-value"><?= date('M j, Y', strtotime($campaign['created_at'])) ?></p>
            <p class="detail-meta"><?= date('g:i a', strtotime($campaign['created_at'])) ?></p>
        </div>

        <div class="detail-section">
            <h3 class="detail-section-title">Last Updated</h3>
            <p class="detail-value"><?= date('M j, Y', strtotime($campaign['updated_at'])) ?></p>
            <p class="detail-meta"><?= date('g:i a', strtotime($campaign['updated_at'])) ?></p>
        </div>

        <?php if ($campaign['scheduled_at'] !== null): ?>
        <div class="detail-section">
            <h3 class="detail-section-title">Scheduled For</h3>
            <p class="detail-value"><?= date('M j, Y', strtotime($campaign['scheduled_at'])) ?></p>
            <p class="detail-meta"><?= date('g:i a', strtotime($campaign['scheduled_at'])) ?></p>
        </div>
        <?php endif; ?>

    </div>
</section>

<!-- ── Metrics ─────────────────────────────────────────── -->
<section class="card">
    <div class="page-header">
        <h2 class="detail-card-title">Performance Metrics</h2>
        <?php if ($campaign['status'] !== 'Sent' && $campaign['status'] !== 'Completed'): ?>
        <form method="POST" action="/modules/campaign/detail.php?id=<?= (int)$campaign['id'] ?>" style="margin:0;">
            <?= App\Core\Csrf::field() ?>
            <input type="hidden" name="_action" value="simulate">
            <button type="submit" class="btn btn-primary" style="font-size:0.85rem;padding:6px 14px;">
                Simulate Send
            </button>
        </form>
        <?php endif; ?>
    </div>

    <div class="metrics-grid">

        <div class="metric-card">
            <div class="metric-card-inner">
                <p class="metric-label">Sent</p>
                <p class="metric-value"><?= number_format((int)$campaign['sent_count']) ?></p>
                <p class="metric-sub">recipients</p>
            </div>
        </div>

    </div>

    <?php if ($campaign['sent_count'] === 0 || $campaign['sent_count'] === '0'): ?>
        <p class="text-muted">Campaign has not been sent yet. Metrics will appear once the campaign is sent.</p>
    <?php endif; ?>
</section>

<!-- ── Audience ────────────────────────────────────────── -->
<section class="card">
    <div class="page-header">
        <h2 class="detail-card-title">Audience</h2>
        <a href="/modules/campaign/audience.php?campaign_id=<?= (int)$campaign['id'] ?>" class="btn btn-primary" title="Add audience segment">+ Add Segment</a>
    </div>

    <?php if (empty($audience)): ?>
        <p class="text-muted">No audience segments defined. <a href="/modules/campaign/audience.php?campaign_id=<?= (int)$campaign['id'] ?>">Add audience →</a></p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Segment</th>
                <th>Tag Filter</th>
                <th>Account</th>
                <th>Contact</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($audience as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['segment_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['tag_filter']   ?? '—') ?></td>
                <td><?= htmlspecialchars($row['account_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['contact_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>
