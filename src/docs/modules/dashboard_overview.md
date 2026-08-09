app/Modules/Dashboard/

The Dashboard module is shared.

It does not own one specific business area.

Instead, it reads from every module.

Recommended structure:

Dashboard/
├── DashboardController.php
├── DashboardService.php
├── DashboardRepository.php
└── views/
    └── dashboard.php
Dashboard shows
customer count
contact count
active RFQs
RFQs by stage
won/lost RFQs
campaign sent count
campaign open rate
campaign click rate
product count
low-stock products
reserved inventory count
Dashboard flow

Example: User opens dashboard.

dashboard.php
        ↓
DashboardController loads dashboard
        ↓
DashboardService asks for summary metrics
        ↓
DashboardRepository queries multiple tables
        ↓
View renders dashboard cards and charts

The dashboard is where integration becomes visible.

It proves the modules are not isolated.