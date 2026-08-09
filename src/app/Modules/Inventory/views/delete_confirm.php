<section class="card">
    <div class="page-header">
        <h1>Delete Product</h1>
        <a href="/modules/inventory/products.php?page=detail&id=<?= (int)$product['id'] ?>" class="btn rfq-list-clear-btn">&#8592; Cancel</a>
    </div>

    <div class="alert alert-danger" style="margin-top:1rem;">
        <strong>Are you sure you want to delete this product?</strong> This cannot be undone.
    </div>

    <!-- Product summary -->
    <div class="rfq-detail-grid" style="margin:1.25rem 0; grid-template-columns: repeat(auto-fit, minmax(140px,1fr));">
        <div class="rfq-detail-section">
            <h3 class="rfq-detail-section-title">Product Name</h3>
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
        <div class="rfq-detail-section">
            <h3 class="rfq-detail-section-title">Available Qty</h3>
            <p class="rfq-detail-value"><?= (int)$product['available_quantity'] ?></p>
        </div>
        <div class="rfq-detail-section">
            <h3 class="rfq-detail-section-title">Reserved Qty</h3>
            <p class="rfq-detail-value"><?= (int)$product['reserved_quantity'] ?></p>
        </div>
    </div>

    <?php if ((int)$product['reserved_quantity'] > 0): ?>
        <div class="alert alert-warning">
            This product has <strong><?= (int)$product['reserved_quantity'] ?></strong> unit(s) reserved by active RFQs.
            You must release or convert those reservations before deleting.
        </div>
    <?php endif; ?>

    <form method="POST" action="/modules/inventory/products.php?page=delete" class="rfq-form-actions" style="margin-top:1rem;">
        <?= App\Core\Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
        <button type="submit" class="btn" style="background:#b91c1c; color:#fff; border-color:#b91c1c;">
            Yes, Delete This Product
        </button>
        <a href="/modules/inventory/products.php?page=detail&id=<?= (int)$product['id'] ?>" class="btn rfq-list-clear-btn">Cancel</a>
    </form>
</section>
