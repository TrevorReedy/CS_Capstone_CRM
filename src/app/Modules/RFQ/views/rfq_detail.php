<?php
$catalogValue = array_sum(array_map(
    fn($r) => (float)$r['price'] * (int)$r['quantity_reserved'],
    $reservations
));

$stageBadge = [
    'New'         => 'rfq-badge-neutral',
    'In Review'   => 'rfq-badge-info',
    'Quoted'      => 'rfq-badge-quoted',
    'Negotiation' => 'rfq-badge-warning',
    'Won'         => 'rfq-badge-success',
    'Lost'        => 'rfq-badge-danger',
];
$allStages = ['New', 'In Review', 'Quoted', 'Negotiation', 'Won', 'Lost'];
?>

<!-- ── Header ─────────────────────────────────────────── -->
<section class="card">
    <div class="page-header">
        <div class="rfq-detail-title-row">
            <h1><?= htmlspecialchars($rfq['title']) ?></h1>

            <!-- Quick stage changer -->
            <form method="POST" action="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" class="rfq-stage-form">
                <?= App\Core\Csrf::field() ?>
                <input type="hidden" name="_action" value="stage">
                <select
                    name="stage"
                    class="rfq-stage-select rfq-badge <?= $stageBadge[$rfq['stage']] ?? '' ?>"
                    onchange="this.form.submit()"
                    title="Change stage"
                >
                    <?php foreach ($allStages as $s): ?>
                    <option value="<?= $s ?>" <?= $rfq['stage'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <div class="header-actions">
            <a href="/modules/rfq/rfq_pdf.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-secondary" style="font-size:0.85rem;padding:6px 14px;" title="Download this RFQ and its quotes &amp; reservations as a PDF">Download PDF</a>
            <a href="/modules/rfq/edit.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-primary" style="font-size:0.85rem;padding:6px 14px;">Edit</a>
            <form method="POST" action="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" style="margin:0;"
                  onsubmit="return confirm('Delete this RFQ and all its quotes and reservations? This cannot be undone.');">
                <?= App\Core\Csrf::field() ?>
                <input type="hidden" name="_action" value="delete">
                <button type="submit" class="btn btn-danger" style="font-size:0.85rem;padding:6px 14px;">Delete</button>
            </form>
            <a href="/modules/rfq/pipeline.php" class="btn btn-secondary">&#8592; Back</a>
        </div>
    </div>

    <!-- ── Core info grid ─────────────────────────────── -->
    <div class="rfq-detail-grid">

        <div class="rfq-detail-section">
            <h3 class="rfq-detail-section-title">Account</h3>
            <?php if ($rfq['account_id']): ?>
                <p class="rfq-detail-value">
                    <a href="/modules/customer/account_detail.php?id=<?= (int)$rfq['account_id'] ?>">
                        <?= htmlspecialchars($rfq['account_name']) ?>
                    </a>
                </p>
                <?php if ($rfq['account_email']): ?>
                    <p class="rfq-detail-meta"><?= htmlspecialchars($rfq['account_email']) ?></p>
                <?php endif; ?>
                <?php if ($rfq['account_phone']): ?>
                    <p class="rfq-detail-meta"><?= htmlspecialchars($rfq['account_phone']) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted">—</p>
            <?php endif; ?>
        </div>

        <div class="rfq-detail-section">
            <h3 class="rfq-detail-section-title">Contact</h3>
            <?php if ($rfq['contact_id'] && trim($rfq['contact_name']) !== ''): ?>
                <p class="rfq-detail-value">
                    <a href="/modules/customer/account_detail.php?id=<?= (int)$rfq['contact_account_id'] ?>">
                        <?= htmlspecialchars($rfq['contact_name']) ?>
                    </a>
                </p>
                <?php if ($rfq['contact_title']): ?>
                    <p class="rfq-detail-meta"><?= htmlspecialchars($rfq['contact_title']) ?></p>
                <?php endif; ?>
                <?php if ($rfq['contact_email']): ?>
                    <p class="rfq-detail-meta"><?= htmlspecialchars($rfq['contact_email']) ?></p>
                <?php endif; ?>
                <?php if ($rfq['contact_phone']): ?>
                    <p class="rfq-detail-meta"><?= htmlspecialchars($rfq['contact_phone']) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="text-muted">—</p>
            <?php endif; ?>
        </div>

        <div class="rfq-detail-section">
            <h3 class="rfq-detail-section-title">Created By</h3>
            <p class="rfq-detail-value"><?= htmlspecialchars($rfq['created_by_name']) ?></p>
            <p class="rfq-detail-meta"><?= date('M j, Y g:i a', strtotime($rfq['created_at'])) ?></p>
        </div>

        <div class="rfq-detail-section">
            <h3 class="rfq-detail-section-title">Last Updated</h3>
            <p class="rfq-detail-value"><?= date('M j, Y', strtotime($rfq['updated_at'])) ?></p>
            <p class="rfq-detail-meta"><?= date('g:i a', strtotime($rfq['updated_at'])) ?></p>
        </div>

    </div>

    <?php if ($rfq['description'] !== '' && $rfq['description'] !== null): ?>
    <div class="rfq-detail-description">
        <h3 class="rfq-detail-section-title">Description</h3>
        <p><?= nl2br(htmlspecialchars($rfq['description'])) ?></p>
    </div>
    <?php endif; ?>
</section>

<!-- ── Quotes ─────────────────────────────────────────── -->
<section class="card">
    <?php
        $quoteRequired = in_array($rfq['stage'], ['Quoted', 'Negotiation', 'Won', 'Lost'], true);
        $addQuoteTitle = $quoteRequired ? 'Add a quote' : 'Quotes are not required for ' . $rfq['stage'] . ' stage';
    ?>
    <div class="page-header">
        <h2 class="rfq-detail-card-title">Quotes</h2>
        <a
            href="<?= $quoteRequired ? '/modules/rfq/create_quote.php?rfq_id=' . (int)$rfq['id'] : '#' ?>"
            class="rfq-create-btn<?= $quoteRequired ? '' : ' rfq-create-btn--wip' ?>"
            title="<?= htmlspecialchars($addQuoteTitle) ?>"
            <?= $quoteRequired ? '' : 'aria-disabled="true" tabindex="-1"' ?>
        >+</a>
    </div>

    <?php if (empty($quotes)): ?>
        <p class="text-muted">No quotes attached to this RFQ.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Amount</th>
                <th>Discount</th>
                <th>Net</th>
                <?php if ($catalogValue > 0): ?><th title="Net quote minus catalog value of reserved inventory">Variance</th><?php endif; ?>
                <th>Valid From</th>
                <th>Valid To</th>
                <th>Status</th>
                <th style="width:90px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($quotes as $i => $q): ?>
            <?php
                $days = (int)$q['days_remaining'];
                if ($rfq['stage'] === 'Won') {
                    $qBadge = 'rfq-badge-success'; $qLabel = 'Accepted';
                } elseif ($rfq['stage'] === 'Lost') {
                    $qBadge = 'rfq-badge-neutral'; $qLabel = 'Declined';
                } elseif ($days < 0)      { $qBadge = 'rfq-badge-danger';  $qLabel = abs($days) . 'd overdue'; }
                elseif ($days <= 7)       { $qBadge = 'rfq-badge-warning'; $qLabel = $days . 'd left'; }
                else                      { $qBadge = 'rfq-badge-success'; $qLabel = $days . 'd left'; }
                $net      = (float)$q['quote_amount'] - (float)$q['discount'];
                $variance = $net - $catalogValue;
            ?>
            <tr>
                <td class="text-muted"><?= $i + 1 ?></td>
                <td>$<?= number_format((float)$q['quote_amount'], 2) ?></td>
                <td><?= (float)$q['discount'] > 0 ? '-$' . number_format((float)$q['discount'], 2) : '—' ?></td>
                <td><strong>$<?= number_format($net, 2) ?></strong></td>
                <?php if ($catalogValue > 0): ?>
                <?php
                    if ($variance > 0)       { $vBadge = 'rfq-badge-info';    $vPrefix = '+'; }
                    elseif ($variance < 0)   { $vBadge = 'rfq-badge-warning'; $vPrefix = '-'; }
                    else                     { $vBadge = 'rfq-badge-neutral';  $vPrefix = ''; }
                    $vPct = $catalogValue != 0 ? round(abs($variance) / $catalogValue * 100, 1) : 0;
                ?>
                <td>
                    <span class="rfq-badge <?= $vBadge ?>" title="Catalog value: $<?= number_format($catalogValue, 2) ?>">
                        <?= $vPrefix ?>$<?= number_format(abs($variance), 2) ?>
                        <span class="rfq-badge-pct">(<?= $vPrefix ?><?= $vPct ?>%)</span>
                    </span>
                </td>
                <?php endif; ?>
                <td class="text-muted"><?= $q['validity_start_date'] ? date('M j, Y', strtotime($q['validity_start_date'])) : '—' ?></td>
                <td class="text-muted"><?= $q['validity_end_date']   ? date('M j, Y', strtotime($q['validity_end_date']))   : '—' ?></td>
                <td><span class="rfq-badge <?= $qBadge ?>"><?= $qLabel ?></span></td>
                <td style="display:flex;gap:4px;align-items:center;">
                    <a href="/modules/rfq/edit_quote.php?id=<?= (int)$q['id'] ?>"
                       class="btn btn-secondary" style="font-size:0.78rem;padding:3px 8px;">Edit</a>
                    <form method="POST" action="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" style="display:inline;"
                          onsubmit="return confirm('Delete this quote?');">
                        <?= App\Core\Csrf::field() ?>
                        <input type="hidden" name="_action"  value="delete_quote">
                        <input type="hidden" name="quote_id" value="<?= (int)$q['id'] ?>">
                        <input type="hidden" name="rfq_id"   value="<?= (int)$rfq['id'] ?>">
                        <button type="submit" class="rfq-res-remove-btn" title="Delete quote">&times;</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<!-- ── Inventory Reservations ────────────────────────── -->
<section class="card">
    <?php
        $addResTitle = $quoteRequired ? 'Reserve inventory for this RFQ' : 'Inventory reservation not available for ' . $rfq['stage'] . ' stage';
    ?>
    <div class="page-header">
        <h2 class="rfq-detail-card-title">Inventory Reservations</h2>
        <a
            href="<?= $quoteRequired ? '/modules/rfq/create_reservation.php?rfq_id=' . (int)$rfq['id'] : '#' ?>"
            class="rfq-create-btn<?= $quoteRequired ? '' : ' rfq-create-btn--wip' ?>"
            title="<?= htmlspecialchars($addResTitle) ?>"
            <?= $quoteRequired ? '' : 'aria-disabled="true" tabindex="-1"' ?>
        >+</a>
    </div>

    <?php if (empty($reservations)): ?>
        <p class="text-muted">No inventory reserved for this RFQ.</p>
    <?php else: ?>
    <table class="table">
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Unit Price</th>
                <th>Qty Reserved</th>
                <th>Total</th>
                <th>Status</th>
                <th>Reserved On</th>
                <th style="width:90px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reservations as $res): ?>
            <?php
                $resBadge = match($res['reservation_status']) {
                    'Reserved'  => 'rfq-badge-info',
                    'Released'  => 'rfq-badge-neutral',
                    'Converted' => 'rfq-badge-success',
                    default     => '',
                };
            ?>
            <tr>
                <td><?= htmlspecialchars($res['product_name']) ?></td>
                <td class="text-muted"><?= htmlspecialchars($res['sku']) ?></td>
                <td>$<?= number_format((float)$res['price'], 2) ?></td>
                <td><?= (int)$res['quantity_reserved'] ?></td>
                <td><strong>$<?= number_format((float)$res['price'] * (int)$res['quantity_reserved'], 2) ?></strong></td>
                <td>
                    <?php if ($res['reservation_status'] === 'Reserved'): ?>
                    <form method="POST" action="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" style="margin:0;">
                        <?= App\Core\Csrf::field() ?>
                        <input type="hidden" name="_action"        value="update_reservation_status">
                        <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                        <input type="hidden" name="rfq_id"         value="<?= (int)$rfq['id'] ?>">
                        <select name="reservation_status" class="rfq-stage-select rfq-badge rfq-badge-info"
                                onchange="this.form.submit()" title="Change reservation status">
                            <option value="Reserved"  selected>Reserved</option>
                            <option value="Released">Released</option>
                            <option value="Converted">Converted</option>
                        </select>
                    </form>
                    <?php else: ?>
                        <span class="rfq-badge <?= $resBadge ?>"><?= htmlspecialchars($res['reservation_status']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= date('M j, Y', strtotime($res['created_at'])) ?></td>
                <td style="display:flex;gap:4px;align-items:center;">
                    <?php if ($res['reservation_status'] === 'Reserved'): ?>
                    <a href="/modules/rfq/edit_reservation.php?id=<?= (int)$res['id'] ?>"
                       class="btn btn-secondary" style="font-size:0.78rem;padding:3px 8px;">Edit</a>
                    <?php endif; ?>
                    <form method="POST" action="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" style="display:inline;"
                          onsubmit="return confirm('Remove this reservation?<?= $res['reservation_status'] === 'Reserved' ? ' Stock will be returned to inventory.' : '' ?>');">
                        <?= App\Core\Csrf::field() ?>
                        <input type="hidden" name="_action"        value="delete_reservation">
                        <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                        <input type="hidden" name="rfq_id"         value="<?= (int)$rfq['id'] ?>">
                        <input type="hidden" name="redirect_to"    value="detail">
                        <button type="submit" class="rfq-res-remove-btn" title="Remove reservation">&times;</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="table-footer-label">Catalog Value</td>
                <td class="table-footer-value"><strong>$<?= number_format($catalogValue, 2) ?></strong></td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</section>
