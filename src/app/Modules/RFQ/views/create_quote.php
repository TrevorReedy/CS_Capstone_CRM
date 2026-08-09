<section class="card">

    <div class="page-header">
        <h1>Add Quote</h1>
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

        <!-- Amount & Discount -->
        <div class="form-row">

            <div class="form-group">
                <label for="q-amount" class="form-label">Amount <span class="form-required">*</span></label>
                <input
                    type="number"
                    id="q-amount"
                    name="quote_amount"
                    value="<?= htmlspecialchars($input['quote_amount']) ?>"
                    placeholder="0.00"
                    step="0.01"
                    min="0"
                    class="form-control"
                    required
                >
            </div>

            <div class="form-group">
                <label for="q-discount" class="form-label">Discount <span class="text-muted">(optional)</span></label>
                <input
                    type="number"
                    id="q-discount"
                    name="discount"
                    value="<?= htmlspecialchars($input['discount']) ?>"
                    placeholder="0.00"
                    step="0.01"
                    min="0"
                    class="form-control"
                >
            </div>

        </div>

        <!-- Validity dates -->
        <div class="form-row">

            <div class="form-group">
                <label for="q-start" class="form-label">Valid From <span class="text-muted">(optional)</span></label>
                <input
                    type="date"
                    id="q-start"
                    name="validity_start_date"
                    value="<?= htmlspecialchars($input['validity_start_date']) ?>"
                    class="form-control"
                >
            </div>

            <div class="form-group">
                <label for="q-end" class="form-label">Valid To <span class="text-muted">(optional)</span></label>
                <input
                    type="date"
                    id="q-end"
                    name="validity_end_date"
                    value="<?= htmlspecialchars($input['validity_end_date']) ?>"
                    class="form-control"
                >
            </div>

        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Quote</button>
            <a href="/modules/rfq/detail.php?id=<?= (int)$rfq['id'] ?>" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</section>
