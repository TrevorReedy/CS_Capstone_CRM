<?php
$isEdit = isset($product) && $product !== null;
$pageTitle = $isEdit ? 'Edit Product' : 'Add Product';
?>

<section class="card">
    <!-- Header row -->
    <div class="page-header">
        <h1><?= $pageTitle ?></h1>
        <a href="/modules/inventory/products.php" class="btn rfq-list-clear-btn">&#8592; Back to Inventory</a>
    </div>

    <form method="POST" action="/modules/inventory/products.php?page=detail" class="rfq-form">
        <?= App\Core\Csrf::field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
        <?php endif; ?>

        <!-- Row 1: Name + SKU -->
        <div class="rfq-form-row">
            <div class="rfq-form-group">
                <label class="rfq-form-label">Product Name <span class="rfq-form-required">*</span></label>
                <input
                    type="text"
                    name="product_name"
                    class="form-control"
                    value="<?= htmlspecialchars($product['product_name'] ?? '') ?>"
                    required
                    placeholder="e.g. Triple-Lumen Central Venous Catheter Kit"
                >
            </div>
            <div class="rfq-form-group">
                <label class="rfq-form-label">SKU <span class="rfq-form-required">*</span></label>
                <input
                    type="text"
                    name="sku"
                    class="form-control"
                    value="<?= htmlspecialchars($product['sku'] ?? '') ?>"
                    required
                    placeholder="e.g. CVC-3L-001"
                >
            </div>
        </div>

        <!-- Row 2: Price + Starting Qty (add mode only) -->
        <div class="rfq-form-row">
            <div class="rfq-form-group">
                <label class="rfq-form-label">Price ($) <span class="rfq-form-required">*</span></label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="price"
                    class="form-control"
                    value="<?= htmlspecialchars((string)($product['price'] ?? '0.00')) ?>"
                    required
                >
            </div>
            <?php if (!$isEdit): ?>
            <div class="rfq-form-group">
                <label class="rfq-form-label">Starting Available Quantity</label>
                <input
                    type="number"
                    min="0"
                    name="available_quantity"
                    class="form-control"
                    value="0"
                >
            </div>
            <?php endif; ?>
        </div>

        <!-- Row 3: Low Stock Threshold -->
        <div class="rfq-form-row">
            <div class="rfq-form-group">
                <label class="rfq-form-label">Low Stock Threshold <span class="rfq-form-required">*</span></label>
                <input
                    type="number"
                    min="0"
                    name="low_stock_threshold"
                    class="form-control"
                    value="<?= htmlspecialchars((string)($product['low_stock_threshold'] ?? \App\Modules\Inventory\InventoryService::DEFAULT_LOW_STOCK_THRESHOLD)) ?>"
                    required
                >
                <span class="text-muted" style="font-size:0.82rem;">Show a low stock warning once available quantity drops below this number</span>
            </div>
        </div>

        <!-- Description -->
        <div class="rfq-form-group">
            <label class="rfq-form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" placeholder="Optional product description"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
        </div>

        <!-- Actions -->
        <div class="rfq-form-actions">
            <button type="submit" class="btn btn-primary">Save Product</button>
            <a href="/modules/inventory/products.php" class="btn rfq-list-clear-btn">Cancel</a>
        </div>
    </form>

    <!-- Stock summary (edit mode only) -->
    <?php if ($isEdit): ?>
    <div style="margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid #e0e0e0;">
        <div class="rfq-detail-grid" style="grid-template-columns: repeat(auto-fit, minmax(140px,1fr));">
            <div class="rfq-detail-section">
                <h3 class="rfq-detail-section-title">Available Qty</h3>
                <p class="rfq-detail-value"><?= (int)$product['available_quantity'] ?></p>
            </div>
            <div class="rfq-detail-section">
                <h3 class="rfq-detail-section-title">Reserved Qty</h3>
                <p class="rfq-detail-value"><?= (int)$product['reserved_quantity'] ?></p>
            </div>
            <div class="rfq-detail-section">
                <h3 class="rfq-detail-section-title">Created</h3>
                <p class="rfq-detail-meta"><?= date('M j, Y', strtotime($product['created_at'] ?? 'now')) ?></p>
            </div>
            <div class="rfq-detail-section">
                <h3 class="rfq-detail-section-title">Last Updated</h3>
                <p class="rfq-detail-meta"><?= date('M j, Y', strtotime($product['updated_at'] ?? 'now')) ?></p>
            </div>
        </div>
        <div style="margin-top:1rem; display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="/modules/inventory/products.php?page=stock&id=<?= (int)$product['id'] ?>" class="btn btn-primary" style="font-size:0.9rem;">Update Stock Levels</a>
            <a href="/modules/inventory/products.php?page=ledger&product_id=<?= (int)$product['id'] ?>" class="btn rfq-list-clear-btn" style="font-size:0.9rem;">View History</a>
            <a href="/modules/inventory/products.php?page=reservations" class="btn rfq-list-clear-btn" style="font-size:0.9rem;">View Reservations</a>
            <a href="/modules/inventory/products.php?page=delete&id=<?= (int)$product['id'] ?>" class="btn" style="font-size:0.9rem; background:#fde8e8; color:#b91c1c; border-color:#fca5a5;">Delete Product</a>
        </div>
    </div>
    <?php endif; ?>
</section>
