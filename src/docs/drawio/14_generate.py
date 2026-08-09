#!/usr/bin/env python3
"""Regenerate 14_modules_and_data_model.drawio (both pages). See ../writeup/14_modules_and_data_model.md.

Body text is 20px throughout; headings sit above it so the hierarchy survives.
Every box size and corridor width below is derived from that 20px line height —
if you change FS_BODY, the geometry constants have to move with it or the text
will overflow its box.
"""
import os

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                   "14_modules_and_data_model.drawio")

# ── type scale ───────────────────────────────────────────────────────────────
FS_BODY = 20     # method lists, table rows, edge labels, legends
FS_HEAD = 24     # module lane titles, table name bars
FS_TITLE = 30    # page titles
LH = 26          # rendered line height at FS_BODY

# ── palette (matches 00_DIAGRAM_INDEX.md) ────────────────────────────────────
P = {
    "blue":   ("#dae8fc", "#6c8ebf"),   # Customer
    "orange": ("#ffe6cc", "#d79b00"),   # RFQ
    "green":  ("#d5e8d4", "#82b366"),   # Inventory
    "yellow": ("#fff2cc", "#d6b656"),   # Core
    "red":    ("#f8cecc", "#b85450"),   # Admin
    "purple": ("#e1d5e7", "#9673a6"),   # Campaign
    "grey":   ("#f5f5f5", "#666666"),   # Dashboard / shared identity
}

cells = []
def cell(xml): cells.append(xml)

def esc(t):
    """XML-escape a label. Labels are written with literal HTML tags and unicode."""
    return (t.replace("&", "&amp;").replace("<", "&lt;")
             .replace(">", "&gt;").replace('"', "&quot;"))

def box(cid, label, x, y, w, h, style):
    cell(f'<mxCell id="{cid}" value="{esc(label)}" style="{style}" vertex="1" parent="1">'
         f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/></mxCell>')

def child(cid, parent, label, x, y, w, h, style):
    cell(f'<mxCell id="{cid}" value="{esc(label)}" style="{style}" vertex="1" parent="{parent}">'
         f'<mxGeometry x="{x}" y="{y}" width="{w}" height="{h}" as="geometry"/></mxCell>')

def edge(cid, src, tgt, style, label="", points=None):
    pts = ""
    if points:
        inner = "".join(f'<mxPoint x="{px}" y="{py}"/>' for px, py in points)
        pts = f'<Array as="points">{inner}</Array>'
    cell(f'<mxCell id="{cid}" value="{esc(label)}" style="{style}" edge="1" parent="1" '
         f'source="{src}" target="{tgt}">'
         f'<mxGeometry relative="1" as="geometry">{pts}</mxGeometry></mxCell>')

def cls_label(name, methods, extra=None):
    out = f'<b>{name}</b>'
    for m in methods:
        out += f'<br>{m}'
    if extra:
        out += f'<br><i>{extra}</i>'
    return out

CLS = ("rounded=1;whiteSpace=wrap;html=1;align=left;verticalAlign=top;"
       f"spacingLeft=12;spacingTop=6;fontSize={FS_BODY};fillColor=#ffffff;strokeColor={{}};")
LANE = ("swimlane;html=1;startSize=60;rounded=1;fontStyle=1;"
        f"fontSize={FS_HEAD};fillColor={{}};strokeColor={{}};swimlaneFillColor=#ffffff;")
EDGE = ("edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;"
        "html=1;strokeColor=#555555;strokeWidth=2;")
EDGE_X = ("edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;"
          "html=1;strokeColor=#9673a6;strokeWidth=3;dashed=1;"
          f"fontSize={FS_BODY};fontColor=#6a4c93;labelBackgroundColor=#ffffff;")

# ══ PAGE 1 — modules ═════════════════════════════════════════════════════════
LW, LH_LANE = 600, 620          # lane width / height
BW = 560                        # class box width
COLX = [40, 740, 1440]          # 100px corridors between lanes
ROWY = [140, 900]
CB = [(76, 146), (248, 146), (420, 172)]   # (y, h) for Controller / Service / Repository

MODULES = [
    ("rfq", "RFQ", "orange", 0, 0, [
        ("RFQController", ["+ index()  + show()  + create()", "+ edit()  + handleUpdateStagePost()",
                           "+ createQuote()  + createReservation()"], "+ 14 more"),
        ("RFQService", ["+ createRFQ()  + updateRFQ()", "+ changeStage()  + addQuote()",
                        "+ addReservation()"], "+ 6 more"),
        ("RFQRepository", ["+ search()  + findById()  + insert()", "+ updateStage()  + insertQuote()",
                           "+ insertReservation()", "+ winRateByAccount()  + stageSummary()"], "+ 25 more"),
    ]),
    ("cmp", "Campaign", "purple", 1, 0, [
        ("CampaignController", ["+ index()  + create()  + show()", "+ edit()  + audience()",
                                "+ handleSimulatePost()"], "+ 8 more"),
        ("CampaignService", ["+ createCampaign()  + updateCampaign()", "+ addAudienceSegment()",
                             "+ simulateSend()"], "+ 5 more"),
        ("CampaignRepository", ["+ listTable()  + insert()  + update()", "+ insertAudience()",
                                "+ dashboardStats()  + campaignMomentum()",
                                "+ overdueScheduledSends()"], "+ 17 more"),
    ]),
    ("inv", "Inventory", "green", 2, 0, [
        ("InventoryController", ["+ index()  + show()  + save()", "+ updateStock()  + reservations()",
                                 "+ ledger()"], "+ 6 more"),
        ("InventoryService", ["+ createProduct()  + updateStock()", "+ deleteProduct()  + reserveForRfq()",
                              "+ releaseReservation()"], "+ 9 more"),
        ("InventoryRepository", ["+ listTable()  + findById()", "+ updateStock()  + createReservation()",
                                 "+ updateReservationStatus()", "+ logMovement()  + countReservations()"],
         "+ 13 more"),
    ]),
    ("cus", "Customer", "blue", 0, 1, [
        ("CustomerController", ["+ index()"], "the only entry point"),
        ("CustomerService", ["(no public methods yet)"], "placeholder for future rules"),
        ("CustomerRepository", ["+ listTable()  + search()  + find()", "+ create()  + createContact()",
                                "+ recentInteractions()", "+ distinctValues()"], "+ 4 more"),
    ]),
    ("adm", "Admin", "red", 2, 1, [
        ("AdminController", ["+ listUsers()  + createUser()", "+ editUser()  + permissionMatrix()",
                             "+ handleSaveMatrixPost()"], "+ 7 more"),
        ("UserService", ["+ createUser()  + updateUser()", "+ validateUserInput()"], "+ 1 more"),
        ("AdminRepository / UserRepository", ["+ allRoles()  + savePermissions()", "+ rolePermissionsMap()",
                                              "+ listTable()  + insert()  + update()"], "+ 14 more"),
    ]),
]

for mid, title, colour, col, row, classes in MODULES:
    fill, stroke = P[colour]
    box(mid, title, COLX[col], ROWY[row], LW, LH_LANE, LANE.format(fill, stroke))
    for i, (cname, methods, extra) in enumerate(classes):
        cy, ch = CB[i]
        child(f"{mid}_{i}", mid, cls_label(cname, methods, extra), 20, cy, BW, ch,
              CLS.format(stroke))
    for i in range(2):
        edge(f"{mid}_e{i}", f"{mid}_{i}", f"{mid}_{i+1}",
             EDGE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;")

# Dashboard — Controller builds Cards, Cards read the Service
fill, stroke = P["grey"]
box("dsh", "Dashboard", COLX[1], ROWY[1], LW, LH_LANE, LANE.format(fill, stroke))
# Four boxes have to share the same lane height as the three-box modules, so the
# gaps here are 20px rather than 26px. Each height is (lines x LH) + 12; the
# Repository box needs 4 lines, not 3 — the italic count line is a line too.
DSH = [
    ("DashboardController", ["+ index()"], None, 76, 68),
    ("DashboardCard  (abstract)", ["+ title()  + body()  + render()", "+ permission()  + visible()"],
     "19 concrete cards extend it", 164, 120),
    ("DashboardService", ["+ activeRfqSummary()  + winRateByAccount()", "+ campaignStatusBreakdown()",
                          "+ overdueCampaignSends()", "+ lowStockProducts()"], "+ 17 more", 304, 172),
    ("DashboardRepository", ["+ lowStock()  + topReserved()", "+ reservedUnits()  + heavilyReserved()"],
     "+ 1 more", 496, 120),
]
for i, (cname, methods, extra, cy, ch) in enumerate(DSH):
    child(f"dsh_{i}", "dsh", cls_label(cname, methods, extra), 20, cy, BW, ch, CLS.format(stroke))
for i in range(3):
    edge(f"dsh_e{i}", f"dsh_{i}", f"dsh_{i+1}",
         EDGE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;")

svc_mid = ROWY[1] + 304 + 86     # DashboardService vertical centre
rep_mid = ROWY[0] + 420 + 86     # row-0 Repository vertical centre
edge("x_rfq", "dsh_2", "rfq_2",
     EDGE_X + "exitX=0;exitY=0.5;exitDx=0;exitDy=0;entryX=1;entryY=0.5;entryDx=0;entryDy=0;",
     "reads", [(690, svc_mid), (690, rep_mid)])
edge("x_cmp", "dsh_2", "cmp_2",
     EDGE_X + "exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=1;entryY=0.5;entryDx=0;entryDy=0;",
     "reads", [(1390, svc_mid), (1390, rep_mid)])

# Core band
cfill, cstroke = P["yellow"]
CORE_Y, CORE_W = 1620, 2000
box("core", "Core  —  used by every module", 40, CORE_Y, CORE_W, 210,
    LANE.format(cfill, cstroke))
CORE = ["Auth", "Permissions", "Csrf", "Validator", "Database", "View", "DataTable", "PDF"]
for i, c in enumerate(CORE):
    child(f"core_{i}", "core", f"<b>{c}</b>", 20 + i * 245, 74, 225, 66,
          f"rounded=1;whiteSpace=wrap;html=1;fontSize={FS_BODY};fillColor=#ffffff;strokeColor=#666666;")
child("core_note", "core",
      "Every <b>Repository</b> obtains its handle from <b>Database::connection()</b>. "
      "Every <b>Controller</b> passes through <b>Permissions</b> + <b>Csrf</b> before it dispatches.",
      20, 154, CORE_W - 40, 40,
      f"text;html=1;align=center;verticalAlign=middle;fontSize={FS_BODY};fontColor=#444444;")

box("p1_title", "Module architecture  —  Controller → Service → Repository, per module",
    40, 40, CORE_W, 50,
    f"text;html=1;align=left;verticalAlign=middle;fontSize={FS_TITLE};fontStyle=1;fontColor=#333333;")
box("p1_leg",
    "Solid arrow = calls, within a module. &nbsp; "
    "<font color='#9673a6'><b>Dashed purple</b></font> = cross-module read. &nbsp; "
    "Method lists are representative; the italic line gives the count not shown.",
    40, CORE_Y + 240, CORE_W, 40,
    f"text;html=1;align=left;verticalAlign=middle;fontSize={FS_BODY};fontColor=#555555;")

page1 = "".join(cells)

# ══ PAGE 2 — ERD ═════════════════════════════════════════════════════════════
cells = []

RH = 32          # table row height at 20px
HDR = 48         # table name bar
TW = 640         # table width

TBL = ("swimlane;html=1;startSize=48;align=center;fontStyle=1;"
       f"fontSize={FS_HEAD};fillColor={{}};strokeColor={{}};swimlaneFillColor=#ffffff;")
ROWS = ("text;html=1;align=left;verticalAlign=middle;spacingLeft=10;"
        f"fontSize={FS_BODY};strokeColor=none;fillColor=none;")

def table(tid, name, x, y, rows, colour, w=TW):
    fill, stroke = P[colour]
    h = HDR + len(rows) * RH + 12
    box(tid, name, x, y, w, h, TBL.format(fill, stroke))
    for i, (mark, col) in enumerate(rows):
        if mark == "PK":
            lbl = f'<b>PK</b>&nbsp; <b>{col}</b>'
        elif mark == "FK":
            lbl = f'<font color="#b85450"><b>FK</b></font>&nbsp; {col}'
        elif mark == "..":
            lbl = f'<i>{col}</i>'
        else:
            lbl = f'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; {col}'
        child(f"{tid}_r{i}", tid, lbl, 8, HDR + i * RH, w - 16, RH, ROWS)
    return h

# identity band
table("t_rp", "role_permissions", 60, 80,
      [("FK", "role_id"), ("", "permission")], "grey")
table("t_rol", "roles", 820, 80,
      [("PK", "id"), ("", "role_name"), ("", "description (null)"),
       ("", "owner_user_id (null, no FK)")], "grey")
table("t_usr", "users", 1580, 80,
      [("PK", "id"), ("", "name"), ("", "email (unique)"),
       ("", "password_hash"), ("FK", "role_id")], "grey")

CY = [640, 1060, 1480]
table("t_cam", "campaigns", 60, CY[0],
      [("PK", "id"), ("", "campaign_name"), ("", "campaign_type (enum)"),
       ("", "status (enum)"), ("", "scheduled_at (null)"),
       ("FK", "created_by_user_id"), ("", "sent_count")], "purple")
table("t_ca", "campaign_audience", 60, CY[1],
      [("PK", "id"), ("FK", "campaign_id"), ("FK", "account_id (null)"),
       ("FK", "contact_id (null)"), ("", "tag_filter (null)"),
       ("", "segment_name (null)")], "purple")
table("t_ap", "audience_presets", 60, CY[2],
      [("PK", "id"), ("", "preset_name"), ("", "segment_name"),
       ("", "account_ids / contact_ids"), ("FK", "created_by_user_id"),
       ("..", "+ 3 more")], "purple")

table("t_acc", "accounts", 820, CY[0],
      [("PK", "id"), ("", "account_name"), ("FK", "parent_account_id (null)"),
       ("", "industry (null)"), ("", "tags (null)"), ("..", "+ 6 more")], "blue")
table("t_con", "contacts", 820, CY[1],
      [("PK", "id"), ("FK", "account_id"), ("", "first_name / last_name"),
       ("", "email (null)"), ("", "title (null)"), ("..", "+ 6 more")], "blue")
table("t_int", "interactions", 820, CY[2],
      [("PK", "id"), ("FK", "account_id"), ("FK", "contact_id (null)"),
       ("FK", "user_id"), ("", "interaction_type (enum)"),
       ("", "interaction_subject"), ("..", "+ 3 more")], "blue")

table("t_rfq", "rfqs", 1580, CY[0],
      [("PK", "id"), ("FK", "account_id (null)"), ("FK", "contact_id (null)"),
       ("FK", "created_by_user_id"), ("", "title"), ("", "stage (enum)"),
       ("..", "+ 3 more")], "orange")
table("t_quo", "quotes", 1580, CY[1],
      [("PK", "id"), ("FK", "rfq_id"), ("", "quote_amount"),
       ("", "discount (null)"), ("", "validity_start_date (null)"),
       ("", "validity_end_date (null)")], "orange", w=500)
table("t_res", "rfq_inventory_reservations", 1580, CY[2],
      [("PK", "id"), ("FK", "rfq_id"), ("FK", "product_id"),
       ("", "quantity_reserved"), ("", "reservation_status (enum)"),
       ("..", "+ 2 more")], "orange")

table("t_pro", "products", 2340, CY[0],
      [("PK", "id"), ("", "product_name"), ("", "sku (unique)"),
       ("", "price"), ("", "description (null)"), ("..", "+ 2 more")], "green")
table("t_inv", "inventory", 2340, CY[1],
      [("PK", "id"), ("FK", "product_id (unique)"), ("", "available_quantity"),
       ("", "reserved_quantity"), ("", "low_stock_threshold"),
       ("", "updated_at")], "green")
table("t_mov", "inventory_movements", 2340, CY[2],
      [("PK", "id"), ("FK", "product_id (null)"), ("FK", "user_id (null)"),
       ("", "movement_type (enum)"), ("", "quantity_delta (null)"),
       ("..", "+ 7 more")], "green")

ER = ("edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;html=1;"
      f"strokeColor=#4d4d4d;strokeWidth=2;fontSize={FS_BODY};fontStyle=1;"
      "labelBackgroundColor=#ffffff;startArrow={};startFill=0;endArrow=ERmany;endFill=0;")
ONE = ER.format("ERone")
OPT = ER.format("ERzeroToOne")
ONE1 = ER.format("ERone").replace("endArrow=ERmany", "endArrow=ERone")

def X(x, y): return (x, y)

edge("e1", "t_rol", "t_usr", ONE + "exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;", "1 : N")
edge("e2", "t_rol", "t_rp", ONE + "exitX=0;exitY=0.5;exitDx=0;exitDy=0;entryX=1;entryY=0.5;entryDx=0;entryDy=0;", "1 : N")

edge("e3", "t_usr", "t_cam", ONE + "exitX=0.15;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;",
     "1 : N", [X(1676, 440), X(380, 440)])
edge("e4", "t_usr", "t_ap", ONE + "exitX=0.02;exitY=1;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;",
     "1 : N", [X(1593, 390), X(30, 390), X(30, 1606)])
edge("e5", "t_usr", "t_rfq", ONE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;",
     "1 : N")
edge("e6", "t_usr", "t_int", ONE + "exitX=0.3;exitY=1;exitDx=0;exitDy=0;entryX=1;entryY=0.5;entryDx=0;entryDy=0;",
     "1 : N", [X(1772, 500), X(1520, 500), X(1520, 1622)])
edge("e7", "t_usr", "t_mov", ONE + "exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;",
     "1 : N", [X(2280, 190), X(2280, 1622)])

edge("e8", "t_cam", "t_ca", ONE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;", "1 : N")
edge("e9", "t_acc", "t_ca", OPT + "exitX=0;exitY=0.6;exitDx=0;exitDy=0;entryX=1;entryY=0.35;entryDx=0;entryDy=0;",
     "0..1 : N", [X(745, 791), X(745, 1150)])
edge("e10", "t_con", "t_ca", OPT + "exitX=0;exitY=0.6;exitDx=0;exitDy=0;entryX=1;entryY=0.75;entryDx=0;entryDy=0;",
     "0..1 : N", [X(785, 1211), X(785, 1250)])

edge("e11", "t_acc", "t_con", ONE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;", "1 : N")
edge("e12", "t_con", "t_int", ONE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;",
     "0..1 : N")
edge("e13", "t_acc", "t_int", ONE + "exitX=1;exitY=0.85;exitDx=0;exitDy=0;entryX=1;entryY=0.78;entryDx=0;entryDy=0;",
     "1 : N", [X(1490, 854), X(1490, 1700)])
edge("e14", "t_acc", "t_acc",
     ER.format("ERzeroToOne") +
     "exitX=0.1;exitY=0;exitDx=0;exitDy=0;entryX=0.9;entryY=0;entryDx=0;entryDy=0;",
     "0..1 : N  parent", [X(884, 570), X(1396, 570)])

edge("e15", "t_acc", "t_rfq", OPT + "exitX=1;exitY=0.2;exitDx=0;exitDy=0;entryX=0;entryY=0.2;entryDx=0;entryDy=0;",
     "0..1 : N")
edge("e16", "t_con", "t_rfq", OPT + "exitX=1;exitY=0.35;exitDx=0;exitDy=0;entryX=0;entryY=0.81;entryDx=0;entryDy=0;",
     "0..1 : N", [X(1550, 1148), X(1550, 870)])
edge("e17", "t_rfq", "t_quo", ONE + "exitX=0.35;exitY=1;exitDx=0;exitDy=0;entryX=0.45;entryY=0;entryDx=0;entryDy=0;", "1 : N")
edge("e18", "t_rfq", "t_res", ONE + "exitX=0.92;exitY=1;exitDx=0;exitDy=0;entryX=0.92;entryY=0;entryDx=0;entryDy=0;",
     "1 : N")

edge("e19", "t_pro", "t_inv", ONE1 + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;",
     "1 : 1")
edge("e20", "t_pro", "t_mov", ONE + "exitX=1;exitY=0.7;exitDx=0;exitDy=0;entryX=1;entryY=0.5;entryDx=0;entryDy=0;",
     "1 : N", [X(3030, 816), X(3030, 1622)])
edge("e21", "t_pro", "t_res", ONE + "exitX=0;exitY=0.4;exitDx=0;exitDy=0;entryX=1;entryY=0.32;entryDx=0;entryDy=0;",
     "1 : N", [X(2250, 741), X(2250, 1560)])

box("p2_title", "Data model  —  15 tables, crow’s-foot cardinality from the actual FOREIGN KEY constraints",
    60, 10, 2900, 50,
    f"text;html=1;align=left;verticalAlign=middle;fontSize={FS_TITLE};fontStyle=1;fontColor=#333333;")
box("p2_leg",
    "<b>1 : N</b> = FK column is NOT NULL &nbsp;•&nbsp; <b>0..1 : N</b> = FK column is NULL-able "
    "&nbsp;•&nbsp; <b>1 : 1</b> = FK column is UNIQUE &nbsp;•&nbsp; "
    "colour = owning module (blue Customer, orange RFQ, purple Campaign, green Inventory, grey shared identity)",
    60, 1830, 2900, 40,
    f"text;html=1;align=left;verticalAlign=middle;fontSize={FS_BODY};fontColor=#555555;")

page2 = "".join(cells)

# ── assemble ────────────────────────────────────────────────────────────────
def diagram(name, body):
    return (f'<diagram name="{name}"><mxGraphModel dx="1200" dy="800" grid="1" gridSize="10" '
            f'guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" '
            f'pageWidth="1169" pageHeight="826" math="0" shadow="0"><root>'
            f'<mxCell id="0"/><mxCell id="1" parent="0"/>{body}</root></mxGraphModel></diagram>')

xml = ('<?xml version="1.0" encoding="UTF-8"?>\n<mxfile host="drawio" version="26.0.0">'
       + diagram("1 - Modules &amp; Functions", page1)
       + diagram("2 - Data Model &amp; Cardinality", page2)
       + '</mxfile>\n')

with open(OUT, "w") as f:
    f.write(xml)
print("wrote", OUT, len(xml), "bytes")
