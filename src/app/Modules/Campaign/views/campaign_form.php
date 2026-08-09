<?php
$campaign = $campaign ?? null;
$errors   = $errors   ?? [];
$input    = $input    ?? [
    'campaign_name' => $campaign['campaign_name'] ?? '',
    'campaign_type' => $campaign['campaign_type'] ?? 'Email',
    'status'        => $campaign['status']        ?? 'Draft',
    'scheduled_at'  => $campaign['scheduled_at']  ?? null,
];
$isEdit   = $campaign !== null;
$backUrl  = $isEdit
    ? '/modules/campaign/detail.php?id=' . (int)$campaign['id']
    : '/modules/campaign/campaigns.php';
?>

<section class="card">

    <div class="page-header">
        <h1><?= $isEdit ? 'Edit Campaign' : 'Create Campaign' ?></h1>
        <a href="<?= $backUrl ?>" class="btn btn-secondary">&#8592; Back</a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="form-errors">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="module-form">
        <?= App\Core\Csrf::field() ?>

        <!-- Campaign Name -->
        <div class="form-group">
            <label for="campaign-name" class="form-label">
                Campaign Name <span class="form-required">*</span>
            </label>
            <input
                type="text"
                id="campaign-name"
                name="campaign_name"
                value="<?= htmlspecialchars($input['campaign_name']) ?>"
                placeholder="e.g. Q3 Email Outreach"
                class="form-control"
                required
            >
        </div>

        <!-- Type & Status (side by side) -->
        <div class="form-row">

            <div class="form-group">
                <label for="campaign-type" class="form-label">
                    Type <span class="form-required">*</span>
                </label>
                <select id="campaign-type" name="campaign_type" class="form-control" required>
                    <option value="Email"          <?= $input['campaign_type'] === 'Email'          ? 'selected' : '' ?>>Email</option>
                    <option value="SMS Simulation" <?= $input['campaign_type'] === 'SMS Simulation' ? 'selected' : '' ?>>SMS Simulation</option>
                </select>
            </div>

            <div class="form-group">
                <label for="campaign-status" class="form-label">Status</label>
                <select id="campaign-status" name="status" class="form-control">
                    <?php foreach (['Draft', 'Scheduled', 'Sent', 'Completed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $input['status'] === $s ? 'selected' : '' ?>>
                        <?= $s ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>

        <!-- Scheduled Date (visible only when status = Scheduled) -->
        <div class="form-group" id="scheduled-at-group" style="display:none;">
            <label for="scheduled-at" class="form-label">Scheduled Date &amp; Time</label>
            <input
                type="datetime-local"
                id="scheduled-at"
                name="scheduled_at"
                class="form-control"
                value="<?= htmlspecialchars($input['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($input['scheduled_at'])) : '') ?>"
            >
        </div>
        <script>
        (function () {
            var sel   = document.getElementById('campaign-status');
            var group = document.getElementById('scheduled-at-group');
            function toggle() { group.style.display = sel.value === 'Scheduled' ? '' : 'none'; }
            sel.addEventListener('change', toggle);
            toggle();
        })();
        </script>

        <!-- Audience notice -->
        <?php if ($isEdit): ?>
        <div class="audience-preview">
            <p class="form-label" style="margin-bottom:0.35rem;">Audience</p>
            <p class="text-muted" style="margin:0 0 0.5rem;">
                Audience is configured separately from the campaign form.
            </p>
            <a href="/modules/campaign/audience.php?campaign_id=<?= (int)$campaign['id'] ?>" class="btn btn-secondary">
                Manage Audience
            </a>
        </div>
        <?php else: ?>
        <div class="audience-preview">
            <p class="form-label" style="margin-bottom:0.35rem;">Audience</p>
            <p class="text-muted" style="margin:0;">
                Save the campaign first, then select your audience.
            </p>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? 'Save Changes' : 'Create Campaign' ?>
            </button>
            <a href="<?= $backUrl ?>" class="btn btn-secondary">Cancel</a>
        </div>

    </form>

</section>
