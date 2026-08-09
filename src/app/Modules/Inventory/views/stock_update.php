<section class="card">
    <div class="page-header">
        <h1>Update Stock</h1>
        <a href="/modules/inventory/products.php" class="btn rfq-list-clear-btn">&#8592; Back to Inventory</a>
    </div>

    <?php if (empty($product)): ?>
        <div class="alert alert-danger">Product not found.</div>
    <?php else: ?>

        <!-- Product summary -->
        <div class="rfq-detail-grid" style="margin-bottom:1.5rem; grid-template-columns: repeat(auto-fit, minmax(140px,1fr));">
            <div class="rfq-detail-section">
                <h3 class="rfq-detail-section-title">Product</h3>
                <p class="rfq-detail-value"><?= htmlspecialchars($product['product_name']) ?></p>
            </div>
            <div class="rfq-detail-section">
                <h3 class="rfq-detail-section-title">SKU</h3>
                <p class="rfq-detail-value"><?= htmlspecialchars($product['sku']) ?></p>
            </div>
            <div class="rfq-detail-section">
                <h3 class="rfq-detail-section-title">Price</h3>
                <p class="rfq-detail-value">$<?= number_format((float)$product['price'], 2) ?></p>
            </div>
        </div>

        <form method="POST" action="/modules/inventory/products.php?page=stock" class="rfq-form">
            <?= App\Core\Csrf::field() ?>
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

            <div class="rfq-form-row">
                <div class="rfq-form-group">
                    <label class="rfq-form-label">Available Quantity <span class="rfq-form-required">*</span></label>
                    <input
                        type="number"
                        min="0"
                        name="available_quantity"
                        class="form-control"
                        value="<?= (int)$product['available_quantity'] ?>"
                        required
                    >
                    <span class="text-muted" style="font-size:0.82rem;">Units currently available for sale or reservation</span>
                </div>
                <div class="rfq-form-group">
                    <label class="rfq-form-label">Reserved Quantity</label>
                    <input
                        type="number"
                        class="form-control"
                        value="<?= (int)$product['reserved_quantity'] ?>"
                        disabled
                        readonly
                    >
                    <span class="text-muted" style="font-size:0.82rem;">Read-only — driven automatically by RFQ reservations. Reserve stock from an RFQ, not here.</span>
                </div>
            </div>

            <div class="rfq-form-actions">
                <button type="submit" class="btn btn-primary">Save Stock Levels</button>
                <a href="/modules/inventory/products.php?page=detail&id=<?= (int)$product['id'] ?>" class="btn rfq-list-clear-btn">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</section>
