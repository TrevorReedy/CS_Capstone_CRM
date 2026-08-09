
<section class="card">

    <div class="page-header">
        <h1>Create RFQ</h1>
        <a href="/modules/rfq/pipeline.php" class="btn btn-secondary">&#8592; Back</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="form-errors">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="module-form" id="rfq-create-form">
        <?= App\Core\Csrf::field() ?>

        <!-- Title -->
        <div class="form-group">
            <label for="rfq-title" class="form-label">Title <span class="form-required">*</span></label>
            <input
                type="text"
                id="rfq-title"
                name="title"
                value="<?= htmlspecialchars($input['title']) ?>"
                placeholder="e.g. Q3 Catheter Supply Request"
                class="form-control"
                required
            >
        </div>

        <!-- Account & Contact (side by side) -->
        <div class="form-row">

            <div class="form-group">
                <label for="rfq-account-search" class="form-label">Account <span class="text-muted">(optional)</span></label>
                <div class="form-input-row">
                    <div class="rfq-search-dropdown" id="account-dd">
                        <input
                            type="text"
                            id="rfq-account-search"
                            class="form-control rfq-search-input"
                            placeholder="Search accounts…"
                            autocomplete="off"
                        >
                        <input type="hidden" name="account_id" id="rfq-account-val" value="<?= htmlspecialchars($input['account_id']) ?>">
                    </div>
                    <a
                        href="/modules/customer/accounts.php"
                        class="rfq-create-btn"
                        title="Add a new account"
                    >+</a>
                </div>
                 <div class="rfq-search-results" id="rfq-account-results" style="display:none;"></div>

            </div>

            <div class="form-group">
                <label for="rfq-contact-search" class="form-label">Contact <span class="text-muted">(optional)</span></label>
                <div class="form-input-row">
                    <div class="rfq-search-dropdown" id="contact-dd">
                        <input
                            type="text"
                            id="rfq-contact-search"
                            class="form-control rfq-search-input"
                            placeholder="Search contacts…"
                            autocomplete="off"
                        >
                        <input type="hidden" name="contact_id" id="rfq-contact-val" value="<?= htmlspecialchars($input['contact_id']) ?>">
                    </div>
                    <a
                        id="rfq-contact-add-btn"
                        href="/modules/customer/accounts.php"
                        class="rfq-create-btn"
                        title="Add a new contact"
                    >+</a>
                </div>
                     <div class="rfq-search-results" id="rfq-contact-results" style="display:none;"></div>

            </div>

        </div>

        <!-- Stage + quote toggle button -->
        <div class="form-group">
            <label for="rfq-stage" class="form-label">Stage</label>
            <div class="form-input-row">
                <select id="rfq-stage" name="stage" class="form-control rfq-form-stage-select">
                    <?php foreach ($stages as $stage): ?>
                    <option value="<?= $stage ?>" <?= $input['stage'] === $stage ? 'selected' : '' ?>>
                        <?= $stage ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button
                    type="button"
                    id="rfq-add-quote-btn"
                    class="rfq-create-btn rfq-create-btn--wip"
                    title="Select a quote-required stage to add a quote"
                    disabled
                >+</button>
                <span class="field-hint">Quote</span>
            </div>
        </div>

        <!-- Inline quote (shown when a quote-required stage is selected) -->
        <div id="rfq-inline-quote" style="display:none;">
            <div class="form-row">
                <div class="form-group">
                    <label for="q-amount" class="form-label">Quote Amount</label>
                    <input type="number" id="q-amount" name="quote_amount"
                        value="<?= htmlspecialchars($input['quote_amount']) ?>"
                        placeholder="0.00" step="0.01" min="0" class="form-control">
                </div>
                <div class="form-group">
                    <label for="q-discount" class="form-label">Discount <span class="text-muted">(optional)</span></label>
                    <input type="number" id="q-discount" name="quote_discount"
                        value="<?= htmlspecialchars($input['quote_discount']) ?>"
                        placeholder="0.00" step="0.01" min="0" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="q-start" class="form-label">Valid From <span class="text-muted">(optional)</span></label>
                    <input type="date" id="q-start" name="quote_validity_start_date"
                        value="<?= htmlspecialchars($input['quote_validity_start_date']) ?>"
                        class="form-control">
                </div>
                <div class="form-group">
                    <label for="q-end" class="form-label">Valid To <span class="text-muted">(optional)</span></label>
                    <input type="date" id="q-end" name="quote_validity_end_date"
                        value="<?= htmlspecialchars($input['quote_validity_end_date']) ?>"
                        class="form-control">
                </div>
            </div>
        </div>

        <!-- Inventory toggle button (below quote section) -->
        <div class="section-toggle-row">
            <button
                type="button"
                id="rfq-add-res-btn"
                class="rfq-create-btn rfq-create-btn--wip"
                title="Select a quote-required stage to reserve inventory"
                disabled
            >+</button>
            <span class="field-hint">Reserve Inventory</span>
        </div>

        <!-- Inline inventory reservation — supports multiple items -->
        <div id="rfq-inline-res" style="display:none;">
            <table class="table rfq-res-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th style="width:100px;">SKU</th>
                        <th style="width:100px;">Unit Price</th>
                        <th style="width:90px;">Qty</th>
                        <th style="width:110px;">Total</th>
                        <th style="width:36px;"></th>
                    </tr>
                </thead>
                <tbody id="rfq-res-tbody"></tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="table-footer-label">Catalog Total</td>
                        <td class="table-footer-value"><strong id="rfq-res-grand-total">—</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
            <button type="button" id="rfq-res-add-row" class="btn btn-secondary rfq-res-add-row">+ Add Item</button>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label for="rfq-description" class="form-label">Description <span class="text-muted">(optional)</span></label>
            <textarea
                id="rfq-description"
                name="description"
                rows="4"
                placeholder="Describe the request, quantities, or any relevant details…"
                class="form-control"
            ><?= htmlspecialchars($input['description']) ?></textarea>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create RFQ</button>
            <a href="/modules/rfq/pipeline.php" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</section>

<script src="/assets/js/autocomplete.js"></script>
<script>
// Account + contact pickers, backed by the server-side autocomplete endpoints
// (no more embedding the whole accounts/contacts tables in the page).
(function () {
    const accountValEl    = document.getElementById('rfq-account-val');
    const contactSearchEl = document.getElementById('rfq-contact-search');
    const contactValEl    = document.getElementById('rfq-contact-val');

    initServerDropdown(
        document.getElementById('rfq-account-search'),
        accountValEl,
        document.getElementById('rfq-account-results'),
        {
            url: '/modules/rfq/search_accounts.php',
            onChanged: function (newAccountId) {
                // Point the contact "+" button at the selected account (or the list).
                const contactAddBtn = document.getElementById('rfq-contact-add-btn');
                if (contactAddBtn) {
                    contactAddBtn.href = newAccountId
                        ? '/modules/customer/account_detail.php?id=' + newAccountId
                        : '/modules/customer/accounts.php';
                    contactAddBtn.title = newAccountId
                        ? 'Add a new contact to this account'
                        : 'Add a new contact';
                }
                // Choosing a new account invalidates any previously picked contact
                // (contacts are account-scoped), so clear it; it re-scopes on focus.
                if (newAccountId) {
                    contactValEl.value = '';
                    contactSearchEl.value = '';
                    contactSearchEl.classList.remove('rfq-search-input--selected');
                }
            }
        }
    );

    initServerDropdown(
        contactSearchEl,
        contactValEl,
        document.getElementById('rfq-contact-results'),
        {
            url: '/modules/rfq/search_contacts.php',
            // Scope the contact search to the selected account (none = all).
            params: function () {
                return accountValEl.value ? { account_id: accountValEl.value } : {};
            }
        }
    );

    // Set initial contact "+" href if an account is already selected on load.
    (function () {
        const contactAddBtn = document.getElementById('rfq-contact-add-btn');
        if (contactAddBtn && accountValEl.value) {
            contactAddBtn.href  = '/modules/customer/account_detail.php?id=' + accountValEl.value;
            contactAddBtn.title = 'Add a new contact to this account';
        }
    }());
}());

// Client-side: at least one of account or contact required
(function () {
    document.getElementById('rfq-create-form').addEventListener('submit', function (e) {
        const accountVal = document.getElementById('rfq-account-val').value;
        const contactVal = document.getElementById('rfq-contact-val').value;
        if (!accountVal && !contactVal) {
            e.preventDefault();
            const existing = this.querySelector('.form-errors');
            if (!existing) {
                const box = document.createElement('div');
                box.className = 'form-errors';
                box.innerHTML = '<p>At least one of Account or Contact is required.</p>';
                this.insertBefore(box, this.firstChild);
            }
            window.scrollTo(0, 0);
        }
    });
}());

// Enable/disable the quote + button based on stage; expand inline quote on click
(function () {
    const quoteRequiredStages = <?= json_encode($quoteRequiredStages) ?>;
    const stageSelect  = document.getElementById('rfq-stage');
    const addBtn       = document.getElementById('rfq-add-quote-btn');
    const quoteSection = document.getElementById('rfq-inline-quote');
    let   quoteOpen    = <?= (!empty($input['quote_amount'])) ? 'true' : 'false' ?>;

    function updateQuoteBtn() {
        const isRequired = quoteRequiredStages.includes(stageSelect.value);
        addBtn.disabled = !isRequired;
        addBtn.classList.toggle('rfq-create-btn--wip', !isRequired);
        addBtn.title = isRequired
            ? 'Add a quote for this RFQ'
            : 'Select a quote-required stage to add a quote';
        if (!isRequired) {
            quoteSection.style.display = 'none';
            quoteOpen = false;
        }
    }

    addBtn.addEventListener('click', function () {
        quoteOpen = !quoteOpen;
        quoteSection.style.display = quoteOpen ? '' : 'none';
    });

    stageSelect.addEventListener('change', updateQuoteBtn);

    updateQuoteBtn();
    if (quoteOpen && quoteRequiredStages.includes(stageSelect.value)) {
        quoteSection.style.display = '';
    }
}());

// Multi-row inventory reservation
(function () {
    const quoteRequiredStages = <?= json_encode($quoteRequiredStages) ?>;
    const PRODUCTS            = <?= json_encode(array_values($products)) ?>;
    const RESTORE             = <?= json_encode([
        'products'   => array_values($_POST['res_product_id']       ?? []),
        'quantities' => array_values($_POST['res_quantity_reserved'] ?? []),
    ]) ?>;

    const stageSelect  = document.getElementById('rfq-stage');
    const resBtn       = document.getElementById('rfq-add-res-btn');
    const resSection   = document.getElementById('rfq-inline-res');
    const tbody        = document.getElementById('rfq-res-tbody');
    const addRowBtn    = document.getElementById('rfq-res-add-row');
    const grandTotalEl = document.getElementById('rfq-res-grand-total');
    let   resOpen      = RESTORE.products.length > 0;

    const PRODUCT_MAP = {};
    PRODUCTS.forEach(p => { PRODUCT_MAP[String(p.id)] = p; });

    function fmt(n) { return '$' + parseFloat(n).toFixed(2); }

    function truncate(str, len) {
        return str && str.length > len ? str.slice(0, len) + '…' : (str || '');
    }

    function buildOptions(selectedId) {
        return '<option value="">— Select product —</option>'
            + PRODUCTS.map(p => {
                const sel = String(p.id) === String(selectedId) ? ' selected' : '';
                return `<option value="${p.id}" data-available="${p.available_quantity}"${sel}>${p.product_name}</option>`;
            }).join('');
    }

    function syncGrandTotal() {
        let total = 0;
        tbody.querySelectorAll('.rfq-res-row').forEach(row => {
            const p  = PRODUCT_MAP[row.querySelector('.res-product-sel').value];
            const qi = row.querySelector('.res-qty-input');
            if (p && qi.value) total += parseFloat(p.price) * parseInt(qi.value, 10);
        });
        grandTotalEl.textContent = total > 0 ? fmt(total) : '—';
    }

    function addRow(productId, qty) {
        const tr = document.createElement('tr');
        tr.className = 'rfq-res-row';
        tr.innerHTML = `
            <td>
                <select name="res_product_id[]" class="form-control res-product-sel">
                    ${buildOptions(productId || '')}
                </select>
                <span class="res-desc text-muted"></span>
                <span class="field-hint res-avail-hint"></span>
            </td>
            <td><span class="res-sku text-muted">—</span></td>
            <td><span class="res-price">—</span></td>
            <td>
                <input type="number" name="res_quantity_reserved[]" class="form-control res-qty-input"
                    value="${qty || ''}" placeholder="1" min="1" step="1">
            </td>
            <td><strong class="res-total">—</strong></td>
            <td><button type="button" class="rfq-res-remove-btn" title="Remove row">&times;</button></td>
        `;

        const sel      = tr.querySelector('.res-product-sel');
        const hint     = tr.querySelector('.res-avail-hint');
        const descEl   = tr.querySelector('.res-desc');
        const skuEl    = tr.querySelector('.res-sku');
        const priceEl  = tr.querySelector('.res-price');
        const totalEl  = tr.querySelector('.res-total');
        const qi       = tr.querySelector('.res-qty-input');

        function syncRow() {
            const p = PRODUCT_MAP[sel.value];
            if (!p) {
                hint.style.display = 'none';
                descEl.textContent = '';
                skuEl.textContent  = '—';
                priceEl.textContent = '—';
                totalEl.textContent = '—';
                qi.removeAttribute('max');
                syncGrandTotal();
                return;
            }
            qi.max              = p.available_quantity;
            hint.textContent    = `${p.available_quantity} unit${p.available_quantity !== 1 ? 's' : ''} available`;
            hint.style.display  = '';
            descEl.textContent  = truncate(p.description, 65);
            skuEl.textContent   = p.sku;
            priceEl.textContent = fmt(p.price);
            syncTotal();
        }

        function syncTotal() {
            const p = PRODUCT_MAP[sel.value];
            if (!p || !qi.value) { totalEl.textContent = '—'; syncGrandTotal(); return; }
            totalEl.textContent = fmt(parseFloat(p.price) * parseInt(qi.value, 10));
            syncGrandTotal();
        }

        sel.addEventListener('change', syncRow);
        qi.addEventListener('input', syncTotal);
        tr.querySelector('.rfq-res-remove-btn').addEventListener('click', () => { tr.remove(); syncGrandTotal(); });
        tbody.appendChild(tr);
        syncRow();
    }

    function updateResBtn() {
        const isRequired = quoteRequiredStages.includes(stageSelect.value);
        resBtn.disabled = !isRequired;
        resBtn.classList.toggle('rfq-create-btn--wip', !isRequired);
        resBtn.title = isRequired
            ? 'Reserve inventory for this RFQ'
            : 'Select a quote-required stage to reserve inventory';
        if (!isRequired) { resSection.style.display = 'none'; resOpen = false; }
    }

    resBtn.addEventListener('click', function () {
        resOpen = !resOpen;
        resSection.style.display = resOpen ? '' : 'none';
        if (resOpen && tbody.rows.length === 0) addRow();
    });

    addRowBtn.addEventListener('click', () => addRow());
    stageSelect.addEventListener('change', updateResBtn);

    updateResBtn();

    // Restore rows after a validation failure re-render
    if (resOpen && quoteRequiredStages.includes(stageSelect.value)) {
        resSection.style.display = '';
        if (RESTORE.products.length > 0) {
            RESTORE.products.forEach((pid, i) => addRow(pid, RESTORE.quantities[i] || ''));
        } else {
            addRow();
        }
    }
}());
</script>
