<section class="card">

    <div class="page-header">
        <h1>Edit Reservation</h1>
        <a href="/modules/rfq/edit.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-secondary">&#8592; Back</a>
    </div>

    <p class="text-muted">
        RFQ: <strong><?= htmlspecialchars($rfq['title']) ?></strong>
    </p>

    <?php if (!empty($errors)): ?>
    <div class="form-errors">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="module-form">
        <?= App\Core\Csrf::field() ?>
        <input type="hidden" name="rfq_id" value="<?= (int)$rfq['id'] ?>">

        <!-- Product: read-only -->
        <div class="form-group">
            <label class="form-label">Product</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($reservation['product_name']) ?>" readonly>
        </div>

        <!-- Product info card -->
        <div class="res-product-card">
            <div class="res-product-card-row">
                <span>
                    <span class="text-muted res-product-card-field-label">SKU</span>&nbsp;
                    <strong><?= htmlspecialchars($reservation['sku']) ?></strong>
                </span>
                <span>
                    <span class="text-muted res-product-card-field-label">Unit Price</span>&nbsp;
                    <strong>$<?= number_format((float)$reservation['price'], 2) ?></strong>
                </span>
                <span>
                    <span class="text-muted res-product-card-field-label">Available</span>&nbsp;
                    <?php $maxQty = (int)$reservation['available_quantity'] + (int)$reservation['quantity_reserved']; ?>
                    <strong><?= $maxQty ?> unit<?= $maxQty !== 1 ? 's' : '' ?></strong>
                </span>
            </div>
        </div>

        <!-- Quantity -->
        <div class="form-group">
            <label for="res-qty" class="form-label">Quantity <span class="form-required">*</span></label>
            <input
                type="number"
                id="res-qty"
                name="quantity_reserved"
                value="<?= (int)$input['quantity_reserved'] ?>"
                min="1"
                max="<?= $maxQty ?>"
                step="1"
                class="form-control res-qty-sm"
                required
            >
            <p id="res-total-line" class="text-muted res-total-line" style="display:none;"></p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="/modules/rfq/edit.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</section>

<script>
(function () {
    const qtyInput  = document.getElementById('res-qty');
    const totalLine = document.getElementById('res-total-line');
    const price     = <?= (float)$reservation['price'] ?>;
    const sku       = <?= json_encode($reservation['sku']) ?>;

    function updateTotal() {
        if (!qtyInput.value) { totalLine.style.display = 'none'; return; }
        const total = price * parseInt(qtyInput.value, 10);
        totalLine.textContent = sku + '  ×  ' + qtyInput.value + '  ×  $' + price.toFixed(2) + '  =  $' + total.toFixed(2);
        totalLine.style.display = '';
    }

    qtyInput.addEventListener('input', updateTotal);
    updateTotal();
}());
</script>