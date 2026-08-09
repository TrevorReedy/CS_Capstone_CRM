<?php
namespace App\Modules\Campaign;

use App\Core\Auth;
use App\Core\Permissions;
class CampaignController
{
    private CampaignRepository $repo;
    private CampaignService    $service;

    // Validation state persisted across handlePost → render calls in the same request.
    private array $formInput      = ['campaign_name' => '', 'campaign_type' => 'Email', 'status' => 'Draft', 'scheduled_at' => null];
    private array $formErrors     = [];
    private array $audienceErrors = [];
    private array $audienceInput  = [];

    public function __construct()
    {
        $this->repo    = new CampaignRepository();
        $this->service = new CampaignService();
    }

    // ── List ───────────────────────────────────────────────────────────────────

    public function index(): void
    {
        // The campaigns list table is a client-driven DataTable (server-side
        // processing) fed by /modules/campaign/campaigns_data.php — no list query
        // needed here.
        include __DIR__ . '/views/campaigns_list.php';
    }

    // ── Create ─────────────────────────────────────────────────────────────────

    public function handleCreatePost(): void
    {
        $this->formInput  = $this->parseFormInput();
        $this->formErrors = $this->service->validateCampaignInput($this->formInput);

        if (empty($this->formErrors)) {
            $id = $this->service->createCampaign($this->formInput, (int)Auth::user()['id']);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign "' . $this->formInput['campaign_name'] . '" created.'];
            header('Location: /modules/campaign/detail.php?id=' . $id);
            exit;
        }

        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please fix the errors below.'];
    }

    // Renders the create form; re-populates with $this->formInput and $this->formErrors on validation failure.
    public function create(): void
    {
        $campaign = null;
        $errors   = $this->formErrors;
        $input    = $this->formInput;
        include __DIR__ . '/views/campaign_form.php';
    }

    // ── Detail ─────────────────────────────────────────────────────────────────

    public function show(int $id): void
    {
        $campaign = $this->repo->findById($id);
        $audience = $campaign ? $this->repo->getAudienceByCampaignId($id) : [];
        include __DIR__ . '/views/campaign_metrics.php';
    }

    // ── Edit ───────────────────────────────────────────────────────────────────

    public function handleUpdatePost(int $id): void
    {
        $this->formInput  = $this->parseFormInput();
        $this->formErrors = $this->service->validateCampaignInput($this->formInput);

        if (empty($this->formErrors)) {
            $this->service->updateCampaign($id, $this->formInput);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign updated.'];
            header('Location: /modules/campaign/detail.php?id=' . $id);
            exit;
        }

        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please fix the errors below.'];
    }

    // Renders the edit form; re-populates with $this->formInput and $this->formErrors on validation failure.
    public function edit(int $id): void
    {
        // Authorization is enforced at the entry point (public/modules/campaign/edit.php)
        // so it covers the POST path too, not just this render.
        $campaign = $this->repo->findById($id);
        if (!$campaign) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Campaign not found.'];
            header('Location: /modules/campaign/campaigns.php');
            exit;
        }

        $errors = $this->formErrors;
        $input  = $this->formErrors
            ? $this->formInput
            : ['campaign_name' => $campaign['campaign_name'],
               'campaign_type' => $campaign['campaign_type'],
               'status'        => $campaign['status'],
               'scheduled_at'  => $campaign['scheduled_at']];

        include __DIR__ . '/views/campaign_form.php';
    }

    // ── Audience ───────────────────────────────────────────────────────────────

    public function handleAudiencePost(int $campaignId): void
    {
        $this->audienceInput = [
            'segment_name' => trim($_POST['segment_name'] ?? ''),
            'tag_filter'   => trim($_POST['tag_filter']   ?? ''),
            'account_ids'  => $_POST['account_ids']  ?? [],
            'contact_ids'  => $_POST['contact_ids']  ?? [],
        ];
        $this->audienceErrors = $this->service->validateAudienceInput($this->audienceInput);

        if (empty($this->audienceErrors)) {
            // Edit mode replaces a segment: delete the old rows, then insert the
            // new ones. Those are two writes, and without a transaction a failure
            // between them left the campaign with no audience at all — the old
            // members already gone, the new ones never added.
            $editSegment = trim($_POST['_edit_segment'] ?? '');
            $db = \App\Core\Database::connection();
            $db->beginTransaction();
            try {
                if ($editSegment !== '') {
                    $this->repo->deleteAudienceBySegment($campaignId, $editSegment);
                }
                $this->service->addAudienceSegment($campaignId, $this->audienceInput);
                $db->commit();
            } catch (\Throwable $e) {
                $db->rollBack();
                $_SESSION['flash'] = [
                    'type'    => 'error',
                    'message' => \App\Core\ErrorResponse::flash($e, 'save the audience segment', 'CampaignController::handleAudiencePost'),
                ];
                header('Location: /modules/campaign/audience.php?campaign_id=' . $campaignId);
                exit;
            }

            $msg = $editSegment !== '' ? 'Audience segment updated.' : 'Audience segment added.';
            $_SESSION['flash'] = ['type' => 'success', 'message' => $msg];
            header('Location: /modules/campaign/audience.php?campaign_id=' . $campaignId);
            exit;
        }

        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Please fix the errors below.'];
        // audienceErrors and audienceInput stored on $this — audience() reads them below.
    }

    public function handleRemoveSegment(string $segmentName, int $campaignId): void
    {
        $this->repo->deleteAudienceBySegment($campaignId, $segmentName);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Audience segment removed.'];
        header('Location: /modules/campaign/audience.php?campaign_id=' . $campaignId);
        exit;
    }

    public function handleSavePresetPost(int $campaignId): void
    {
        $presetName  = trim($_POST['preset_name']  ?? '');
        $segmentName = trim($_POST['segment_name'] ?? '');
        $tagFilter   = trim($_POST['tag_filter']   ?? '');
        $accountIds  = $_POST['account_ids']  ?? [];
        $contactIds  = $_POST['contact_ids']  ?? [];

        if ($presetName === '' || $segmentName === '') {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Preset name and segment name are required.'];
            header('Location: /modules/campaign/audience.php?campaign_id=' . $campaignId);
            exit;
        }

        $this->repo->insertPreset([
            'preset_name'        => $presetName,
            'segment_name'       => $segmentName,
            'tag_filter'         => $tagFilter,
            'account_ids'        => $accountIds,
            'contact_ids'        => $contactIds,
            'created_by_user_id' => (int)Auth::user()['id'],
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => "Preset \"{$presetName}\" saved."];
        header('Location: /modules/campaign/audience.php?campaign_id=' . $campaignId);
        exit;
    }

    public function handleApplyPresetPost(int $campaignId): void
    {
        $presetId = (int)($_POST['preset_id'] ?? 0);
        if ($presetId === 0) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Select a preset to import.'];
            header('Location: /modules/campaign/audience.php?campaign_id=' . $campaignId);
            exit;
        }

        $preset = $this->repo->findPresetById($presetId);
        if (!$preset) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Preset not found.'];
            header('Location: /modules/campaign/audience.php?campaign_id=' . $campaignId);
            exit;
        }

        $this->service->addAudienceSegment($campaignId, [
            'segment_name' => $preset['segment_name'],
            'tag_filter'   => $preset['tag_filter']   ?? '',
            'account_ids'  => $preset['account_ids'],
            'contact_ids'  => $preset['contact_ids'],
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => "Preset \"{$preset['preset_name']}\" imported."];
        header('Location: /modules/campaign/audience.php?campaign_id=' . $campaignId);
        exit;
    }

    public function audience(int $campaignId): void
    {
        $campaign = $this->repo->findById($campaignId);
        if (!$campaign) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Campaign not found.'];
            header('Location: /modules/campaign/campaigns.php');
            exit;
        }
        $accounts           = $this->repo->allAccounts();
        $contacts           = $this->repo->allContacts();
        $currentAudience    = $this->repo->getAudienceByCampaignId($campaignId);
        $savedAudienceCount = $this->repo->countSavedAudience($campaignId);
        $presets            = $this->repo->allPresets();
        $errors             = $this->audienceErrors;

        // Load all rows for the segment being edited when ?edit_segment is in the URL.
        $editSegmentRows = [];
        $editSegmentName = trim($_GET['edit_segment'] ?? '');
        if ($editSegmentName !== '') {
            $editSegmentRows = $this->repo->getAudienceSegment($campaignId, $editSegmentName);
        }

        include __DIR__ . '/views/audience_selection.php';
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    public function handleDeletePost(int $id): void
    {
    if (!Permissions::can('campaigns.delete')) {
    layout_deny();
    exit;
}
    {
        $this->repo->delete($id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign deleted.'];
        header('Location: /modules/campaign/campaigns.php');
        exit;
    }
}
    // ── Simulate Send ──────────────────────────────────────────────────────────

    public function handleSimulatePost(int $id): void
    {
    if (!Permissions::can('campaigns.metrics')) {
    layout_deny();
    exit;
}
        $this->service->simulateSend($id);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Campaign send simulated successfully.'];
        header('Location: /modules/campaign/detail.php?id=' . $id);
        exit;
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    // Extracts and sanitises campaign name, type, status, and optional scheduled date from POST.
    private function parseFormInput(): array
    {
        $scheduledAt = trim($_POST['scheduled_at'] ?? '');
        return [
            'campaign_name' => trim($_POST['campaign_name'] ?? ''),
            'campaign_type' => trim($_POST['campaign_type'] ?? ''),
            'status'        => trim($_POST['status']        ?? 'Draft'),
            'scheduled_at'  => $scheduledAt !== '' ? $scheduledAt : null,
        ];
    }
}
