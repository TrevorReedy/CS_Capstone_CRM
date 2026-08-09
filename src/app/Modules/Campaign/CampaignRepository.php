<?php
namespace App\Modules\Campaign;

use App\Core\Database;
use App\Core\DataTable\ServerTable;
use PDO;

class CampaignRepository
{
    public static array $statuses = ['Draft', 'Scheduled', 'Sent', 'Completed'];
    public static array $types    = ['Email', 'SMS Simulation'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Server-side DataTables source for the campaigns list (the top table on the
     * Campaigns page — the analytics/momentum sections below it are unaffected).
     * Shared by the data + export endpoints. Name search uses the ft_campaigns_name
     * FULLTEXT index; type/status are exact per-column select filters.
     */
    public static function listTable(): ServerTable
    {
        return new ServerTable(
            Database::connection(),
            'campaigns c',
            'c.id, c.campaign_name, c.campaign_type, c.status, c.sent_count, c.created_at',
            [
                ['data' => 'id',            'sql' => 'c.id',            'order' => true, 'search' => 'like'],
                ['data' => 'campaign_name', 'sql' => 'c.campaign_name', 'order' => true, 'search' => 'fulltext', 'ft' => 'c.campaign_name'],
                ['data' => 'campaign_type', 'sql' => 'c.campaign_type', 'order' => true, 'search' => 'exact'],
                ['data' => 'status',        'sql' => 'c.status',        'order' => true, 'search' => 'exact'],
                ['data' => 'sent_count',    'sql' => 'c.sent_count',    'order' => true, 'search' => false],
                ['data' => 'created_at',    'sql' => 'c.created_at',    'order' => true, 'search' => false],
            ],
            'c.created_at',
            'DESC'
        );
    }

    // ── Lookups ────────────────────────────────────────────────────────────────
    // JOIN to users hits users.id (PK) — fast. campaigns.created_by_user_id covered by idx_campaigns_created_by_user_id.

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.campaign_name, c.campaign_type, c.status, c.scheduled_at,
                   c.sent_count,
                   c.created_at, c.updated_at,
                   u.name AS created_by_name
            FROM campaigns c
            LEFT JOIN users u ON u.id = c.created_by_user_id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function allAccounts(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, account_name, tags FROM accounts ORDER BY account_name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function allContacts(): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.first_name, c.last_name, c.account_id, a.account_name
            FROM contacts c
            JOIN accounts a ON a.id = c.account_id
            ORDER BY c.last_name ASC, c.first_name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // ── Audience ───────────────────────────────────────────────────────────────
    // campaign_id lookup hits idx_campaign_audience_campaign_id.
    // JOINs to accounts/contacts use their PKs and FK indexes.

    // Returns raw rows including account_id / contact_id for grouping logic.
    public function getAudienceByCampaignId(int $campaignId): array
    {
        $stmt = $this->db->prepare("
            SELECT ca.id, ca.segment_name, ca.tag_filter,
                   ca.account_id, ca.contact_id,
                   a.account_name,
                   CONCAT(c.first_name, ' ', c.last_name) AS contact_name
            FROM campaign_audience ca
            LEFT JOIN accounts a ON a.id = ca.account_id
            LEFT JOIN contacts c ON c.id = ca.contact_id
            WHERE ca.campaign_id = ?
            ORDER BY ca.segment_name ASC, ca.id ASC
        ");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll();
    }

    // Returns all raw rows for a named segment — used to pre-fill the edit form.
    public function getAudienceSegment(int $campaignId, string $segmentName): array
    {
        $stmt = $this->db->prepare("
            SELECT id, account_id, contact_id, tag_filter, segment_name
            FROM campaign_audience
            WHERE campaign_id = ? AND segment_name = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$campaignId, $segmentName]);
        return $stmt->fetchAll();
    }

    public function insertAudience(int $campaignId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO campaign_audience (campaign_id, account_id, contact_id, tag_filter, segment_name)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $campaignId,
            !empty($data['account_id']) ? (int)$data['account_id'] : null,
            !empty($data['contact_id']) ? (int)$data['contact_id'] : null,
            ($data['tag_filter']   ?? '') !== '' ? $data['tag_filter']   : null,
            ($data['segment_name'] ?? '') !== '' ? $data['segment_name'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    // Removes all rows belonging to a named segment within a campaign.
    public function deleteAudienceBySegment(int $campaignId, string $segmentName): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM campaign_audience WHERE campaign_id = ? AND segment_name = ?"
        );
        $stmt->execute([$campaignId, $segmentName]);
    }

    // ── Audience Presets ───────────────────────────────────────────────────────

    public function allPresets(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, preset_name, segment_name, tag_filter, account_ids, contact_ids
             FROM audience_presets ORDER BY preset_name ASC"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();
        // Decode JSON columns so callers get plain arrays.
        foreach ($rows as &$row) {
            $row['account_ids'] = json_decode($row['account_ids'] ?? '[]', true) ?: [];
            $row['contact_ids'] = json_decode($row['contact_ids'] ?? '[]', true) ?: [];
        }
        return $rows;
    }

    public function findPresetById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, preset_name, segment_name, tag_filter, account_ids, contact_ids
             FROM audience_presets WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['account_ids'] = json_decode($row['account_ids'] ?? '[]', true) ?: [];
        $row['contact_ids'] = json_decode($row['contact_ids'] ?? '[]', true) ?: [];
        return $row;
    }

    public function insertPreset(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO audience_presets
                (preset_name, segment_name, tag_filter, account_ids, contact_ids, created_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['preset_name'],
            $data['segment_name'],
            ($data['tag_filter'] ?? '') !== '' ? $data['tag_filter'] : null,
            json_encode(array_values(array_filter(array_map('intval', $data['account_ids'] ?? [])))),
            json_encode(array_values(array_filter(array_map('intval', $data['contact_ids'] ?? [])))),
            (int)$data['created_by_user_id'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    // Counts the recipients already saved for a campaign.
    // Explicit account/contact IDs are counted directly; tag-filter-only rows trigger
    // a FIND_IN_SET account lookup so the total reflects real DB matches.
    public function countSavedAudience(int $campaignId): array
    {
        $rows = $this->db->prepare("
            SELECT account_id, contact_id, tag_filter
            FROM campaign_audience
            WHERE campaign_id = ?
        ");
        $rows->execute([$campaignId]);
        $entries = $rows->fetchAll();

        $accountIds = [];
        $contactIds = [];
        $tagFilters = [];

        foreach ($entries as $e) {
            if ($e['account_id'] !== null) $accountIds[] = (int)$e['account_id'];
            if ($e['contact_id'] !== null) $contactIds[] = (int)$e['contact_id'];
            if ($e['tag_filter']  !== null) $tagFilters[] = $e['tag_filter'];
        }

        // Reuse previewAudienceCount: merge all tag filters into one string,
        // pass all explicit IDs — it handles deduplication via COUNT(DISTINCT).
        $combinedTag = implode(',', $tagFilters);
        return $this->previewAudienceCount($combinedTag, array_unique($accountIds), array_unique($contactIds));
    }

    // Returns ['accounts' => int, 'contacts' => int] for live preview.
    // Account query uses FIND_IN_SET on the tags column — acceptable at small scale.
    // Contact query hits contacts.id (PK) directly.
    public function previewAudienceCount(string $tagFilter, array $accountIds, array $contactIds): array
    {
        $accountCount = 0;
        $contactCount = 0;

        $aClauses = [];
        $aParams  = [];
        if (!empty($accountIds)) {
            $ph = implode(',', array_fill(0, count($accountIds), '?'));
            $aClauses[] = "id IN ({$ph})";
            array_push($aParams, ...$accountIds);
        }
        foreach (array_filter(array_map('trim', explode(',', $tagFilter))) as $tag) {
            $aClauses[] = 'FIND_IN_SET(?, tags)';
            $aParams[]  = $tag;
        }
        if ($aClauses) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT id) FROM accounts WHERE " . implode(' OR ', $aClauses)
            );
            $stmt->execute($aParams);
            $accountCount = (int)$stmt->fetchColumn();
        }

        if (!empty($contactIds)) {
            $ph = implode(',', array_fill(0, count($contactIds), '?'));
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM contacts WHERE id IN ({$ph})");
            $stmt->execute($contactIds);
            $contactCount = (int)$stmt->fetchColumn();
        }

        return ['accounts' => $accountCount, 'contacts' => $contactCount];
    }

    // ── Writes ─────────────────────────────────────────────────────────────────

    public function insert(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO campaigns (campaign_name, campaign_type, status, scheduled_at, created_by_user_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['campaign_name'],
            $data['campaign_type'],
            $data['status'],
            $data['scheduled_at'] ?? null,
            (int)$data['created_by_user_id'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE campaigns SET campaign_name = ?, campaign_type = ?, status = ?, scheduled_at = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['campaign_name'],
            $data['campaign_type'],
            $data['status'],
            $data['scheduled_at'] ?? null,
            $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM campaigns WHERE id = ?");
        $stmt->execute([$id]);
    }

    // Sets sent_count and flips status to Sent atomically (simulated send).
    public function updateMetrics(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE campaigns
            SET sent_count = ?, status = 'Sent'
            WHERE id = ?
        ");
        $stmt->execute([
            (int)$data['sent_count'],
            $id,
        ]);
    }

    // ── Dashboard Queries ──────────────────────────────────────────────────────
    // idx_campaigns_status_scheduled_at covers (status, scheduled_at) for WHERE + ORDER BY.

    public function upcomingScheduledSends(int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.campaign_name, c.campaign_type, c.scheduled_at,
                   DATEDIFF(c.scheduled_at, NOW()) AS days_until,
                   u.name AS created_by_name
            FROM campaigns c
            LEFT JOIN users u ON u.id = c.created_by_user_id
            WHERE c.status = 'Scheduled' AND c.scheduled_at >= NOW()
            ORDER BY c.scheduled_at ASC
            LIMIT {$limit}
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Scheduled campaigns whose send time has already passed. Sends are simulated
    // manually (there is no cron), so these are stuck until someone acts on them —
    // the mirror image of upcomingScheduledSends, same covering index.
    public function overdueScheduledSends(int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.campaign_name, c.campaign_type, c.scheduled_at,
                   DATEDIFF(NOW(), c.scheduled_at) AS days_overdue,
                   u.name AS created_by_name
            FROM campaigns c
            LEFT JOIN users u ON u.id = c.created_by_user_id
            WHERE c.status = 'Scheduled' AND c.scheduled_at < NOW()
            ORDER BY c.scheduled_at ASC
            LIMIT {$limit}
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Campaigns that have already gone out, newest first (Recent Sends card).
    // Send time mirrors campaignMomentum's activity date: scheduled_at when the
    // campaign was queued, else the row's last write.
    public function recentSends(int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.campaign_name, c.campaign_type, c.status, c.sent_count,
                   COALESCE(c.scheduled_at, c.updated_at, c.created_at) AS sent_at,
                   u.name AS created_by_name
            FROM campaigns c
            LEFT JOIN users u ON u.id = c.created_by_user_id
            WHERE c.status IN ('Sent', 'Completed')
            ORDER BY sent_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Drafts still being put together, oldest first so the stalest surface first.
    // has_audience flags whether any audience row exists at all — a draft with no
    // audience cannot go out, which is what the card badges. EXISTS stops at the
    // first matching row and hits idx_campaign_audience_campaign_id.
    public function draftCampaigns(int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.campaign_name, c.campaign_type, c.created_at,
                   DATEDIFF(NOW(), c.created_at) AS days_old,
                   EXISTS (
                       SELECT 1 FROM campaign_audience ca WHERE ca.campaign_id = c.id
                   ) AS has_audience
            FROM campaigns c
            WHERE c.status = 'Draft'
            ORDER BY c.created_at ASC
            LIMIT {$limit}
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Weekly campaign activity for the momentum chart — last N weeks.
    // $groupBy: 'week' (YEARWEEK buckets) or 'day' (DATE buckets) for short ranges.
    // $segment: 'all' | 'accounts' | 'contacts' — filters to campaigns with that audience type.
    // Hits idx_campaigns_created_at_status; segment subqueries hit idx_campaign_audience_campaign_account/contact.
public function campaignMomentum(
    string $from    = '',
    string $to      = '',
    string $groupBy = 'week',
    string $segment = 'all'
): array {
    if ($from === '') $from = date('Y-m-d', strtotime('-12 weeks'));
    if ($to   === '') $to   = date('Y-m-d 23:59:59');

    $activityDate = "COALESCE(c.scheduled_at, c.updated_at, c.created_at)";

    if ($groupBy === 'day') {
        $groupExpr = "DATE({$activityDate})";
        $labelExpr = "DATE_FORMAT({$activityDate}, '%a %b %d')";
    } else {
        $groupExpr = "YEARWEEK({$activityDate}, 1)";
        $labelExpr = "DATE_FORMAT(MIN({$activityDate}), '%b %d')";
    }

    $segmentClause = '';
    if ($segment === 'accounts') {
        $segmentClause = 'AND c.id IN (
            SELECT DISTINCT campaign_id
            FROM campaign_audience
            WHERE account_id IS NOT NULL
        )';
    } elseif ($segment === 'contacts') {
        $segmentClause = 'AND c.id IN (
            SELECT DISTINCT campaign_id
            FROM campaign_audience
            WHERE contact_id IS NOT NULL
        )';
    }

    $stmt = $this->db->prepare("
        SELECT
            {$groupExpr} AS period_key,
            {$labelExpr} AS period_label,

            COUNT(*) AS campaigns_sent,

            COALESCE(SUM(c.sent_count), 0) AS total_recipients

        FROM campaigns c
        WHERE c.status IN ('Sent', 'Completed')
          AND {$activityDate} BETWEEN ? AND ?
          {$segmentClause}
        GROUP BY {$groupExpr}
        ORDER BY period_key ASC
    ");

    $stmt->execute([$from, $to]);
    return $stmt->fetchAll();
}

    // Summary stats for the dashboard stat cards. Computed in a single pass over
    // campaigns (idx_campaigns_status). "active" = Scheduled or Sent — campaigns
    // that are queued to go out or in-flight, excluding Drafts and Completed.
    //
    // One pass feeds four cards (Active Campaigns, Status Breakdown, Total Reach,
    // Overdue count) — DashboardService memoises the result so they share it.
    // total_reach only counts campaigns that actually went out; a Draft or
    // Scheduled row's sent_count is 0 but should not be treated as reach either.
    public function dashboardStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'Draft')     AS draft,
                SUM(status = 'Scheduled') AS scheduled,
                SUM(status = 'Sent')      AS sent,
                SUM(status = 'Completed') AS completed,
                SUM(status IN ('Scheduled','Sent')) AS active,
                SUM(status IN ('Sent','Completed')) AS sent_completed,
                COALESCE(
                    SUM(CASE WHEN status IN ('Sent','Completed') THEN sent_count END), 0
                ) AS total_reach,
                SUM(status = 'Scheduled' AND scheduled_at < NOW()) AS overdue
            FROM campaigns
        ");
        $stmt->execute();
        return $stmt->fetch() ?: [];
    }
}
