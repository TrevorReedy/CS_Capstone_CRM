-- TyphonCath CRM seed data
-- Safe to re-run. Uses deterministic demo IDs plus ON DUPLICATE KEY UPDATE / INSERT IGNORE.
-- Select the target database before running this file, or run it through db_setup.php.

SET NAMES utf8mb4;

INSERT INTO roles (role_name, description) VALUES
('Super Admin', 'Full system access'),
('Admin', 'Administrative CRM access'),
('Sales User', 'Customer and RFQ pipeline access'),
('Marketing User', 'Campaign management access'),
('Inventory Manager', 'Product and inventory access')
ON DUPLICATE KEY UPDATE
    description = VALUES(description);

-- Demo credentials: admin@typhoncath.test / password
INSERT INTO users (id, name, email, password_hash, role_id) VALUES
(1, 'Demo Admin', 'admin@typhoncath.test', '$2y$10$ZtXz2SAiwlR1ZttmF9EZqesRX1BqN.cgTfmG2bXV4LKU/bK5O8Gi6', (SELECT id FROM roles WHERE role_name = 'Super Admin' LIMIT 1))
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    role_id = VALUES(role_id);

INSERT INTO accounts (id, account_name, email, phone, industry, source, tags) VALUES
(1, 'Demo Hospital', 'contact@demohospital.test', '555-0100', 'Healthcare', 'Seed Data', 'hospital,prospect'),
(2, 'City Medical Center', 'procurement@citymed.test', '555-0201', 'Healthcare', 'Seed Data', 'hospital,active'),
(3, 'Regional Heart Institute', 'supply@heartinstitute.test', '555-0202', 'Cardiology', 'Seed Data', 'specialist,prospect'),
(4, 'St. Luke\'s Hospital', 'orders@stlukes.test', '555-0203', 'Healthcare', 'Seed Data', 'hospital,active'),
(5, 'Northwest Cardiology Group', 'purchasing@nwcardio.test', '555-0204', 'Cardiology', 'Seed Data', 'specialist,active'),
(6, 'Valley General Hospital', 'supply@valleygeneral.test', '555-0205', 'Healthcare', 'Seed Data', 'hospital,prospect'),
(7, 'Summit Ambulatory Surgery Ctr', 'supply@summitasc.test', '555-0301', 'Ambulatory Surgery', 'Trade Show', 'asc,active'),
(8, 'Pacific Vascular Associates', 'procurement@pacvascular.test', '555-0302', 'Vascular Surgery', 'Referral', 'specialist,prospect'),
(9, 'Midlands Research Hospital', 'supply@midlandsresearch.test', '555-0303', 'Academic Medicine', 'Direct', 'research,active'),
(10, 'BlueCross Regional Medical', 'orders@bluecrossreg.test', '555-0304', 'Healthcare', 'Inbound', 'hospital,active'),
(11, 'Sunrise Nursing & Rehab', 'director@sunrisenr.test', '555-0305', 'Long-Term Care', 'Cold Outreach', 'ltc,prospect'),
(12, 'Apex Medical Distributors', 'purchasing@apexmed.test', '555-0306', 'Distribution', 'Partner', 'distributor,active')
ON DUPLICATE KEY UPDATE
    account_name = VALUES(account_name),
    email = VALUES(email),
    phone = VALUES(phone),
    industry = VALUES(industry),
    source = VALUES(source),
    tags = VALUES(tags);

INSERT INTO contacts (id, account_id, first_name, last_name, email, phone, title) VALUES
(1, 1, 'Sandra', 'Lee', 'slee@demohospital.test', '555-1001', 'Procurement Manager'),
(2, 2, 'James', 'Ortega', 'jortega@citymed.test', '555-1002', 'Supply Chain Director'),
(3, 3, 'Maria', 'Chen', 'mchen@heartinstitute.test', '555-1003', 'Purchasing Lead'),
(4, 4, 'David', 'Park', 'dpark@stlukes.test', '555-1004', 'Operations Manager'),
(5, 5, 'Rachel', 'Torres', 'rtorres@nwcardio.test', '555-1005', 'Procurement Officer'),
(6, 6, 'Kevin', 'Nguyen', 'knguyen@valleygeneral.test', '555-1006', 'Supply Coordinator'),
(7, 7, 'Priya', 'Sharma', 'psharma@summitasc.test', '555-1007', 'Director of Surgical Services'),
(8, 8, 'Carlos', 'Mendez', 'cmendez@pacvascular.test', '555-1008', 'Procurement Manager'),
(9, 9, 'Aisha', 'Okonkwo', 'aokonkwo@midlandsresearch.test', '555-1009', 'Research Supply Coordinator'),
(10, 10, 'Tom', 'Wilder', 'twilder@bluecrossreg.test', '555-1010', 'Supply Chain Director'),
(11, 11, 'Linda', 'Hoffman', 'lhoffman@sunrisenr.test', '555-1011', 'Nursing Director'),
(12, 12, 'Bryan', 'Cho', 'bcho@apexmed.test', '555-1012', 'Key Account Manager')
ON DUPLICATE KEY UPDATE
    account_id = VALUES(account_id),
    first_name = VALUES(first_name),
    last_name = VALUES(last_name),
    email = VALUES(email),
    phone = VALUES(phone),
    title = VALUES(title);

-- ── Interactions ─────────────────────────────────────────────────────────────
-- Logged customer touchpoints (calls/emails/notes/meetings) across accounts and
-- their contacts. Dates are RELATIVE to NOW() so the "Recent Interactions"
-- dashboard card always shows fresh, correctly-ordered activity no matter when
-- the seed is run. Types are mixed so the card renders all four badge colours.
INSERT INTO interactions (id, account_id, contact_id, user_id, interaction_type, interaction_date, interaction_subject, notes) VALUES
(1,  2,  2,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'call',    NOW() - INTERVAL 3 HOUR, 'Follow-up on ICU bulk order',        'Called James to confirm the delivery window for the ICU expansion order. Wants the shipment split across two weeks.'),
(2,  5,  5,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'email',   NOW() - INTERVAL 8 HOUR, 'Sent coronary bundle quote',         'Emailed Rachel the revised coronary catheter bundle quote with the 5% volume discount applied.'),
(3,  10, 10,   (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'meeting', NOW() - INTERVAL 1 DAY,  'Multi-year urinary catheter review', 'On-site meeting with Tom to walk through the three-year framework terms. Legal is reviewing the pricing schedule.'),
(4,  3,  3,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'note',    NOW() - INTERVAL 2 DAY,  'Hybrid OR kit specs confirmed',      'Maria confirmed the specialty kit configuration. Awaiting internal budget sign-off before we quote.'),
(5,  8,  8,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'call',    NOW() - INTERVAL 3 DAY,  'PICC + midline bundle negotiation',  'Carlos is pushing for better 12-month pricing. Offered to hold the current rate if they commit by month end.'),
(6,  1,  1,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'email',   NOW() - INTERVAL 5 DAY,  'Q3 reorder confirmation',            'Sent Sandra the Q3 catheter reorder summary for the ICU ward. Confirmed standard quarterly volumes.'),
(7,  12, 12,   (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'meeting', NOW() - INTERVAL 6 DAY,  'Distributor partnership kickoff',    'Kickoff with Bryan on the three-state distribution agreement. Aligned on catalogue and onboarding timeline.'),
(8,  6,  6,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'note',    NOW() - INTERVAL 9 DAY,  'Emergency restock logged',           'Logged an urgent restock request from Kevin after an inventory shortage. Flagged for expedited fulfilment.'),
(9,  9,  9,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'call',    NOW() - INTERVAL 12 DAY, 'Dialysis catheter trial check-in',   'Aisha reports positive early results in the hemodialysis access study. Will share interim data next month.'),
(10, 4,  4,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'email',   NOW() - INTERVAL 15 DAY, 'Annual contract renewal terms',      'Sent David the 2026 renewal terms. He requested a call to discuss the price-escalation clause.'),
(11, 7,  7,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'meeting', NOW() - INTERVAL 18 DAY, 'ASC vascular sheath walkthrough',    'Product walkthrough with Priya for the OR suite sheath package. Positive feedback on the introducer kit.'),
(12, 11, 11,   (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'call',    NOW() - INTERVAL 22 DAY, 'Home care programme logistics',      'Linda outlined monthly delivery needs for 60 discharged patients. Discussed a recurring order setup.'),
(13, 2,  NULL, (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'note',    NOW() - INTERVAL 27 DAY, 'Account-wide procurement note',      'Noted that City Medical is consolidating procurement across all three sites — expect larger volumes.'),
(14, 5,  5,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'email',   NOW() - INTERVAL 33 DAY, 'Coronary intervention pact intro',   'Introduced the preferred-vendor agreement for coronary guide catheters. Rachel is forwarding it to committee.'),
(15, 3,  3,    (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'meeting', NOW() - INTERVAL 39 DAY, 'Volume deal negotiation session',    'Full-year volume pricing negotiation with Maria. Narrowed to two tiers; awaiting a final commitment.'),
(16, 6,  NULL, (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 'note',    NOW() - INTERVAL 45 DAY, 'Pilot pause recorded',               'Recorded that Valley General paused the pilot program pending its budget cycle. Follow up in Q4.')
ON DUPLICATE KEY UPDATE
    account_id = VALUES(account_id),
    contact_id = VALUES(contact_id),
    user_id = VALUES(user_id),
    interaction_type = VALUES(interaction_type),
    interaction_date = VALUES(interaction_date),
    interaction_subject = VALUES(interaction_subject),
    notes = VALUES(notes);

INSERT INTO products (id, product_name, sku, price, description) VALUES
(1, 'Typhon Catheter Demo Product', 'TYPHON-DEMO-001', '499.00', 'Demo product for CRM inventory.'),
(2, 'Triple-Lumen Central Venous Catheter Kit', 'CVC-3L-001', '285.00', 'Triple-lumen CVC kit for central venous access; 7 Fr, 20 cm.'),
(3, 'PICC Line Kit — Power Injectable', 'PICC-PWR-002', '198.00', 'Power-injectable PICC kit with introducer needle and guidewire.'),
(4, 'Foley Catheter 16 Fr Silicone', 'FOLEY-16-003', '42.50', 'All-silicone indwelling Foley catheter, 16 Fr, 10 mL balloon.'),
(5, 'Arterial Line Catheter 20 Ga', 'ART-LINE-004', '87.00', 'Radial arterial line catheter, 20 Ga, with pressure transducer tubing.'),
(6, 'Midline Catheter Kit 4 Fr', 'MIDLINE-4F-005', '76.00', 'Single-lumen midline catheter kit for extended peripheral access.'),
(7, 'Hemodialysis Catheter Dual-Lumen', 'HD-DL-006', '320.00', 'Dual-lumen tunnelled hemodialysis catheter, 14.5 Fr.'),
(8, 'Nasogastric Feeding Tube 12 Fr', 'NG-12-007', '18.75', 'Radiopaque PVC nasogastric feeding tube, 12 Fr, 120 cm.'),
(9, 'Peritoneal Dialysis Catheter', 'PD-CATH-008', '410.00', 'Coiled-tip peritoneal dialysis catheter with dual polyester cuffs.'),
(10, 'Suprapubic Catheter 14 Fr', 'SUPRA-14-009', '58.00', 'All-silicone suprapubic drainage catheter, 14 Fr, 10 mL balloon.'),
(11, 'Pigtail Drainage Catheter 8 Fr', 'PIGTAIL-8-010', '64.50', 'Locking pigtail drainage catheter, 8 Fr, for percutaneous drainage.'),
(12, 'Introducer Sheath 6 Fr', 'SHEATH-6F-011', '92.00', 'Radial/femoral introducer sheath, 6 Fr, with hemostasis valve.'),
(13, 'Guide Catheter JL4 6 Fr', 'GUIDE-JL4-012', '145.00', 'Judkins Left 4.0 coronary guide catheter, 6 Fr.'),
(14, 'Balloon Dilatation Catheter', 'BALLOON-013', '268.00', 'Semi-compliant PTCA balloon dilatation catheter, rapid-exchange.'),
(15, 'Thermodilution Swan-Ganz Catheter', 'SWAN-GANZ-014', '540.00', '7.5 Fr thermodilution pulmonary artery (Swan-Ganz) catheter.'),
(16, 'Umbilical Vessel Catheter 3.5 Fr', 'UVC-35-015', '39.00', 'Neonatal umbilical vessel catheter, 3.5 Fr, single lumen.'),
(17, 'Epidural Catheter Kit 18 Ga', 'EPI-18-016', '71.00', 'Closed-tip epidural catheter kit with Tuohy needle, 18 Ga.'),
(18, 'Chest Drainage Catheter 28 Fr', 'CHEST-28-017', '84.00', 'Straight chest drainage (thoracostomy) catheter, 28 Fr.'),
(19, 'Tunneled CVC 9 Fr', 'TUN-CVC-9-018', '375.00', 'Dual-lumen tunnelled central venous catheter, 9 Fr, with cuff.'),
(20, 'Yankauer Suction Catheter', 'YANK-019', '6.25', 'Rigid Yankauer oral suction catheter, sterile, single-use.')
ON DUPLICATE KEY UPDATE
    product_name = VALUES(product_name),
    sku = VALUES(sku),
    price = VALUES(price),
    description = VALUES(description);

-- available_quantity / reserved_quantity are tuned to exercise the dashboard cards:
--   Low Stock  (available <= 10):        7, 8, 10, 11, 14, 16, 19, 20
--   Heavily Reserved (>70% reserved):    7 (92%), 9 (75%), 12 (75%), 14 (77%), 19 (83%)
--   Top Reserved (most units reserved):  9, 7, 12, 19, 14
-- reserved_quantity for each product equals the sum of its 'Reserved' rows in
-- rfq_inventory_reservations below, matching how the reserve flow keeps them in sync.
INSERT INTO inventory (id, product_id, available_quantity, reserved_quantity) VALUES
(1, 1, 100, 0),
(2, 2, 250, 12),
(3, 3, 480, 0),
(4, 4, 900, 20),
(5, 5, 160, 5),
(6, 6, 220, 8),
(7, 7, 4, 46),
(8, 8, 4, 0),
(9, 9, 20, 60),
(10, 10, 9, 2),
(11, 11, 6, 0),
(12, 12, 15, 45),
(13, 13, 140, 15),
(14, 14, 10, 34),
(15, 15, 60, 6),
(16, 16, 0, 0),
(17, 17, 300, 10),
(18, 18, 95, 4),
(19, 19, 8, 40),
(20, 20, 7, 0)
ON DUPLICATE KEY UPDATE
    product_id = VALUES(product_id),
    available_quantity = VALUES(available_quantity),
    reserved_quantity = VALUES(reserved_quantity);

INSERT INTO rfqs (id, account_id, contact_id, created_by_user_id, title, description, stage, created_at) VALUES
(1, 1, 1, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Q3 Catheter Supply Request', 'Standard quarterly reorder for ICU ward.', 'New', '2026-06-17 09:00:00'),
(2, 2, 2, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'ICU Catheter Bulk Order', 'Bulk order for new ICU expansion unit.', 'New', '2026-06-15 10:30:00'),
(3, 3, 3, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Hybrid OR Catheter Kit', 'Specialty kit for new hybrid operating room.', 'New', '2026-06-18 14:00:00'),
(4, 3, 3, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Cardiac Catheter Package', 'Annual package for cath lab procedures.', 'In Review', '2026-06-05 08:00:00'),
(5, 4, 4, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Annual Contract Renewal', 'Renewal of 2025 catheter supply contract.', 'In Review', '2026-06-08 11:00:00'),
(6, 6, 6, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Emergency Supply Order', 'Urgent restock following inventory shortage.', 'In Review', '2026-06-01 09:30:00'),
(7, 5, 5, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Coronary Catheter Bundle', 'Discounted bundle for coronary procedures.', 'Quoted', '2026-05-10 09:00:00'),
(8, 6, 6, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Pediatric Catheter Order', 'Pediatric sizing range for new ward.', 'Quoted', '2026-05-20 13:00:00'),
(9, 1, 1, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Catheter Consumables Q2', 'Consumables top-up order.', 'Quoted', '2026-05-01 10:00:00'),
(10, 2, 2, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Multi-site Supply Agreement', 'Agreement covering three City Medical locations.', 'Negotiation', '2026-04-28 08:00:00'),
(11, 3, 3, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Annual Volume Deal', 'Volume pricing negotiation for full-year supply.', 'Negotiation', '2026-04-20 09:00:00'),
(12, 4, 4, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Catheter Supply Contract 2026', 'Signed annual supply contract.', 'Won', '2026-02-10 09:00:00'),
(13, 5, 5, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Specialty Catheter Package', 'Specialty package for cardiology department.', 'Won', '2026-03-05 10:00:00'),
(14, 6, 6, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Budget Catheter Tender', 'Lost to lower-cost competitor.', 'Lost', '2026-01-15 09:00:00'),
(15, 1, 1, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Pilot Program Order', 'Pilot declined — customer paused procurement.', 'Lost', '2026-02-20 11:00:00'),
(16, 7, 7, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'PICC Line Kit Requisition', '500-unit PICC kit order for oncology IV therapy programme.', 'New', '2026-06-20 08:15:00'),
(17, 8, 8, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Vascular Access Catheter Trial', 'Comparing Typhon CVC against current supplier across two ICU bays.', 'New', '2026-06-19 10:00:00'),
(18, 9, 9, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Dialysis Catheter Evaluation', 'Research trial kit for hemodialysis access study — 6-month window.', 'New', '2026-06-18 13:30:00'),
(19, 10, 10, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Central Venous Catheter Restock', 'Urgent restock of triple-lumen CVCs following supply chain delay.', 'New', '2026-06-17 09:45:00'),
(20, 11, 11, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Foley Catheter Annual Agreement', 'Annual Foley supply for 180-bed long-term care facility.', 'In Review', '2026-06-09 09:00:00'),
(21, 12, 12, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'GPO Evaluation — Arterial Lines', 'Distributor evaluating Typhon arterials for GPO catalogue inclusion.', 'In Review', '2026-06-06 14:00:00'),
(22, 7, 7, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'ASC Vascular Sheath Package', 'Quarterly vascular sheath and introducer kit for OR suite 3 & 4.', 'In Review', '2026-06-03 11:30:00'),
(23, 4, 4, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Peritoneal Dialysis Catheter RFQ', 'Evaluating Typhon PD catheters for new dialysis unit expansion.', 'In Review', '2026-05-29 10:00:00'),
(24, 8, 8, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Peripherally Inserted Line Bundle', 'Negotiated bundle: PICC + midline kits for 12-month supply.', 'Quoted', '2026-05-22 09:00:00'),
(25, 12, 12, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Distributor Bulk Catheter Order', 'High-volume mixed catheter order for Q3 distributor allocation.', 'Quoted', '2026-05-16 13:00:00'),
(26, 9, 9, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Hemodialysis Access Kit — Q2', 'Tunnelled dialysis catheter kits for 40-patient cohort study.', 'Quoted', '2026-05-11 10:30:00'),
(27, 3, 3, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Hybrid Cath Lab Supply Package', 'Specialty catheter and sheath bundle for expanded hybrid OR suite.', 'Quoted', '2026-05-06 08:00:00'),
(28, 10, 10, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Multi-Year Urinary Catheter Deal', 'Three-year supply agreement across two BlueCross campuses.', 'Negotiation', '2026-04-18 09:00:00'),
(29, 5, 5, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Coronary Intervention Supply Pact', 'Preferred-vendor agreement for coronary guide catheters.', 'Negotiation', '2026-04-12 10:00:00'),
(30, 11, 11, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Home Care Catheter Programme', 'Monthly intermittent catheter delivery for 60 discharged patients.', 'Negotiation', '2026-04-07 14:00:00'),
(31, 7, 7, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Q1 PICC Line Contract', 'Signed 6-month PICC supply agreement for oncology ward.', 'Won', '2026-01-22 09:00:00'),
(32, 9, 9, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Dialysis Supply Agreement 2026', 'Annual hemodialysis catheter contract — full year supply secured.', 'Won', '2026-02-12 11:00:00'),
(33, 10, 10, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Foley Catheter Framework Deal', 'Two-year Foley catheter framework signed at contracted pricing.', 'Won', '2026-03-08 09:00:00'),
(34, 12, 12, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Typhon Distribution Partnership', 'Exclusive distribution agreement for three-state territory.', 'Won', '2026-01-10 10:00:00'),
(35, 8, 8, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Vascular Access Open Tender 2026', 'Lost on price — competitor offered 18% lower unit cost.', 'Lost', '2026-02-25 09:00:00'),
(36, 6, 6, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'Pediatric Catheter Rebid', 'Customer re-awarded to incumbent — switching costs too high.', 'Lost', '2026-03-18 13:00:00'),
(37, 12, 12, (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 'National GPO Bid Q1', 'GPO awarded contract to alternative supplier; revisit Q4.', 'Lost', '2026-01-28 11:00:00')
ON DUPLICATE KEY UPDATE
    account_id = VALUES(account_id),
    contact_id = VALUES(contact_id),
    created_by_user_id = VALUES(created_by_user_id),
    title = VALUES(title),
    description = VALUES(description),
    stage = VALUES(stage),
    created_at = VALUES(created_at);

INSERT INTO quotes (id, rfq_id, quote_amount, discount, validity_start_date, validity_end_date) VALUES
-- Active-pipeline quotes (RFQs in Quoted/Negotiation) use validity_end_date
-- RELATIVE to CURDATE() so the "Expiring Quotes" card always shows a realistic
-- mix of soon-to-expire and a couple of overdue quotes. Won/Lost quotes below
-- keep fixed historical dates (they never appear in the expiring-soon widget).
(1, 7, '45000.00', '5.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 2 DAY),
(2, 8, '12500.00', '0.00', CURDATE() - INTERVAL 21 DAY, CURDATE() - INTERVAL 1 DAY),
(3, 9, '78000.00', '8.00', CURDATE() - INTERVAL 30 DAY, CURDATE() - INTERVAL 5 DAY),
(4, 10, '220000.00', '10.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 25 DAY),
(5, 11, '95000.00', '7.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 6 DAY),
(6, 12, '135000.00', '5.00', '2026-04-01', '2026-05-01'),
(7, 13, '67500.00', '3.00', '2026-03-15', '2026-04-15'),
(8, 14, '28000.00', '2.00', '2026-02-01', '2026-03-01'),
(9, 15, '15000.00', '0.00', '2026-01-15', '2026-02-15'),
(10, 24, '38500.00', '4.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 20 DAY),
(11, 25, '182000.00', '6.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 9 DAY),
(12, 26, '54000.00', '3.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 30 DAY),
(13, 27, '97500.00', '5.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 4 DAY),
(14, 28, '310000.00', '12.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 38 DAY),
(15, 29, '124000.00', '8.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 16 DAY),
(16, 30, '21600.00', '0.00', CURDATE() - INTERVAL 21 DAY, CURDATE() + INTERVAL 12 DAY),
(17, 31, '44000.00', '3.00', '2026-01-15', '2026-02-15'),
(18, 32, '168000.00', '7.00', '2026-02-01', '2026-03-01'),
(19, 33, '89000.00', '5.00', '2026-02-20', '2026-03-20'),
(20, 34, '475000.00', '10.00', '2026-01-05', '2026-02-05'),
(21, 35, '61000.00', '2.00', '2026-02-01', '2026-03-01'),
(22, 36, '19500.00', '0.00', '2026-03-01', '2026-04-01'),
(23, 37, '290000.00', '9.00', '2026-01-10', '2026-02-10')
ON DUPLICATE KEY UPDATE
    rfq_id = VALUES(rfq_id),
    quote_amount = VALUES(quote_amount),
    discount = VALUES(discount),
    validity_start_date = VALUES(validity_start_date),
    validity_end_date = VALUES(validity_end_date);

INSERT INTO campaigns (id, campaign_name, campaign_type, status, created_by_user_id, sent_count, created_at, updated_at) VALUES
(1, 'Q2 Hospital Outreach', 'Email', 'Completed', (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 6, '2026-04-15 09:00:00', '2026-04-15 11:30:00'),
(2, 'Catheter Product Launch 2026', 'Email', 'Sent', (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 4, '2026-05-20 10:00:00', '2026-05-20 12:00:00'),
(3, 'Q3 Prospect Warm-Up', 'Email', 'Scheduled', (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 0, '2026-06-10 08:00:00', '2026-06-10 08:00:00'),
(4, 'SMS Supply Alert — June', 'SMS Simulation', 'Draft', (SELECT id FROM users WHERE email = 'admin@typhoncath.test' LIMIT 1), 0, '2026-06-22 14:00:00', '2026-06-22 14:00:00')
ON DUPLICATE KEY UPDATE
    campaign_name = VALUES(campaign_name),
    campaign_type = VALUES(campaign_type),
    status = VALUES(status),
    created_by_user_id = VALUES(created_by_user_id),
    sent_count = VALUES(sent_count),
    created_at = VALUES(created_at),
    updated_at = VALUES(updated_at);

INSERT INTO campaign_audience (id, campaign_id, account_id, contact_id, tag_filter, segment_name) VALUES
(1, 1, 1, NULL, 'hospital', 'Hospital Accounts'),
(2, 1, 2, NULL, NULL, 'Hospital Accounts'),
(3, 1, 4, NULL, NULL, 'Hospital Accounts'),
(4, 1, NULL, 1, NULL, 'Hospital Contacts'),
(5, 1, NULL, 2, NULL, 'Hospital Contacts'),
(6, 1, NULL, 4, NULL, 'Hospital Contacts'),
(7, 2, NULL, NULL, 'active', 'Active Accounts'),
(8, 3, NULL, NULL, 'prospect', 'Prospect Accounts'),
(9, 4, 12, NULL, 'distributor', 'Distributors'),
(10, 4, NULL, NULL, 'specialist', 'Specialist Groups')
ON DUPLICATE KEY UPDATE
    campaign_id = VALUES(campaign_id),
    account_id = VALUES(account_id),
    contact_id = VALUES(contact_id),
    tag_filter = VALUES(tag_filter),
    segment_name = VALUES(segment_name);


-- ── Campaign enrichment (IDs 5–30) ──────────────────────────────────────────
-- 26 campaigns across 12 weeks to populate the momentum chart and the campaigns
-- list. IDs 27–29 are Scheduled with a future scheduled_at to feed the
-- "Upcoming Campaign Sends" card.

INSERT INTO campaigns (id, campaign_name, campaign_type, status, created_by_user_id, sent_count, scheduled_at, created_at, updated_at) VALUES
-- Week 1 (Apr 7) ──────────────────────────────────────────────────────────────
(5,  'Spring Cardiology Outreach',      'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 8,  NULL,                  '2026-04-07 09:00:00', '2026-04-07 14:00:00'),
(6,  'PICC Line Clinical Update',       'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 10, NULL,                  '2026-04-08 10:00:00', '2026-04-08 13:30:00'),
-- Week 2 (Apr 14) ─────────────────────────────────────────────────────────────
(7,  'ICU Supply Newsletter Q2',        'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 12, NULL,                  '2026-04-14 09:00:00', '2026-04-14 15:00:00'),
(8,  'SMS: Foley Restock Alert',        'SMS Simulation', 'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 6,  NULL,                  '2026-04-15 11:00:00', '2026-04-15 12:00:00'),
-- Week 3 (Apr 21) ─────────────────────────────────────────────────────────────
(9,  'Vascular Access Product Brief',   'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 9,  NULL,                  '2026-04-21 09:30:00', '2026-04-21 14:00:00'),
(10, 'Specialty Catheter Feature',      'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 7,  NULL,                  '2026-04-22 10:00:00', '2026-04-22 13:00:00'),
-- Week 4 (Apr 28) ─────────────────────────────────────────────────────────────
(11, 'Q2 Win-Back: Lapsed Contacts',    'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 11, NULL,                  '2026-04-28 09:00:00', '2026-04-28 14:00:00'),
(12, 'SMS: Conference Reminder',        'SMS Simulation', 'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 8,  NULL,                  '2026-04-29 08:00:00', '2026-04-29 10:00:00'),
-- Week 5 (May 5) ──────────────────────────────────────────────────────────────
(13, 'New Product Announcement',        'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 15, NULL,                  '2026-05-06 09:00:00', '2026-05-06 16:00:00'),
(14, 'Dialysis Catheter Whitepaper',    'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 6,  NULL,                  '2026-05-07 10:00:00', '2026-05-07 13:30:00'),
-- Week 6 (May 12) ─────────────────────────────────────────────────────────────
(15, 'May Hospital Segment Push',       'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 10, NULL,                  '2026-05-12 09:00:00', '2026-05-12 14:00:00'),
(16, 'SMS: Q2 Clinical Trial Alert',   'SMS Simulation', 'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 7,  NULL,                  '2026-05-13 10:00:00', '2026-05-13 12:00:00'),
-- Week 7 (May 19) ─────────────────────────────────────────────────────────────
(17, 'Cardiology Conference Follow-up', 'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 9,  NULL,                  '2026-05-19 08:00:00', '2026-05-19 15:00:00'),
(18, 'Distributor Partner Update',      'Email',          'Sent',      (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 5,  NULL,                  '2026-05-20 10:00:00', '2026-05-20 12:30:00'),
-- Week 8 (May 26) ─────────────────────────────────────────────────────────────
(19, 'Arterial Line Technical Brief',   'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 8,  NULL,                  '2026-05-26 09:00:00', '2026-05-26 13:00:00'),
(20, 'SMS: Inventory Alert June',       'SMS Simulation', 'Sent',      (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 12, NULL,                  '2026-05-27 11:00:00', '2026-05-27 12:00:00'),
-- Week 9 (Jun 2) ──────────────────────────────────────────────────────────────
(21, 'June Surgical Suite Campaign',    'Email',          'Completed', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 11, NULL,                  '2026-06-03 09:00:00', '2026-06-03 14:00:00'),
(22, 'CVC Clinical Evidence Pack',      'Email',          'Sent',      (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 8,  NULL,                  '2026-06-04 10:00:00', '2026-06-04 13:00:00'),
-- Week 10 (Jun 9) ─────────────────────────────────────────────────────────────
(23, 'Q3 Hospital Pipeline Activation', 'Email',          'Sent',      (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 9,  NULL,                  '2026-06-10 09:00:00', '2026-06-10 12:00:00'),
(24, 'SMS: New Sales Rep Introduction', 'SMS Simulation', 'Sent',      (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 10, NULL,                  '2026-06-11 10:00:00', '2026-06-11 11:30:00'),
-- Week 11 (Jun 16) ────────────────────────────────────────────────────────────
(25, 'Mid-Year Loyalty Reward Email',   'Email',          'Sent',      (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 7,  NULL,                  '2026-06-17 09:00:00', '2026-06-17 13:00:00'),
(26, 'Catheter Safety Bulletin',        'Email',          'Sent',      (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 6,  NULL,                  '2026-06-18 10:00:00', '2026-06-18 12:00:00'),
-- Week 12 (Jun 23) — upcoming scheduled sends ─────────────────────────────────
-- scheduled_at is RELATIVE to NOW() so these always stay in the future and keep
-- the "Upcoming Campaign Sends" card populated regardless of when the seed runs.
(27, 'Q3 Prospect Welcome Series',      'Email',          'Scheduled', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 0,  NOW() + INTERVAL 7 DAY,  '2026-06-24 10:00:00', '2026-06-24 10:00:00'),
(28, 'SMS: Summer Product Preview',     'SMS Simulation', 'Scheduled', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 0,  NOW() + INTERVAL 3 DAY,  '2026-06-25 09:00:00', '2026-06-25 09:00:00'),
(29, 'Cardiology Q3 Annual Review',     'Email',          'Scheduled', (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 0,  NOW() + INTERVAL 14 DAY, '2026-06-26 08:00:00', '2026-06-26 08:00:00'),
(30, 'Q3 Full Territory Campaign',      'Email',          'Draft',     (SELECT id FROM users WHERE email='admin@typhoncath.test' LIMIT 1), 0,  NULL,                  '2026-06-26 14:00:00', '2026-06-26 14:00:00')
ON DUPLICATE KEY UPDATE
    campaign_name  = VALUES(campaign_name),
    campaign_type  = VALUES(campaign_type),
    status         = VALUES(status),
    sent_count     = VALUES(sent_count),
    scheduled_at   = VALUES(scheduled_at),
    created_at     = VALUES(created_at),
    updated_at     = VALUES(updated_at);

-- Campaign audience for enriched campaigns (IDs 11–50)
-- Zero-click campaigns 8, 11, 15, 20 are seeded with overlapping contacts/accounts
-- so the re-engagement panel surfaces clear priority tiers:
--   Kevin Nguyen / Valley General (acct 6)  → 4 cold campaigns → High
--   Linda Hoffman / Sunrise NR (acct 11)    → 3 cold campaigns → High
--   Maria Chen / Regional Heart (acct 3)    → 2 cold campaigns → Medium

INSERT INTO campaign_audience (id, campaign_id, account_id, contact_id, tag_filter, segment_name) VALUES
-- Campaign 7: ICU Newsletter (drop-off — 71.4% open, 8.3% click)
(11, 7, 1,    NULL, NULL,          'Hospital Accounts'),
(12, 7, 2,    NULL, NULL,          'Hospital Accounts'),
(13, 7, 4,    NULL, NULL,          'Hospital Accounts'),
(14, 7, NULL, 2,    NULL,          'Hospital Contacts'),
(15, 7, NULL, 4,    NULL,          'Hospital Contacts'),
-- Campaign 8: SMS Foley Alert (zero click → re-engagement)
(16, 8, NULL, 6,    NULL,          'LTC & Supply Contacts'),
(17, 8, NULL, 11,   NULL,          'LTC & Supply Contacts'),
(18, 8, 6,    NULL, NULL,          'LTC & Supply Accounts'),
(19, 8, 11,   NULL, NULL,          'LTC & Supply Accounts'),
-- Campaign 11: Q2 Win-Back (null click → re-engagement)
(20, 11, NULL, 3,   NULL,          'Lapsed Contacts'),
(21, 11, NULL, 6,   NULL,          'Lapsed Contacts'),
(22, 11, NULL, 11,  NULL,          'Lapsed Contacts'),
(23, 11, 3,    NULL, NULL,         'Lapsed Accounts'),
(24, 11, 6,    NULL, NULL,         'Lapsed Accounts'),
-- Campaign 13: New Product Announcement (top performer)
(25, 13, NULL, NULL, 'active',     'All Active Accounts'),
-- Campaign 15: May Hospital Push (null click → re-engagement)
(26, 15, NULL, 3,   NULL,          'Hospital Contacts'),
(27, 15, NULL, 6,   NULL,          'Hospital Contacts'),
(28, 15, 3,    NULL, NULL,         'Hospital Accounts'),
(29, 15, 6,    NULL, NULL,         'Hospital Accounts'),
-- Campaign 16: SMS Clinical Trial Alert (top performer SMS)
(30, 16, NULL, NULL, 'specialist', 'Specialist Segment'),
-- Campaign 17: Cardiology Conference Follow-up (drop-off — 88.9% open, 11.1% click)
(31, 17, NULL, 3,   NULL,          'Cardiology Contacts'),
(32, 17, NULL, 5,   NULL,          'Cardiology Contacts'),
(33, 17, 3,    NULL, NULL,         'Cardiology Accounts'),
(34, 17, 5,    NULL, NULL,         'Cardiology Accounts'),
-- Campaign 18: Distributor Partner Update (top performer — 60% open, 40% click)
(35, 18, 12,   NULL, 'distributor','Distributors'),
(36, 18, NULL, 12,  NULL,          'Distributor Contacts'),
-- Campaign 20: SMS Inventory Alert (zero click → re-engagement)
(37, 20, NULL, 6,   NULL,          'Supply Contacts'),
(38, 20, NULL, 11,  NULL,          'LTC Contacts'),
(39, 20, 6,    NULL, NULL,         'Supply Accounts'),
(40, 20, 11,   NULL, NULL,         'LTC Accounts'),
-- Campaign 21: June Surgical Suite Campaign
(41, 21, 7,    NULL, NULL,         'Surgical Accounts'),
(42, 21, NULL, 7,   NULL,          'Surgical Contacts'),
-- Campaign 22: CVC Evidence Pack (drop-off — 75% open, 12.5% click)
(43, 22, NULL, NULL, 'hospital',   'Hospital Segment'),
-- Campaign 23: Q3 Hospital Pipeline Activation
(44, 23, NULL, NULL, 'active',     'Active Hospitals'),
-- Campaign 25: Mid-Year Loyalty Reward
(45, 25, NULL, NULL, 'active',     'Active Accounts'),
-- Campaign 26: Catheter Safety Bulletin (drop-off — 83.3% open, 16.7% click)
(46, 26, NULL, NULL, 'hospital',   'All Hospital Segment'),
-- Scheduled campaigns
(47, 27, NULL, NULL, 'prospect',   'Prospect Segment'),
(48, 28, NULL, NULL, 'active',     'Active SMS List'),
(49, 29, NULL, 3,    NULL,         'Cardiology Contacts'),
(50, 29, NULL, 5,    NULL,         'Cardiology Contacts')
ON DUPLICATE KEY UPDATE
    campaign_id  = VALUES(campaign_id),
    account_id   = VALUES(account_id),
    contact_id   = VALUES(contact_id),
    tag_filter   = VALUES(tag_filter),
    segment_name = VALUES(segment_name);

-- Role permissions. Uses role_name lookup instead of hard-coded role IDs.

INSERT IGNORE INTO role_permissions (role_id, permission)
SELECT r.id, p.permission
FROM roles r
JOIN (
        SELECT 'dashboard.view' AS permission
        UNION ALL SELECT 'customers.view' AS permission
        UNION ALL SELECT 'customers.create' AS permission
        UNION ALL SELECT 'customers.edit' AS permission
        UNION ALL SELECT 'customers.delete' AS permission
        UNION ALL SELECT 'contacts.view' AS permission
        UNION ALL SELECT 'contacts.create' AS permission
        UNION ALL SELECT 'contacts.edit' AS permission
        UNION ALL SELECT 'contacts.delete' AS permission
        UNION ALL SELECT 'interactions.view' AS permission
        UNION ALL SELECT 'interactions.create' AS permission
        UNION ALL SELECT 'interactions.edit' AS permission
        UNION ALL SELECT 'interactions.delete' AS permission
        UNION ALL SELECT 'rfqs.view' AS permission
        UNION ALL SELECT 'rfqs.create' AS permission
        UNION ALL SELECT 'rfqs.edit' AS permission
        UNION ALL SELECT 'rfqs.delete' AS permission
        UNION ALL SELECT 'rfqs.update_stage' AS permission
        UNION ALL SELECT 'quotes.view' AS permission
        UNION ALL SELECT 'quotes.create' AS permission
        UNION ALL SELECT 'quotes.edit' AS permission
        UNION ALL SELECT 'quotes.delete' AS permission
        UNION ALL SELECT 'reservations.view' AS permission
        UNION ALL SELECT 'reservations.create' AS permission
        UNION ALL SELECT 'reservations.update_status' AS permission
        UNION ALL SELECT 'campaigns.view' AS permission
        UNION ALL SELECT 'campaigns.create' AS permission
        UNION ALL SELECT 'campaigns.edit' AS permission
        UNION ALL SELECT 'campaigns.delete' AS permission
        UNION ALL SELECT 'campaigns.metrics' AS permission
        UNION ALL SELECT 'inventory.view' AS permission
        UNION ALL SELECT 'inventory.create' AS permission
        UNION ALL SELECT 'inventory.edit' AS permission
        UNION ALL SELECT 'inventory.update_stock' AS permission
        UNION ALL SELECT 'inventory.reserve' AS permission
        UNION ALL SELECT 'reports.view' AS permission
        UNION ALL SELECT 'admin.manage_users' AS permission
        UNION ALL SELECT 'admin.manage_roles' AS permission
        UNION ALL SELECT 'admin.manage_permissions' AS permission
        UNION ALL SELECT 'references.view' AS permission
) p
WHERE r.role_name = 'Super Admin';

INSERT IGNORE INTO role_permissions (role_id, permission)
SELECT r.id, p.permission
FROM roles r
JOIN (
        SELECT 'dashboard.view' AS permission
        UNION ALL SELECT 'customers.view' AS permission
        UNION ALL SELECT 'customers.create' AS permission
        UNION ALL SELECT 'customers.edit' AS permission
        UNION ALL SELECT 'customers.delete' AS permission
        UNION ALL SELECT 'contacts.view' AS permission
        UNION ALL SELECT 'contacts.create' AS permission
        UNION ALL SELECT 'contacts.edit' AS permission
        UNION ALL SELECT 'contacts.delete' AS permission
        UNION ALL SELECT 'interactions.view' AS permission
        UNION ALL SELECT 'interactions.create' AS permission
        UNION ALL SELECT 'interactions.edit' AS permission
        UNION ALL SELECT 'interactions.delete' AS permission
        UNION ALL SELECT 'rfqs.view' AS permission
        UNION ALL SELECT 'rfqs.create' AS permission
        UNION ALL SELECT 'rfqs.edit' AS permission
        UNION ALL SELECT 'rfqs.delete' AS permission
        UNION ALL SELECT 'rfqs.update_stage' AS permission
        UNION ALL SELECT 'quotes.view' AS permission
        UNION ALL SELECT 'quotes.create' AS permission
        UNION ALL SELECT 'quotes.edit' AS permission
        UNION ALL SELECT 'quotes.delete' AS permission
        UNION ALL SELECT 'reservations.view' AS permission
        UNION ALL SELECT 'reservations.create' AS permission
        UNION ALL SELECT 'reservations.update_status' AS permission
        UNION ALL SELECT 'campaigns.view' AS permission
        UNION ALL SELECT 'campaigns.create' AS permission
        UNION ALL SELECT 'campaigns.edit' AS permission
        UNION ALL SELECT 'campaigns.delete' AS permission
        UNION ALL SELECT 'campaigns.metrics' AS permission
        UNION ALL SELECT 'inventory.view' AS permission
        UNION ALL SELECT 'inventory.create' AS permission
        UNION ALL SELECT 'inventory.edit' AS permission
        UNION ALL SELECT 'inventory.update_stock' AS permission
        UNION ALL SELECT 'inventory.reserve' AS permission
        UNION ALL SELECT 'reports.view' AS permission
        UNION ALL SELECT 'admin.manage_users' AS permission
        UNION ALL SELECT 'references.view' AS permission
) p
WHERE r.role_name = 'Admin';

INSERT IGNORE INTO role_permissions (role_id, permission)
SELECT r.id, p.permission
FROM roles r
JOIN (
        SELECT 'dashboard.view' AS permission
        UNION ALL SELECT 'customers.view' AS permission
        UNION ALL SELECT 'customers.create' AS permission
        UNION ALL SELECT 'customers.edit' AS permission
        UNION ALL SELECT 'contacts.view' AS permission
        UNION ALL SELECT 'contacts.create' AS permission
        UNION ALL SELECT 'contacts.edit' AS permission
        UNION ALL SELECT 'interactions.view' AS permission
        UNION ALL SELECT 'interactions.create' AS permission
        UNION ALL SELECT 'rfqs.view' AS permission
        UNION ALL SELECT 'rfqs.create' AS permission
        UNION ALL SELECT 'rfqs.edit' AS permission
        UNION ALL SELECT 'rfqs.update_stage' AS permission
        UNION ALL SELECT 'quotes.view' AS permission
        UNION ALL SELECT 'quotes.create' AS permission
        UNION ALL SELECT 'quotes.edit' AS permission
        UNION ALL SELECT 'reservations.view' AS permission
        UNION ALL SELECT 'reservations.create' AS permission
        UNION ALL SELECT 'inventory.view' AS permission
        UNION ALL SELECT 'reports.view' AS permission
        UNION ALL SELECT 'references.view' AS permission
) p
WHERE r.role_name = 'Sales User';

INSERT IGNORE INTO role_permissions (role_id, permission)
SELECT r.id, p.permission
FROM roles r
JOIN (
        SELECT 'dashboard.view' AS permission
        UNION ALL SELECT 'customers.view' AS permission
        UNION ALL SELECT 'contacts.view' AS permission
        UNION ALL SELECT 'campaigns.view' AS permission
        UNION ALL SELECT 'campaigns.create' AS permission
        UNION ALL SELECT 'campaigns.edit' AS permission
        UNION ALL SELECT 'campaigns.delete' AS permission
        UNION ALL SELECT 'campaigns.metrics' AS permission
        UNION ALL SELECT 'reports.view' AS permission
        UNION ALL SELECT 'references.view' AS permission
) p
WHERE r.role_name = 'Marketing User';

INSERT IGNORE INTO role_permissions (role_id, permission)
SELECT r.id, p.permission
FROM roles r
JOIN (
        SELECT 'dashboard.view' AS permission
        UNION ALL SELECT 'inventory.view' AS permission
        UNION ALL SELECT 'inventory.create' AS permission
        UNION ALL SELECT 'inventory.edit' AS permission
        UNION ALL SELECT 'inventory.update_stock' AS permission
        UNION ALL SELECT 'inventory.reserve' AS permission
        UNION ALL SELECT 'reservations.view' AS permission
        UNION ALL SELECT 'reservations.create' AS permission
        UNION ALL SELECT 'reservations.update_status' AS permission
        UNION ALL SELECT 'rfqs.view' AS permission
        UNION ALL SELECT 'reports.view' AS permission
        UNION ALL SELECT 'references.view' AS permission
) p
WHERE r.role_name = 'Inventory Manager';

-- RFQ inventory reservations. The 'Reserved' rows back each product's
-- reserved_quantity above (their quantities sum to it) and feed the Pending
-- Reservations / Top Reserved cards. A few Released/Converted rows add realistic
-- history (a Lost RFQ released its hold, Won RFQs converted theirs) and are
-- excluded from the "pending" count on purpose.
INSERT INTO rfq_inventory_reservations (id, rfq_id, product_id, quantity_reserved, reservation_status) VALUES
(1,  2,  7,  46, 'Reserved'),
(2,  3,  9,  40, 'Reserved'),
(3,  16, 9,  20, 'Reserved'),
(4,  10, 12, 25, 'Reserved'),
(5,  20, 12, 20, 'Reserved'),
(6,  4,  19, 40, 'Reserved'),
(7,  5,  14, 34, 'Reserved'),
(8,  1,  4,  20, 'Reserved'),
(9,  7,  13, 15, 'Reserved'),
(10, 8,  2,  12, 'Reserved'),
(11, 11, 17, 10, 'Reserved'),
(12, 6,  6,  8,  'Reserved'),
(13, 9,  15, 6,  'Reserved'),
(14, 5,  5,  5,  'Reserved'),
(15, 6,  18, 4,  'Reserved'),
(16, 3,  10, 2,  'Reserved'),
(17, 12, 2,  5,  'Converted'),
(18, 14, 4,  10, 'Released'),
(19, 13, 5,  8,  'Converted')
ON DUPLICATE KEY UPDATE
    rfq_id = VALUES(rfq_id),
    product_id = VALUES(product_id),
    quantity_reserved = VALUES(quantity_reserved),
    reservation_status = VALUES(reservation_status);
