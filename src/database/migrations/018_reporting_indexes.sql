-- 018_reporting_indexes.sql
-- Closes the two remaining un-indexed heavy paths found in the FP review.
--
--   Recent Interactions dashboard card + Customer interaction history
--   (DashboardRepository / CustomerRepository::interactionsForAccount):
--       FROM interactions ... ORDER BY interaction_date DESC LIMIT ?
--   Only interactions.account_id was indexed, so the global date sort was a
--   filesort over every interaction. idx_interactions_interaction_date turns
--   the ORDER BY ... LIMIT into an index walk.
--
--   Win Rate by Account card + drill-down (RFQRepository::winRateByAccount):
--       FROM rfqs r JOIN accounts a ... GROUP BY a.id
--   The aggregation reads (account_id, stage) per row; a composite index lets
--   the GROUP BY be satisfied from the index rather than scanning full rows.
--   NOTE: the final ORDER BY win_rate_pct is a computed expression, so THAT
--   sort remains a filesort by design — unavoidable without materialising the
--   rate. Acceptable: the grouped result set is small (one row per account).
--
-- Not indexable (documented, accepted at project scale):
--   accounts.tags / campaign_audience.tag_filter via FIND_IN_SET(...) — a CSV
--   membership test cannot use a B-tree index (see CampaignRepository).

CREATE INDEX idx_interactions_interaction_date ON interactions(interaction_date);
CREATE INDEX idx_rfqs_account_stage            ON rfqs(account_id, stage);
