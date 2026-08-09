app/Modules/Campaign/

The Campaign module owns:

campaign creation and tracking
customer segmentation
campaign performance metrics

Recommended structure:

Campaign/
├── CampaignController.php
├── CampaignService.php
├── CampaignRepository.php
├── campaign_routes.php
└── views/
    ├── campaigns_list.php
    ├── campaign_form.php
    ├── audience_selection.php
    └── campaign_metrics.php
Campaign pages
Campaigns List
Campaign Create/Edit Form
Audience Selection
Campaign Metrics
Campaign database ownership

Jonah’s module mainly works with:

campaigns
campaign_audience
campaign metrics

The Campaign module also reads from:

accounts
contacts
tags

because campaigns need customer segmentation.

Campaign module flow

Example: User selects campaign audience.

audience_selection.php
        ↓
CampaignController receives selected filters/tags
        ↓
CampaignService validates audience rules
        ↓
CampaignRepository queries matching customers/contacts
        ↓
View shows audience preview count
        ↓
User confirms campaign audience