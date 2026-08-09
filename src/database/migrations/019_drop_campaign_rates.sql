-- 019_drop_campaign_rates.sql
-- Removes campaign open-rate / click-rate tracking.
--
-- These columns held *simulated* engagement rates (see the old
-- CampaignService::simulateSend). They were not part of the delivered scope and
-- are being retired along with the analytics that fed on them (Campaign
-- Performance card, top-performers / re-engagement / engagement-gap queries, and
-- the momentum chart's rate lines). sent_count is retained.
--
-- Dropping the columns also removes any index built on them
-- (idx_campaigns_open_rate, idx_campaigns_click_rate, and open_rate's slot in
-- idx_campaigns_status_open_rate) — MySQL adjusts dependent indexes automatically.

ALTER TABLE campaigns
    DROP COLUMN open_rate,
    DROP COLUMN click_rate;
