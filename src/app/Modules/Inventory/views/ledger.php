<?php
$movementBadge = [
    'created'           => 'rfq-badge-success',
    'updated'           => 'rfq-badge-neutral',
    'manual_adjustment' => 'rfq-badge-warning',
    'reserved'          => 'rfq-badge-quoted',
    'released'          => 'rfq-badge-info',
    'converted'         => 'rfq-badge-success',
    'deleted'           => 'rfq-badge-danger',
];
$movementLabel = [
    'created'           => 'Created',
    'updated'           => 'Updated',
    'manual_adjustment' => 'Manual Adjustment',
    'reserved'          => 'Reserved',
    'released'          => 'Released',
    'converted'         => 'Converted',
    'deleted'           => 'Deleted',
];

function ledgerSortUrl(string $col, string $cur, string $dir, string $q, array $types, ?int $productId, string $perPage): string
{
    $nextDir = ($cur === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
    $params  = array_filter(['q' => $q, 'sort' => $col, 'dir' => $nextDir], fn($v) => $v !== '');
    if ($productId !== null) $params['product_id'] = $productId;
    if (!empty($types)) $params['type'] = $types;
    if ($perPage !== '25') $params['per_page'] = $perPage;
    return '?' . http_build_query($params);
}

function ledgerSortIcon(string $col, string $cur, string $dir): string
{
    if ($cur !== $col) return '<span class="rfq-sort-icon rfq-sort-idle">↕</span>';
    return $dir === 'ASC'
        ? '<span class="rfq-sort-icon rfq-sort-asc">▲</span>'
        : '<span class="rfq-sort-icon rfq-sort-desc">▼</span>';
}

function ledgerQtyDisplay(?int $delta): string
{
    if ($delta === null) return '<span class="text-muted">—</span>';
    if ($delta > 0) return '<span style="color:#166534;">+' . $delta . '</span>';
    if ($delta < 0) return '<span style="color:#b91c1c;">' . $delta . '</span>';
    return '0';
}

$sortCols = [
    'created_at'     => 'Date/Time',
    'product_name'   => 'Product',
    'movement_type'  => 'Event',
    'quantity_delta' => 'Qty Δ',
    'user_name'      => 'User',
];

// Carries the current filters into the print link / cleared-filter link.
$printParams = array_filter([
    'q'    => $ledgerSearch,
    'sort' => $ledgerSort,
    'dir'  => $ledgerDir,
], fn($v) => $v !== '');
if ($ledgerProductId !== null) $printParams['product_id'] = $ledgerProductId;
if (!empty($ledgerTypes)) $printParams['type'] = $ledgerTypes;
?>

<section class="card">
    <div class="page-header">
        <h1>Inventory Ledger</h1>
        <div style="display:flex; gap:0.5rem;">
            <a href="/modules/inventory/products.php?page=ledger_print&<?= http_build_query($printParams) ?>" class="btn" target="_blank" rel="noopener">🖨 Print</a>
            <a href="/modules/inventory/products.php" class="btn rfq-list-clear-btn">&#8592; Back to Inventory</a>
        </div>
    </div>

    <?php if ($product !== null): ?>
        <p class="text-muted" style="margin-top:-0.5rem;">
            Showing history for <strong><?= htmlspecialchars($product['product_name']) ?></strong> (<?= htmlspecialchars($product['sku']) ?>).
            <a href="/modules/inventory/products.php?page=ledger">View all products</a>
        </p>
    <?php endif; ?>

    <!-- Search / filter toolbar -->
    <form method="GET" class="rfq-list-toolbar" style="flex-direction:row; flex-wrap:wrap; align-items:center; gap:0.5rem; margin-bottom:1rem;">
        <input type="hidden" name="page" value="ledger">
        <input type="hidden" name="sort" value="<?= htmlspecialchars($ledgerSort) ?>">
        <input type="hidden" name="dir" value="<?= htmlspecialchars($ledgerDir) ?>">
        <?php if ($ledgerProductId !== null): ?>
            <input type="hidden" name="product_id" value="<?= (int)$ledgerProductId ?>">
        <?php endif; ?>
        <input
            type="text"
            name="q"
            class="form-control rfq-list-search-input"
            placeholder="Search product, SKU, user, or note"
            value="<?= htmlspecialchars($ledgerSearch) ?>"
            style="max-width:280px; margin-bottom:0;"
        >

        <!-- Column filter on Event (movement_type) -->
        <div class="rfq-stage-checks">
            <?php foreach ($movementTypes as $t): ?>
            <label class="rfq-stage-check">
                <input type="checkbox" name="type[]" value="<?= htmlspecialchars($t) ?>"
                    <?= in_array($t, $ledgerTypes, true) ? 'checked' : '' ?>>
                <?= htmlspecialchars($movementLabel[$t] ?? $t) ?>
            </label>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn">Filter</button>
        <?php if ($ledgerSearch !== '' || !empty($ledgerTypes)): ?>
            <a href="/modules/inventory/products.php?page=ledger<?= $ledgerProductId !== null ? '&product_id=' . (int)$ledgerProductId : '' ?>" class="btn rfq-list-clear-btn">Clear</a>
        <?php endif; ?>
        <?php
            $perPageClass = 'form-control rfq-list-perpage-select';
            $perPageAutoSubmit = true;
            include __DIR__ . '/../../../Shared/per_page_select.php';
        ?>
    </form>

    <table class="table rfq-list-table">
        <thead>
            <tr>
                <?php foreach ($sortCols as $key => $label): ?>
                <th>
                    <a href="<?= ledgerSortUrl($key, $ledgerSort, $ledgerDir, $ledgerSearch, $ledgerTypes, $ledgerProductId, (string)$pager->perPageValue) ?>" class="rfq-sort-link">
                        <?= $label ?>
                        <?= ledgerSortIcon($key, $ledgerSort, $ledgerDir) ?>
                    </a>
                </th>
                <?php endforeach; ?>
                <th>Available / Reserved After</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="7" class="rfq-list-empty text-muted">No ledger entries found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($movements as $m): ?>
                <tr>
                    <td class="text-muted rfq-list-date"><?= date('M j, Y g:i A', strtotime($m['created_at'])) ?></td>
                    <td>
                        <?php if ($m['product_id'] !== null): ?>
                            <a href="/modules/inventory/products.php?page=detail&id=<?= (int)$m['product_id'] ?>" class="rfq-list-link"><?= htmlspecialchars($m['product_name']) ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars($m['product_name']) ?> <span class="text-muted">(deleted)</span>
                        <?php endif; ?>
                        <br><span class="text-muted" style="font-size:0.8rem;"><?= htmlspecialchars($m['sku']) ?></span>
                    </td>
                    <td><span class="rfq-badge <?= $movementBadge[$m['movement_type']] ?? 'rfq-badge-neutral' ?>"><?= htmlspecialchars($movementLabel[$m['movement_type']] ?? $m['movement_type']) ?></span></td>
                    <td><?= ledgerQtyDisplay($m['quantity_delta'] !== null ? (int)$m['quantity_delta'] : null) ?></td>
                    <td><?= htmlspecialchars($m['user_name'] ?? 'System') ?></td>
                    <td class="text-muted"><?= $m['available_quantity_after'] !== null ? (int)$m['available_quantity_after'] : '—' ?> / <?= $m['reserved_quantity_after'] !== null ? (int)$m['reserved_quantity_after'] : '—' ?></td>
                    <td class="text-muted"><?= htmlspecialchars($m['note'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
        $pageParam = 'p';
        $paginationClasses = [
            'container' => 'rfq-pagination',
            'item'      => 'rfq-page-btn',
            'nav'       => 'rfq-pagination-nav',
            'disabled'  => 'rfq-page-disabled',
            'active'    => 'rfq-page-active',
            'ellipsis'  => 'rfq-page-ellipsis',
        ];
        include __DIR__ . '/../../../Shared/pagination.php';
    ?>
    <div class="rfq-list-footer">
        Showing <?= $pager->from() ?>–<?= $pager->to() ?> of <?= number_format($total) ?> event<?= $total !== 1 ? 's' : '' ?>
    </div>
</section>
