<?php
// Badge color per reservation status, matching the RFQ module's badge styling.
$statusBadge = [
    'Reserved'  => 'rfq-badge-warning',
    'Released'  => 'rfq-badge-neutral',
    'Converted' => 'rfq-badge-success',
];
?>

<section class="card">
    <div class="page-header">
        <h1>Inventory Reservations</h1>
        <a href="/modules/inventory/products.php" class="btn rfq-list-clear-btn">&#8592; Back to Inventory</a>
    </div>

    <table class="table rfq-list-table" style="margin-top:1rem;">
        <thead>
            <tr>
                <th>#</th>
                <th>RFQ</th>
                <th>Product</th>
                <th>SKU</th>
                <th>Qty Reserved</th>
                <th>Available</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reservations)): ?>
                <tr>
                    <td colspan="9" class="rfq-list-empty text-muted">No reservations found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reservations as $r):
                    $badge = $statusBadge[$r['reservation_status']] ?? 'rfq-badge-neutral';
                ?>
                <tr>
                    <td class="rfq-list-id">#<?= (int)$r['id'] ?></td>
                    <td>
                        <a href="/modules/rfq/detail.php?id=<?= (int)$r['rfq_id'] ?>" class="rfq-list-link">
                            <?= htmlspecialchars($r['rfq_title']) ?>
                        </a>
                        <span class="rfq-list-id"> #<?= (int)$r['rfq_id'] ?></span>
                    </td>
                    <td><?= htmlspecialchars($r['product_name']) ?></td>
                    <td class="rfq-list-id"><?= htmlspecialchars($r['sku']) ?></td>
                    <td><?= (int)$r['quantity_reserved'] ?></td>
                    <td><?= (int)($r['available_quantity'] ?? 0) ?></td>
                    <td><span class="rfq-badge <?= $badge ?>"><?= htmlspecialchars($r['reservation_status']) ?></span></td>
                    <td class="rfq-list-date"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ($r['reservation_status'] === 'Reserved'): ?>
                            <form method="POST" action="/modules/inventory/products.php?page=reservations" style="display:inline;">
                                <?= App\Core\Csrf::field() ?>
                                <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="convert">
                                <button type="submit" class="btn rfq-badge rfq-badge-success" style="border:none; cursor:pointer; font-size:0.8rem; padding:0.2rem 0.6rem;">Convert</button>
                            </form>
                            <form method="POST" action="/modules/inventory/products.php?page=reservations" style="display:inline; margin-left:4px;">
                                <?= App\Core\Csrf::field() ?>
                                <input type="hidden" name="reservation_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="action" value="release">
                                <button type="submit" class="btn rfq-badge rfq-badge-danger" style="border:none; cursor:pointer; font-size:0.8rem; padding:0.2rem 0.6rem;">Release</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="rfq-list-footer"><?= count($reservations ?? []) ?> reservation(s)</p>
</section>
