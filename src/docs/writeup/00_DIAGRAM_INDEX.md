# Typhon Cath CRM Diagram Package Index

Project: Typhon Cath CRM
Source documents translated: ../project/requirements.md and requirements_clarification.txt
System type: Lightweight internal CRM, monolithic web application
Stack: HTML/CSS/JavaScript/jQuery/Bootstrap frontend, PHP backend/business logic, MySQL database
Main modules: Customer Management, RFQ/Pipeline Management, Digital Campaign Management, Inventory Management, Integration/Dashboard
Main roles: Admin, Sales User, Marketing User, Inventory Manager

## Purpose
This folder contains Markdown specifications for diagrams that can be converted into draw.io diagrams. Each file is written as a diagram brief, not as a final visual. The goal is to give draw.io enough structure to create polished final drafts.

## Recommended diagram creation order

1. `01_system_context_and_legacy_excel.md`
2. `02_module_architecture.md`
3. `03_erd_data_model.md`
4. `04_navigation_and_wireframe_map.md`
5. `05_role_permission_matrix.md`
6. `06_rfq_pipeline_state_diagram.md`
7. `07_rfq_creation_sequence_diagram.md`
8. `08_inventory_reservation_flow.md`
9. `09_campaign_audience_flow.md`
10. `10_dashboard_data_flow.md`
11. `11_three_tier_deployment_architecture.md`
12. `12_crud_matrix.md`
13. `13_nonfunctional_quality_diagram.md`

## Notes for draw.io conversion

- Use the Mermaid starter blocks when draw.io can import Mermaid.
- If using manual draw.io shapes, use the “Elements / Nodes” and “Relationships / Arrows” sections as the shape list.
- Keep diagrams modular. Avoid placing every requirement into one giant diagram.
- Use consistent colors:
  - Customer module: blue
  - RFQ / Pipeline module: orange
  - Campaign module: purple
  - Inventory module: green
  - Integration / Dashboard: gray
  - Database: cylinder shape
  - External/manual source: dashed border
