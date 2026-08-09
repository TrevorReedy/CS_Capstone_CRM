<?php $entityType = $input['entity_type'] ?? ''; ?>


<section class="card">

    <div class="page-header">
        <h1>Add Customer / Account</h1>
        <a href="accounts.php" class="button btn-ghost">&#8592; Back</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="form-errors">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="form" id="create-entity-form">
        <?= App\Core\Csrf::field() ?>

        <!-- Type selector -->
        <div class="form-group">
            <label for="entity-type" class="form-label">What would you like to add? <span class="form-required">*</span></label>
            <select id="entity-type" name="entity_type" class="form-control" required>
                <option value="">&mdash; Select Account or Contact &mdash;</option>
                <option value="account" <?= $entityType === 'account' ? 'selected' : '' ?>>Account</option>
                <option value="contact" <?= $entityType === 'contact' ? 'selected' : '' ?>>Contact</option>
            </select>
            <span class="field-hint">A contact must belong to an account. An account can be added on its own.</span>
        </div>

        <!-- ACCOUNT FIELDS -->
        <fieldset id="account-fields" class="entity-fieldset"
                  <?= $entityType === 'account' ? '' : 'disabled style="display:none;"' ?>>

            <div class="form-group">
                <label for="acc-name" class="form-label">Account Name <span class="form-required">*</span></label>
                <input type="text" id="acc-name" name="account_name" class="form-control"
                       value="<?= htmlspecialchars($input['account_name'] ?? '') ?>" placeholder="Customer / company name">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="acc-email" class="form-label">Email</label>
                    <input type="email" id="acc-email" name="email" class="form-control"
                           value="<?= htmlspecialchars($entityType === 'account' ? ($input['email'] ?? '') : '') ?>" placeholder="Email">
                </div>
                <div class="form-group">
                    <label for="acc-phone" class="form-label">Phone</label>
                    <input type="text" id="acc-phone" name="phone" class="form-control"
                           value="<?= htmlspecialchars($entityType === 'account' ? ($input['phone'] ?? '') : '') ?>" placeholder="Phone">
                </div>
            </div>

            <div class="form-group">
                <label for="acc-address" class="form-label">Address</label>
                <input type="text" id="acc-address" name="address" class="form-control"
                       value="<?= htmlspecialchars($input['address'] ?? '') ?>" placeholder="Address">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="acc-industry" class="form-label">Industry</label>
                    <input type="text" id="acc-industry" name="industry" class="form-control"
                           value="<?= htmlspecialchars($input['industry'] ?? '') ?>" placeholder="Industry">
                </div>
                <div class="form-group">
                    <label for="acc-source" class="form-label">Source</label>
                    <input type="text" id="acc-source" name="source" class="form-control"
                           value="<?= htmlspecialchars($input['source'] ?? '') ?>" placeholder="Source">
                </div>
            </div>

            <div class="form-group">
                <label for="acc-tags" class="form-label">Tags</label>
                <input type="text" id="acc-tags" name="tags" class="form-control"
                       value="<?= htmlspecialchars($input['tags'] ?? '') ?>" placeholder="Comma-separated tags">
            </div>

        </fieldset>

        <!-- CONTACT FIELDS -->
        <fieldset id="contact-fields" class="entity-fieldset"
                  <?= $entityType === 'contact' ? '' : 'disabled style="display:none;"' ?>>

            <div class="form-group">
                <label for="con-account" class="form-label">Account <span class="form-required">*</span></label>
                <select id="con-account" name="account_id" class="form-control">
                    <option value="">&mdash; Select an account &mdash;</option>
                    <?php foreach ($accounts as $a): ?>
                    <option value="<?= (int)$a['id'] ?>"
                        <?= (string)($input['account_id'] ?? '') === (string)$a['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['account_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <span class="field-hint">Which account does this contact belong to?</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="con-first" class="form-label">First Name <span class="form-required">*</span></label>
                    <input type="text" id="con-first" name="first_name" class="form-control"
                           value="<?= htmlspecialchars($input['first_name'] ?? '') ?>" placeholder="First name">
                </div>
                <div class="form-group">
                    <label for="con-last" class="form-label">Last Name <span class="form-required">*</span></label>
                    <input type="text" id="con-last" name="last_name" class="form-control"
                           value="<?= htmlspecialchars($input['last_name'] ?? '') ?>" placeholder="Last name">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="con-email" class="form-label">Email</label>
                    <input type="email" id="con-email" name="email" class="form-control"
                           value="<?= htmlspecialchars($entityType === 'contact' ? ($input['email'] ?? '') : '') ?>" placeholder="Email">
                </div>
                <div class="form-group">
                    <label for="con-phone" class="form-label">Phone</label>
                    <input type="text" id="con-phone" name="phone" class="form-control"
                           value="<?= htmlspecialchars($entityType === 'contact' ? ($input['phone'] ?? '') : '') ?>" placeholder="Phone">
                </div>
            </div>

            <div class="form-group">
                <label for="con-title" class="form-label">Title</label>
                <input type="text" id="con-title" name="title" class="form-control"
                       value="<?= htmlspecialchars($input['title'] ?? '') ?>" placeholder="e.g. Purchasing Manager">
            </div>

        </fieldset>

        <!-- Actions — submit stays disabled until a type is chosen -->
        <div class="form-actions">
            <button type="submit" id="create-submit" class="button button-primary" disabled>Add</button>
            <a href="accounts.php" class="button btn-ghost">Cancel</a>
        </div>

    </form>

</section>

<script>
(function () {
    const typeSel   = document.getElementById('entity-type');
    const accountFs = document.getElementById('account-fields');
    const contactFs = document.getElementById('contact-fields');
    const submitBtn = document.getElementById('create-submit');

    // A disabled fieldset hides its inputs from submission AND suppresses their
    // "required" validation, so only the active section is ever submitted.
    function apply() {
        const t = typeSel.value;

        accountFs.disabled = (t !== 'account');
        accountFs.style.display = (t === 'account') ? '' : 'none';

        contactFs.disabled = (t !== 'contact');
        contactFs.style.display = (t === 'contact') ? '' : 'none';

        // Enforce required-ness only on the visible section's key fields.
        document.getElementById('acc-name').required = (t === 'account');
        document.getElementById('con-account').required = (t === 'contact');
        document.getElementById('con-first').required = (t === 'contact');
        document.getElementById('con-last').required = (t === 'contact');

        submitBtn.disabled = (t === '');
        submitBtn.textContent = t === 'account' ? 'Add Account'
                              : t === 'contact' ? 'Add Contact'
                              : 'Add';
    }

    typeSel.addEventListener('change', apply);
    apply(); // run on load so a validation re-render restores the correct state
}());
</script>
