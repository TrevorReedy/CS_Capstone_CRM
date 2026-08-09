
<!--
    TODO: CUSTOM AUDIENCE SEGMENTS — QUICK SELECTION
    Allow users to define named audience groups (e.g. "Enterprise Accounts", "VIP Contacts")
    that appear as one-click options when building a campaign audience, instead of manually
    picking accounts/contacts every time. Preset save/apply is scaffolded but not surfaced
    prominently. Consider: segment list on campaign creation, not just inside the audience
    editor after the fact. May also want tag-based auto-segments (anyone with tag X).
-->

<?php
$campaign           = $campaign           ?? null;
$accounts           = $accounts           ?? [];
$contacts           = $contacts           ?? [];
$currentAudience    = $currentAudience    ?? [];
$errors             = $errors             ?? [];
$editSegmentRows    = $editSegmentRows    ?? [];
$editSegmentName    = $editSegmentName    ?? '';
$presets            = $presets            ?? [];

$campaignId   = $campaign['id']            ?? (int)($_GET['campaign_id'] ?? 0);
$campaignName = $campaign['campaign_name'] ?? 'Campaign';
$isEditing    = $editSegmentName !== '';

// Collect all account_ids and contact_ids belonging to the segment being edited.
$editAccountIds = array_filter(array_column($editSegmentRows, 'account_id'));
$editContactIds = array_filter(array_column($editSegmentRows, 'contact_id'));
$editTagFilter  = $editSegmentRows[0]['tag_filter']   ?? '';

// Pre-fill form from edit segment (edit mode) or prior POST (validation failure).
$prefill = [
    'segment_name' => $editSegmentName,
    'tag_filter'   => $editTagFilter,
    'account_ids'  => array_map('intval', $editAccountIds),
    'contact_ids'  => array_map('intval', $editContactIds),
];

// Group currentAudience rows by segment_name for display.
$groupedAudience = [];
foreach ($currentAudience as $row) {
    $key = $row['segment_name'] ?? '';
    if (!isset($groupedAudience[$key])) {
        $groupedAudience[$key] = [
            'segment_name' => $row['segment_name'] ?? '—',
            'tag_filter'   => $row['tag_filter'],
            'accounts'     => [],
            'contacts'     => [],
        ];
    }
    if ($row['account_name'] !== null) {
        $groupedAudience[$key]['accounts'][] = $row['account_name'];
    }
    if ($row['contact_name'] !== null) {
        $groupedAudience[$key]['contacts'][] = $row['contact_name'];
    }
    // Take the first non-null tag_filter across the group.
    if ($groupedAudience[$key]['tag_filter'] === null && $row['tag_filter'] !== null) {
        $groupedAudience[$key]['tag_filter'] = $row['tag_filter'];
    }
}
?>

<section class="card">

    <div class="page-header">
        <h1><?= $isEditing ? 'Edit Audience Segment' : 'Add Audience Segment' ?></h1>
        <a href="/modules/campaign/detail.php?id=<?= $campaignId ?>" class="btn btn-secondary">&#8592; Back</a>
    </div>

    <p class="text-muted">
        Configuring audience for: <strong><?= htmlspecialchars($campaignName) ?></strong>
    </p>

    <?php if (!empty($errors)): ?>
    <div class="form-errors">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form id="audience-form" method="POST" action="" class="module-form">
        <?= App\Core\Csrf::field() ?>
        <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
        <?php if ($isEditing): ?>
        <input type="hidden" name="_edit_segment" value="<?= htmlspecialchars($editSegmentName) ?>">
        <?php endif; ?>

        <!-- Segment name -->
        <div class="form-group">
            <label for="segment-name" class="form-label">
                Segment Name <span class="form-required">*</span>
            </label>
            <input
                type="text"
                id="segment-name"
                name="segment_name"
                value="<?= htmlspecialchars($_POST['segment_name'] ?? $prefill['segment_name'] ?? '') ?>"
                placeholder="e.g. Q3 Catheter Buyers"
                class="form-control"
                required
            >
        </div>

        <!-- Tag filter -->
        <div class="form-group">
            <label for="tag-filter" class="form-label">
                Tag Filter <span class="text-muted">(optional)</span>
            </label>
            <input
                type="text"
                id="tag-filter"
                name="tag_filter"
                value="<?= htmlspecialchars($_POST['tag_filter'] ?? $prefill['tag_filter'] ?? '') ?>"
                placeholder="e.g. hospital, distributor"
                class="form-control"
            >
            <span class="field-hint">Comma-separated tags — matches any account or contact with at least one of these tags</span>
        </div>

        <!-- Account & Contact selection (side by side) -->
        <div class="form-row">

            <div class="form-group">
                <label for="audience-accounts" class="form-label">
                    Specific Accounts <span class="text-muted">(optional)</span>
                </label>

                <?php if (empty($accounts)): ?>
                    <p class="text-muted">No accounts found.</p>
                <?php else: ?>
                <?php
                $selectedAccountIds = isset($_POST['account_ids'])
                    ? array_map('intval', $_POST['account_ids'])
                    : ($prefill['account_ids'] ?? []);
                ?>
                <div class="form-input-row">
                    <div class="rfq-search-dropdown">
                        <input
                            type="text"
                            id="audience-accounts-search"
                            class="form-control rfq-search-input"
                            placeholder="Search accounts…"
                            autocomplete="off"
                        >
                    </div>
                    <a
                        href="/modules/customer/accounts.php"
                        class="add-btn"
                        title="Add a new account"
                    >+</a>
                </div>
                <div class="rfq-search-results" id="audience-accounts-results" style="display:none;"></div>
                <div class="audience-chips" id="audience-accounts-chips"></div>
                <select id="audience-accounts" name="account_ids[]" multiple style="display:none;">
                    <?php foreach ($accounts as $account): ?>
                    <option value="<?= (int)$account['id'] ?>"
                        <?= in_array((int)$account['id'], $selectedAccountIds, true) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($account['account_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="audience-contacts" class="form-label">
                    Specific Contacts <span class="text-muted">(optional)</span>
                </label>
                <?php
                $selectedContactIds = isset($_POST['contact_ids'])
                    ? array_map('intval', $_POST['contact_ids'])
                    : ($prefill['contact_ids'] ?? []);
                ?>
                <?php if (empty($contacts)): ?>
                    <p class="text-muted">No contacts found.</p>
                <?php else: ?>
                <div class="form-input-row">
                    <div class="rfq-search-dropdown">
                        <input
                            type="text"
                            id="audience-contacts-search"
                            class="form-control rfq-search-input"
                            placeholder="Search contacts…"
                            autocomplete="off"
                        >
                    </div>
                    <a
                        href="/modules/customer/accounts.php"
                        class="add-btn"
                        title="Add a new contact"
                    >+</a>
                </div>
                <div class="rfq-search-results" id="audience-contacts-results" style="display:none;"></div>
                <div class="audience-chips" id="audience-contacts-chips"></div>
                <select id="audience-contacts" name="contact_ids[]" multiple style="display:none;">
                    <?php foreach ($contacts as $contact): ?>
                    <option value="<?= (int)$contact['id'] ?>"
                        <?= in_array((int)$contact['id'], $selectedContactIds, true) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                        <?php if ($contact['account_name'] ?? ''): ?>
                            — <?= htmlspecialchars($contact['account_name']) ?>
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>

        </div>

        <!-- Audience preview -->
        <?php
        $savedAudienceCount = $savedAudienceCount ?? ['accounts' => 0, 'contacts' => 0];
        $savedTotal = ($savedAudienceCount['accounts'] ?? 0) + ($savedAudienceCount['contacts'] ?? 0);
        ?>
        <div class="audience-preview">
            <p class="form-label" style="margin-bottom:0.35rem;">Audience Preview</p>
            <p id="audience-preview-count" class="audience-preview-count">
                <?= $savedTotal ?> recipient<?= $savedTotal !== 1 ? 's' : '' ?>
                (<?= $savedAudienceCount['accounts'] ?> account<?= $savedAudienceCount['accounts'] !== 1 ? 's' : '' ?>,
                 <?= $savedAudienceCount['contacts'] ?> contact<?= $savedAudienceCount['contacts'] !== 1 ? 's' : '' ?>)
            </p>
            <p id="audience-preview-delta" class="text-muted" style="margin:0;font-size:0.8rem;">
                Select filters above to preview what this segment would add.
            </p>
        </div>

        <!-- Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $isEditing ? 'Save Changes' : 'Confirm Audience' ?>
            </button>
            <?php if ($isEditing): ?>
            <a href="/modules/campaign/audience.php?campaign_id=<?= $campaignId ?>" class="btn btn-secondary">Cancel Edit</a>
            <?php else: ?>
            <a href="/modules/campaign/detail.php?id=<?= $campaignId ?>" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" id="save-preset-toggle"
                    style="margin-left:auto;">Save as Preset</button>
        </div>

        <!-- Save-as-preset panel — shown when the toggle button is clicked -->
        <div id="save-preset-panel" style="display:none;margin-top:1rem;padding:1rem;background:var(--bg-subtle,#f6f7f9);border-radius:6px;border:1px solid var(--border,#e2e4e9);">
            <p class="form-label" style="margin-bottom:0.4rem;">Save current segment as a reusable preset</p>
            <form method="POST" action="" style="display:flex;gap:0.6rem;align-items:flex-end;flex-wrap:wrap;">
                <?= App\Core\Csrf::field() ?>
                <input type="hidden" name="_action"      value="save_preset">
                <input type="hidden" name="campaign_id"  value="<?= $campaignId ?>">
                <input type="hidden" name="segment_name" id="preset-segment-name-mirror">
                <input type="hidden" name="tag_filter"   id="preset-tag-filter-mirror">
                <!-- account_ids[] and contact_ids[] are mirrored by JS below -->
                <div style="flex:1;min-width:180px;">
                    <label class="form-label" style="font-size:0.82rem;">Preset Name</label>
                    <input type="text" name="preset_name" class="form-control"
                           placeholder="e.g. Hospital Outreach" required>
                </div>
                <div id="preset-account-ids-hidden"></div>
                <div id="preset-contact-ids-hidden"></div>
                <button type="submit" class="btn btn-primary" style="padding:6px 14px;">Save</button>
                <button type="button" class="btn btn-secondary" id="save-preset-cancel" style="padding:6px 14px;">Cancel</button>
            </form>
        </div>

    </form>

<script>
(function () {
    const tagInput      = document.getElementById('tag-filter');
    const accountSelect = document.getElementById('audience-accounts');
    const contactSelect = document.getElementById('audience-contacts');
    const deltaEl       = document.getElementById('audience-preview-delta');

    // ── Audience preview ─────────────────────────────────────────────────────

    let debounceTimer = null;

    function hasSelection() {
        const tag      = tagInput      ? tagInput.value.trim()        : '';
        const accounts = accountSelect ? accountSelect.selectedOptions.length : 0;
        const contacts = contactSelect ? contactSelect.selectedOptions.length : 0;
        return tag !== '' || accounts > 0 || contacts > 0;
    }

    function refreshPreview() {
        if (!deltaEl) return;
        if (!hasSelection()) {
            deltaEl.textContent = 'Select filters above to preview what this segment would add.';
            return;
        }

        const tag      = tagInput      ? tagInput.value.trim() : '';
        const accounts = accountSelect ? Array.from(accountSelect.selectedOptions).map(o => o.value) : [];
        const contacts = contactSelect ? Array.from(contactSelect.selectedOptions).map(o => o.value) : [];

        const body = new URLSearchParams();
        body.append('tag_filter', tag);
        accounts.forEach(id => body.append('account_ids[]', id));
        contacts.forEach(id => body.append('contact_ids[]', id));

        deltaEl.textContent = '…';

        fetch('/modules/campaign/preview_audience.php', { method: 'POST', body, headers: { 'X-CSRF-Token': '<?= App\Core\Csrf::token() ?>' } })
            .then(r => r.json())
            .then(data => {
                const total = (data.accounts || 0) + (data.contacts || 0);
                deltaEl.textContent = total === 0
                    ? 'No new recipients match the current selection.'
                    : `+ ${total} new recipient${total !== 1 ? 's' : ''} `
                    + `(${data.accounts || 0} account${(data.accounts || 0) !== 1 ? 's' : ''}, `
                    + `${data.contacts || 0} contact${(data.contacts || 0) !== 1 ? 's' : ''})`;
            })
            .catch(() => { deltaEl.textContent = 'Preview unavailable.'; });
    }

    function debounce() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshPreview, 350);
    }

    // ── Wire up events ───────────────────────────────────────────────────────

    if (accountSelect) accountSelect.addEventListener('change', refreshPreview);
    if (contactSelect) contactSelect.addEventListener('change', refreshPreview);
    if (tagInput)      tagInput.addEventListener('input', debounce);

    // ── Save-as-preset panel toggle ─────────────────────────────────────────

    const savePresetToggle  = document.getElementById('save-preset-toggle');
    const savePresetPanel   = document.getElementById('save-preset-panel');
    const savePresetCancel  = document.getElementById('save-preset-cancel');
    const presetSegMirror   = document.getElementById('preset-segment-name-mirror');
    const presetTagMirror   = document.getElementById('preset-tag-filter-mirror');
    const presetAccHolder   = document.getElementById('preset-account-ids-hidden');
    const presetConHolder   = document.getElementById('preset-contact-ids-hidden');
    const segmentNameInput  = document.getElementById('segment-name');

    function mirrorToPresetForm() {
        if (presetSegMirror  && segmentNameInput) presetSegMirror.value  = segmentNameInput.value;
        if (presetTagMirror  && tagInput)         presetTagMirror.value  = tagInput ? tagInput.value : '';

        // Mirror selected account IDs as hidden inputs
        if (presetAccHolder && accountSelect) {
            presetAccHolder.innerHTML = '';
            Array.from(accountSelect.selectedOptions).forEach(o => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'account_ids[]'; inp.value = o.value;
                presetAccHolder.appendChild(inp);
            });
        }

        // Mirror selected contact IDs as hidden inputs
        if (presetConHolder && contactSelect) {
            presetConHolder.innerHTML = '';
            Array.from(contactSelect.selectedOptions).forEach(o => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'contact_ids[]'; inp.value = o.value;
                presetConHolder.appendChild(inp);
            });
        }
    }

    if (savePresetToggle && savePresetPanel) {
        savePresetToggle.addEventListener('click', () => {
            mirrorToPresetForm();
            savePresetPanel.style.display = savePresetPanel.style.display === 'none' ? '' : 'none';
        });
    }
    if (savePresetCancel && savePresetPanel) {
        savePresetCancel.addEventListener('click', () => { savePresetPanel.style.display = 'none'; });
    }

    // ── Import-preset panel toggle ──────────────────────────────────────────

    const importPresetToggle = document.getElementById('import-preset-toggle');
    const importPresetPanel  = document.getElementById('import-preset-panel');
    const importPresetCancel = document.getElementById('import-preset-cancel');

    if (importPresetToggle && importPresetPanel) {
        importPresetToggle.addEventListener('click', () => {
            importPresetPanel.style.display = importPresetPanel.style.display === 'none' ? '' : 'none';
        });
    }
    if (importPresetCancel && importPresetPanel) {
        importPresetCancel.addEventListener('click', () => { importPresetPanel.style.display = 'none'; });
    }

    // ── Multi-select search dropdowns (same pattern as RFQ) ─────────────────

    function initMultiSearchDropdown(searchEl, selectEl, resultsEl, chipsEl, items) {
        let focusedIdx = -1;
        const itemMap = {};
        items.forEach(i => { itemMap[String(i.id)] = i; });

        function getSelectedIds() {
            return new Set(Array.from(selectEl.selectedOptions).map(o => String(o.value)));
        }

        function toggleId(id) {
            const opt = selectEl.querySelector(`option[value="${id}"]`);
            if (!opt) return;
            opt.selected = !opt.selected;
            renderChips();
            renderResults(searchEl.value);
            selectEl.dispatchEvent(new Event('change'));
        }

        function renderChips() {
            const selected = getSelectedIds();
            chipsEl.innerHTML = '';
            selected.forEach(id => {
                const item = itemMap[id];
                if (!item) return;
                const chip = document.createElement('span');
                chip.className = 'audience-chip';
                const label = document.createTextNode(item.label + ' ');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'audience-chip-remove';
                btn.title = 'Remove';
                btn.textContent = '×';
                btn.addEventListener('click', () => toggleId(id));
                chip.appendChild(label);
                chip.appendChild(btn);
                chipsEl.appendChild(chip);
            });
        }

        function renderResults(query) {
            const q = query.toLowerCase();
            const selected = getSelectedIds();
            const filtered = q ? items.filter(i => i.label.toLowerCase().includes(q)) : items;
            resultsEl.innerHTML = '';
            focusedIdx = -1;

            if (filtered.length === 0) {
                resultsEl.innerHTML = '<div class="rfq-search-option--empty">No results</div>';
            } else {
                filtered.forEach(item => {
                    const isChecked = selected.has(String(item.id));
                    const div = document.createElement('div');
                    div.className = 'rfq-search-option' + (isChecked ? ' rfq-search-option--checked' : '');
                    div.dataset.id = item.id;

                    const check = document.createElement('span');
                    check.className = 'rfq-option-check';
                    check.textContent = isChecked ? '✓' : '';

                    div.appendChild(check);
                    div.appendChild(document.createTextNode(item.label));
                    div.addEventListener('mousedown', e => {
                        e.preventDefault();
                        toggleId(String(item.id));
                    });
                    resultsEl.appendChild(div);
                });
            }
        }

        searchEl.addEventListener('input', function () {
            renderResults(this.value);
            resultsEl.style.display = '';
        });

        searchEl.addEventListener('focus', function () {
            renderResults(this.value);
            resultsEl.style.display = '';
        });

        searchEl.addEventListener('blur', function () {
            setTimeout(() => { resultsEl.style.display = 'none'; }, 150);
        });

        searchEl.addEventListener('keydown', function (e) {
            const opts = resultsEl.querySelectorAll('.rfq-search-option');
            if (!opts.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                focusedIdx = Math.min(focusedIdx + 1, opts.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                focusedIdx = Math.max(focusedIdx - 1, 0);
            } else if (e.key === 'Enter' && focusedIdx >= 0) {
                e.preventDefault();
                toggleId(opts[focusedIdx].dataset.id);
                return;
            } else if (e.key === 'Escape') {
                resultsEl.style.display = 'none';
                return;
            } else {
                return;
            }

            opts.forEach((o, i) => o.classList.toggle('rfq-search-option--focused', i === focusedIdx));
            if (opts[focusedIdx]) opts[focusedIdx].scrollIntoView({ block: 'nearest' });
        });

        renderChips();
    }

    const ACCOUNTS = <?= json_encode(array_map(fn($a) => ['id' => $a['id'], 'label' => $a['account_name']], $accounts)) ?>;
    const CONTACTS = <?= json_encode(array_map(fn($c) => [
        'id'    => $c['id'],
        'label' => trim($c['first_name'] . ' ' . $c['last_name']) . ($c['account_name'] ? ' — ' . $c['account_name'] : ''),
    ], $contacts)) ?>;

    const accountSearchInput  = document.getElementById('audience-accounts-search');
    const accountResultsEl    = document.getElementById('audience-accounts-results');
    const accountChipsEl      = document.getElementById('audience-accounts-chips');
    if (accountSearchInput && accountSelect && accountResultsEl && accountChipsEl) {
        initMultiSearchDropdown(accountSearchInput, accountSelect, accountResultsEl, accountChipsEl, ACCOUNTS);
    }

    const contactSearchInput  = document.getElementById('audience-contacts-search');
    const contactResultsEl    = document.getElementById('audience-contacts-results');
    const contactChipsEl      = document.getElementById('audience-contacts-chips');
    if (contactSearchInput && contactSelect && contactResultsEl && contactChipsEl) {
        initMultiSearchDropdown(contactSearchInput, contactSelect, contactResultsEl, contactChipsEl, CONTACTS);
    }
})();
</script>

</section>

<?php if (!empty($groupedAudience) || !empty($presets)): ?>
<!-- Current audience segments — one row per segment, accounts and contacts grouped -->
<section class="card">
    <div class="page-header">
        <h2>Current Audience Segments</h2>
        <?php if (!empty($presets)): ?>
        <button type="button" class="btn btn-secondary" id="import-preset-toggle"
                style="font-size:0.85rem;padding:5px 12px;">Import Preset</button>
        <?php endif; ?>
    </div>

    <!-- Import-preset panel -->
    <?php if (!empty($presets)): ?>
    <div id="import-preset-panel" style="display:none;margin-bottom:1rem;padding:1rem;background:var(--bg-subtle,#f6f7f9);border-radius:6px;border:1px solid var(--border,#e2e4e9);">
        <p class="form-label" style="margin-bottom:0.4rem;">Load a saved preset into this campaign's audience</p>
        <form method="POST" action="" style="display:flex;gap:0.6rem;align-items:flex-end;flex-wrap:wrap;">
            <?= App\Core\Csrf::field() ?>
            <input type="hidden" name="_action"     value="apply_preset">
            <input type="hidden" name="campaign_id" value="<?= $campaignId ?>">
            <div style="flex:1;min-width:200px;">
                <label class="form-label" style="font-size:0.82rem;">Preset</label>
                <select name="preset_id" class="form-control" required>
                    <option value="">— Select a preset —</option>
                    <?php foreach ($presets as $p): ?>
                    <option value="<?= (int)$p['id'] ?>">
                        <?= htmlspecialchars($p['preset_name']) ?>
                        (<?= htmlspecialchars($p['segment_name']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="padding:6px 14px;">Apply</button>
            <button type="button" class="btn btn-secondary" id="import-preset-cancel" style="padding:6px 14px;">Cancel</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($groupedAudience)): ?>
    <table class="table">
        <thead>
            <tr>
                <th>Segment</th>
                <th>Tag Filter</th>
                <th>Accounts</th>
                <th>Contacts</th>
                <th style="width:100px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($groupedAudience as $seg): ?>
            <tr>
                <td><?= htmlspecialchars($seg['segment_name']) ?></td>
                <td><?= htmlspecialchars($seg['tag_filter'] ?? '—') ?></td>
                <td><?= $seg['accounts'] ? htmlspecialchars(implode(', ', $seg['accounts'])) : '—' ?></td>
                <td><?= $seg['contacts'] ? htmlspecialchars(implode(', ', $seg['contacts'])) : '—' ?></td>
                <td style="display:flex;gap:6px;align-items:center;">
                    <a href="?campaign_id=<?= $campaignId ?>&edit_segment=<?= urlencode($seg['segment_name']) ?>#audience-form"
                       class="btn btn-secondary"
                       style="font-size:0.78rem;padding:3px 10px;">Edit</a>
                    <form method="POST" action="" style="display:inline;">
                        <?= App\Core\Csrf::field() ?>
                        <input type="hidden" name="_action"      value="remove_segment">
                        <input type="hidden" name="segment_name" value="<?= htmlspecialchars($seg['segment_name']) ?>">
                        <input type="hidden" name="campaign_id"  value="<?= $campaignId ?>">
                        <button type="submit" class="rfq-res-remove-btn" title="Remove segment">&times;</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-muted" style="margin:0.5rem 0;">No audience segments yet. Add one using the form above or import a preset.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
