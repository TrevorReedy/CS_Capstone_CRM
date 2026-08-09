
<section class="card">

    <div class="page-header">
        <h1>Edit RFQ</h1>
        <a href="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-secondary">&#8592; Back</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="form-errors">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="module-form" id="rfq-edit-form">
        <?= App\Core\Csrf::field() ?>

        <!-- Title -->
        <div class="form-group">
            <label for="rfq-title" class="form-label">Title <span class="form-required">*</span></label>
            <input
                type="text"
                id="rfq-title"
                name="title"
                value="<?= htmlspecialchars($input['title']) ?>"
                class="form-control"
                required
            >
        </div>

        <!-- Account & Contact -->
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
                        href="/modules/customer/create_account.php"
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
                        href="/modules/customer/create_account.php"
                        class="rfq-create-btn"
                        title="Add a new contact"
                    >+</a>
                </div>
                <div class="rfq-search-results" id="rfq-contact-results" style="display:none;"></div>
            </div>

        </div>

        <!-- Stage -->
        <div class="form-group">
            <label for="rfq-stage" class="form-label">Stage</label>
            <select id="rfq-stage" name="stage" class="form-control rfq-form-stage-select">
                <?php foreach ($stages as $stage): ?>
                <option value="<?= $stage ?>" <?= $input['stage'] === $stage ? 'selected' : '' ?>>
                    <?= $stage ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label for="rfq-description" class="form-label">Description <span class="text-muted">(optional)</span></label>
            <textarea
                id="rfq-description"
                name="description"
                rows="4"
                class="form-control"
            ><?= htmlspecialchars($input['description'] ?? '') ?></textarea>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</section>

<!-- ── Inventory Reservations ────────────────────────── -->
<section class="card">
    <div class="page-header">
        <h2 class="rfq-detail-card-title">Inventory Reservations</h2>
        <a href="/modules/rfq/create_reservation.php?rfq_id=<?= (int)$rfq['id'] ?>"
           class="rfq-create-btn" title="Reserve inventory">+</a>
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
                <td><span class="rfq-badge <?= $resBadge ?>"><?= htmlspecialchars($res['reservation_status']) ?></span></td>
                <td style="display:flex;gap:4px;align-items:center;">
                    <?php if ($res['reservation_status'] === 'Reserved'): ?>
                    <a href="/modules/rfq/edit_reservation.php?id=<?= (int)$res['id'] ?>"
                       class="btn btn-secondary" style="font-size:0.78rem;padding:3px 8px;">Edit</a>
                    <?php endif; ?>
                    <form method="POST" action="/modules/rfq/edit.php?id=<?= (int)$rfq['id'] ?>" style="display:inline;"
                          onsubmit="return confirm('Remove this reservation?<?= $res['reservation_status'] === 'Reserved' ? ' Stock will be returned to inventory.' : '' ?>');">
                        <?= App\Core\Csrf::field() ?>
                        <input type="hidden" name="_action"        value="delete_reservation">
                        <input type="hidden" name="reservation_id" value="<?= (int)$res['id'] ?>">
                        <input type="hidden" name="rfq_id"         value="<?= (int)$rfq['id'] ?>">
                        <button type="submit" class="rfq-res-remove-btn" title="Remove reservation">&times;</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

<script src="/assets/js/autocomplete.js"></script>
<script>
(function () {
    // Account + contact pickers, backed by the server-side autocomplete endpoints
    // (no more embedding the whole accounts/contacts tables in the page).
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

    document.getElementById('rfq-edit-form').addEventListener('submit', function (e) {
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
</script>
