<section class="card">

    <div class="page-header">
        <h1>Add Inventory Reservation</h1>
        <a href="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-secondary">&#8592; Back</a>
    </div>

    <p class="text-muted">
        RFQ: <strong><?= htmlspecialchars($rfq['title']) ?></strong>
        &mdash; <?= htmlspecialchars($rfq['account_name']) ?>
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

        <!-- Product -->
        <div class="form-group">
            <label for="res-product" class="form-label">Product <span class="form-required">*</span></label>
            <select id="res-product" name="product_id" class="form-control" required>
                <option value="">— Select product —</option>
                <?php foreach ($products as $product): ?>
                <option
                    value="<?= (int)$product['id'] ?>"
                    data-available="<?= (int)$product['available_quantity'] ?>"
                    data-price="<?= (float)$product['price'] ?>"
                    data-sku="<?= htmlspecialchars($product['sku']) ?>"
                    data-desc="<?= htmlspecialchars(mb_strimwidth($product['description'] ?? '', 0, 70, '…')) ?>"
                    <?= $input['product_id'] == $product['id'] ? 'selected' : '' ?>
                >
                    <?= htmlspecialchars($product['product_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Product detail card (populated by JS) -->
        <div id="res-product-info" class="res-product-card" style="display:none;">
            <div class="res-product-card-row">
                <span><span class="text-muted res-product-card-field-label">SKU</span>&nbsp;<strong id="res-info-sku"></strong></span>
                <span><span class="text-muted res-product-card-field-label">Unit Price</span>&nbsp;<strong id="res-info-price"></strong></span>
                <span><span class="text-muted res-product-card-field-label">Available</span>&nbsp;<strong id="res-info-avail"></strong></span>
            </div>
            <p id="res-info-desc" class="text-muted res-product-card-desc"></p>
        </div>

        <!-- Quantity -->
        <div class="form-group">
            <label for="res-qty" class="form-label">Quantity <span class="form-required">*</span></label>
            <input
                type="number"
                id="res-qty"
                name="quantity_reserved"
                value="<?= htmlspecialchars($input['quantity_reserved']) ?>"
                placeholder="1"
                min="1"
                step="1"
                class="form-control res-qty-sm"
                required
            >
            <p id="res-total-line" class="text-muted res-total-line" style="display:none;"></p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Reserve</button>
            <a href="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</section>

<script>
(function () {
    const productSelect = document.getElementById('res-product');
    const qtyInput      = document.getElementById('res-qty');
    const infoCard      = document.getElementById('res-product-info');
    const infoSku       = document.getElementById('res-info-sku');
    const infoPrice     = document.getElementById('res-info-price');
    const infoAvail     = document.getElementById('res-info-avail');
    const infoDesc      = document.getElementById('res-info-desc');
    const totalLine     = document.getElementById('res-total-line');

    function fmt(n) { return '$' + parseFloat(n).toFixed(2); }

    function updateProduct() {
        const opt = productSelect.options[productSelect.selectedIndex];
        if (!opt || !opt.value) {
            infoCard.style.display = 'none';
            qtyInput.removeAttribute('max');
            totalLine.style.display = 'none';
            return;
        }
        const avail = parseInt(opt.dataset.available, 10);
        qtyInput.max          = avail;
        infoSku.textContent   = opt.dataset.sku;
        infoPrice.textContent = fmt(opt.dataset.price);
        infoAvail.textContent = avail + ' unit' + (avail !== 1 ? 's' : '');
        infoDesc.textContent  = opt.dataset.desc;
        infoCard.style.display = 'block';
        updateTotal();
    }

    function updateTotal() {
        const opt = productSelect.options[productSelect.selectedIndex];
        if (!opt || !opt.value || !qtyInput.value) { totalLine.style.display = 'none'; return; }
        const total = parseFloat(opt.dataset.price) * parseInt(qtyInput.value, 10);
        totalLine.textContent  = `${opt.dataset.sku}  ×  ${qtyInput.value}  ×  ${fmt(opt.dataset.price)}  =  ${fmt(total)}`;
        totalLine.style.display = '';
    }

    productSelect.addEventListener('change', updateProduct);
    qtyInput.addEventListener('input', updateTotal);
    updateProduct();
}());
</script>
