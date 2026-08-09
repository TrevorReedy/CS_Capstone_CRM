<?php
namespace App\Modules\Campaign;

class CampaignService
{
    private CampaignRepository $repo;

    public function __construct()
    {
        $this->repo = new CampaignRepository();
    }

    // ── Validation ─────────────────────────────────────────────────────────────

    public function validateCampaignInput(array $data): array
    {
        $errors = [];

        if (trim($data['campaign_name'] ?? '') === '') {
            $errors[] = 'Campaign name is required.';
        } elseif (strlen(trim($data['campaign_name'])) > 255) {
            $errors[] = 'Campaign name must be 255 characters or fewer.';
        }

        if (!$this->isValidType($data['campaign_type'] ?? '')) {
            $errors[] = 'Invalid campaign type.';
        }

        if (!$this->isValidStatus($data['status'] ?? '')) {
            $errors[] = 'Invalid status.';
        }

        return $errors;
    }

    public function validateAudienceInput(array $data): array
    {
        $errors = [];

        if (trim($data['segment_name'] ?? '') === '') {
            $errors[] = 'Segment name is required.';
        }

        $hasTarget = !empty($data['tag_filter'])
            || !empty($data['account_ids'])
            || !empty($data['contact_ids']);

        if (!$hasTarget) {
            $errors[] = 'Provide a tag filter or select at least one account or contact.';
        }

        return $errors;
    }

    public function isValidType(string $type): bool
    {
        return in_array($type, CampaignRepository::$types, true);
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, CampaignRepository::$statuses, true);
    }

    // ── Writes ─────────────────────────────────────────────────────────────────

    public function createCampaign(array $data, int $userId): int
    {
        return $this->repo->insert([
            'campaign_name'      => trim($data['campaign_name']),
            'campaign_type'      => $data['campaign_type'],
            'status'             => $data['status'],
            'scheduled_at'       => $data['scheduled_at'] ?? null,
            'created_by_user_id' => $userId,
        ]);
    }

    public function updateCampaign(int $id, array $data): void
    {
        $this->repo->update($id, [
            'campaign_name' => trim($data['campaign_name']),
            'campaign_type' => $data['campaign_type'],
            'status'        => $data['status'],
            'scheduled_at'  => $data['scheduled_at'] ?? null,
        ]);
    }

    // Inserts one audience row per selected account and contact; falls back to a
    // tag-only row when neither accounts nor contacts are explicitly chosen.
    public function addAudienceSegment(int $campaignId, array $data): void
    {
        $segmentName = trim($data['segment_name']);
        $tagFilter   = trim($data['tag_filter']   ?? '');
        $accountIds  = array_filter(array_map('intval', $data['account_ids'] ?? []));
        $contactIds  = array_filter(array_map('intval', $data['contact_ids'] ?? []));

        foreach ($accountIds as $accountId) {
            $this->repo->insertAudience($campaignId, [
                'account_id'   => $accountId,
                'contact_id'   => null,
                'tag_filter'   => $tagFilter,
                'segment_name' => $segmentName,
            ]);
        }

        foreach ($contactIds as $contactId) {
            $this->repo->insertAudience($campaignId, [
                'account_id'   => null,
                'contact_id'   => $contactId,
                'tag_filter'   => null,
                'segment_name' => $segmentName,
            ]);
        }

        if (empty($accountIds) && empty($contactIds) && $tagFilter !== '') {
            $this->repo->insertAudience($campaignId, [
                'account_id'   => null,
                'contact_id'   => null,
                'tag_filter'   => $tagFilter,
                'segment_name' => $segmentName,
            ]);
        }
    }

    // Counts audience rows and records the simulated recipient (sent) count.
    public function simulateSend(int $campaignId): void
    {
        $audience = $this->repo->getAudienceByCampaignId($campaignId);
        // Count distinct accounts + contacts in the audience
        $accountIds = array_filter(array_column($audience, 'account_id'));
        $contactIds = array_filter(array_column($audience, 'contact_id'));
        $sentCount  = count(array_unique($accountIds)) + count(array_unique($contactIds));
        if ($sentCount === 0) {
            $sentCount = 1;
        }

        $this->repo->updateMetrics($campaignId, [
            'sent_count' => $sentCount,
        ]);
    }
}
