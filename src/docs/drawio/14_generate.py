#!/usr/bin/env python3
"""Regenerate 14_modules_and_data_model.drawio (both pages). See 14_modules_and_data_model.md."""
import os

OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "14_modules_and_data_model.drawio")

# ── palette ──────────────────────────────────────────────────────────────────
P = {
    "blue":   ("#dae8fc", "#6c8ebf"),
    "orange": ("#ffe6cc", "#d79b00"),
    "green":  ("#d5e8d4", "#82b366"),
    "yellow": ("#fff2cc", "#d6b656"),
    "red":    ("#f8cecc", "#b85450"),
    "purple": ("#e1d5e7", "#9673a6"),
    "grey":   ("#f5f5f5", "#666666"),
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
    """Bold class name + method list."""
    out = f'<b>{name}</b>'
    for m in methods:
        out += f'<br>{m}'
    if extra:
        out += f'<br><i>{extra}</i>'
    return out

CLS = ("rounded=1;whiteSpace=wrap;html=1;align=left;verticalAlign=top;"
       "spacingLeft=10;spacingTop=4;fontSize=12;fillColor=#ffffff;strokeColor={};")
LANE = ("swimlane;html=1;startSize=44;fontSize=17;fontStyle=1;rounded=1;"
        "fillColor={};strokeColor={};swimlaneFillColor=#ffffff;")
EDGE = ("edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;"
        "html=1;strokeColor=#555555;")
EDGE_X = ("edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;"
          "html=1;strokeColor=#9673a6;strokeWidth=2;dashed=1;fontSize=12;"
          "fontColor=#6a4c93;labelBackgroundColor=#ffffff;")

# ══ PAGE 1 — modules ═════════════════════════════════════════════════════════
COLX = [40, 520, 1000]
ROWY = [120, 720]
LW, LH = 420, 480

MODULES = [
    # (id, title, colour, col, row, [(class, [methods], extra), ...])
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
        ("CampaignRepository", ["+ listTable()  + insert()  + update()", "+ insertAudience()  + dashboardStats()",
                                "+ campaignMomentum()", "+ overdueScheduledSends()"], "+ 17 more"),
    ]),
    ("inv", "Inventory", "green", 2, 0, [
        ("InventoryController", ["+ index()  + show()  + save()", "+ updateStock()  + reservations()",
                                 "+ ledger()"], "+ 6 more"),
        ("InventoryService", ["+ createProduct()  + updateStock()", "+ deleteProduct()  + reserveForRfq()",
                              "+ releaseReservation()"], "+ 9 more"),
        ("InventoryRepository", ["+ listTable()  + findById()", "+ updateStock()  + createReservation()",
                                 "+ updateReservationStatus()", "+ logMovement()  + countReservations()"], "+ 13 more"),
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
    x, y = COLX[col], ROWY[row]
    box(mid, title, x, y, LW, LH, LANE.format(fill, stroke))
    ys = [(56, 108), (190, 108), (330, 128)]
    for i, (cname, methods, extra) in enumerate(classes):
        cy, ch = ys[i]
        child(f"{mid}_{i}", mid, cls_label(cname, methods, extra), 20, cy, 380, ch,
              CLS.format(stroke))
    edge(f"{mid}_e0", f"{mid}_0", f"{mid}_1",
         EDGE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;")
    edge(f"{mid}_e1", f"{mid}_1", f"{mid}_2",
         EDGE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;")

# Dashboard — four boxes (Controller builds Cards; Cards read Service)
fill, stroke = P["grey"]
box("dsh", "Dashboard", COLX[1], ROWY[1], LW, LH, LANE.format(fill, stroke))
DSH = [
    ("DashboardController", ["+ index()"], None, 56, 56),
    ("DashboardCard  (abstract)", ["+ title()  + body()  + render()", "+ permission()  + visible()"],
     "17 concrete cards extend it", 128, 90),
    ("DashboardService", ["+ activeRfqSummary()  + winRateByAccount()", "+ campaignStatusBreakdown()",
                          "+ overdueCampaignSends()  + lowStockProducts()"], "+ 17 more", 240, 108),
    ("DashboardRepository", ["+ lowStock()  + topReserved()", "+ reservedUnits()  + heavilyReserved()"],
     "+ 1 more", 360, 90),
]
for i, (cname, methods, extra, cy, ch) in enumerate(DSH):
    child(f"dsh_{i}", "dsh", cls_label(cname, methods, extra), 20, cy, 380, ch, CLS.format(stroke))
for i in range(3):
    edge(f"dsh_e{i}", f"dsh_{i}", f"dsh_{i+1}",
         EDGE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;")

# Cross-module: DashboardService composes RFQ + Campaign repositories
svc_mid = ROWY[1] + 240 + 54
rep_mid = ROWY[0] + 330 + 64
edge("x_rfq", "dsh_2", "rfq_2",
     EDGE_X + "exitX=0;exitY=0.5;exitDx=0;exitDy=0;entryX=1;entryY=0.5;entryDx=0;entryDy=0;",
     "reads", [(480, svc_mid), (480, rep_mid)])
edge("x_cmp", "dsh_2", "cmp_2",
     EDGE_X + "exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=1;entryY=0.5;entryDx=0;entryDy=0;",
     "reads", [(960, svc_mid), (960, rep_mid)])

# Core layer band
cfill, cstroke = P["yellow"]
box("core", "Core  —  used by every module", 40, 1280, 1380, 156,
    LANE.format(cfill, cstroke).replace("startSize=44", "startSize=40"))
CORE = ["Auth", "Permissions", "Csrf", "Validator", "Database", "View", "DataTable", "PDF"]
for i, c in enumerate(CORE):
    child(f"core_{i}", "core", f"<b>{c}</b>", 20 + i * 170, 52, 150, 56,
          "rounded=1;whiteSpace=wrap;html=1;fontSize=13;fillColor=#ffffff;strokeColor=#666666;")
child("core_note", "core",
      "Every <b>Repository</b> obtains its handle from <b>Database::connection()</b>. "
      "Every <b>Controller</b> passes through <b>Permissions</b> + <b>Csrf</b> before it dispatches.",
      20, 118, 1340, 28,
      "text;html=1;align=center;verticalAlign=middle;fontSize=12;fontColor=#444444;")

box("p1_title", "Module architecture  —  Controller → Service → Repository, per module",
    40, 30, 1380, 40,
    "text;html=1;align=left;verticalAlign=middle;fontSize=22;fontStyle=1;fontColor=#333333;")
box("p1_leg",
    "Solid arrow = calls, within a module.   "
    "<font color='#9673a6'><b>Dashed purple</b></font> = cross-module read.   "
    "Method lists are representative; the italic line gives the count not shown.",
    40, 1456, 1380, 30,
    "text;html=1;align=left;verticalAlign=middle;fontSize=13;fontColor=#555555;")

page1 = "".join(cells)

# ══ PAGE 2 — ERD ═════════════════════════════════════════════════════════════
cells = []

TBL = ("swimlane;html=1;startSize=34;fontSize=15;fontStyle=1;align=center;"
       "fillColor={};strokeColor={};swimlaneFillColor=#ffffff;")
ROW = ("text;html=1;align=left;verticalAlign=middle;spacingLeft=8;fontSize=12;"
       "strokeColor=none;fillColor=none;")

def table(tid, name, x, y, w, rows, colour):
    fill, stroke = P[colour]
    h = 34 + len(rows) * 22 + 10
    box(tid, name, x, y, w, h, TBL.format(fill, stroke))
    for i, (mark, col) in enumerate(rows):
        if mark == "PK":
            lbl = f'<b>PK</b>  <b>{col}</b>'
        elif mark == "FK":
            lbl = f'<font color="#b85450"><b>FK</b></font>  {col}'
        elif mark == "..":
            lbl = f'<i>{col}</i>'
        else:
            lbl = f'     {col}'
        child(f"{tid}_r{i}", tid, lbl, 6, 34 + i * 22, w - 12, 22, ROW)
    return h

# top identity band
table("t_rp", "role_permissions", 60, 60, 400,
      [("FK", "role_id"), ("", "permission")], "grey")
table("t_rol", "roles", 560, 60, 400,
      [("PK", "id"), ("", "role_name"), ("", "description (null)"),
       ("", "owner_user_id (null, no FK)")], "grey")
table("t_usr", "users", 1060, 60, 400,
      [("PK", "id"), ("", "name"), ("", "email (unique)"),
       ("", "password_hash"), ("FK", "role_id")], "grey")

CY = [460, 780, 1100]
table("t_cam", "campaigns", 60, CY[0], 460,
      [("PK", "id"), ("", "campaign_name"), ("", "campaign_type (enum)"),
       ("", "status (enum)"), ("", "scheduled_at (null)"),
       ("FK", "created_by_user_id"), ("", "sent_count")], "purple")
table("t_ca", "campaign_audience", 60, CY[1], 460,
      [("PK", "id"), ("FK", "campaign_id"), ("FK", "account_id (null)"),
       ("FK", "contact_id (null)"), ("", "tag_filter (null)"),
       ("", "segment_name (null)")], "purple")
table("t_ap", "audience_presets", 60, CY[2], 460,
      [("PK", "id"), ("", "preset_name"), ("", "segment_name"),
       ("", "account_ids / contact_ids"), ("FK", "created_by_user_id"),
       ("..", "+ 3 more")], "purple")

table("t_acc", "accounts", 600, CY[0], 460,
      [("PK", "id"), ("", "account_name"), ("FK", "parent_account_id (null)"),
       ("", "industry (null)"), ("", "tags (null)"), ("..", "+ 6 more")], "blue")
table("t_con", "contacts", 600, CY[1], 460,
      [("PK", "id"), ("FK", "account_id"), ("", "first_name / last_name"),
       ("", "email (null)"), ("", "title (null)"), ("..", "+ 6 more")], "blue")
table("t_int", "interactions", 600, CY[2], 460,
      [("PK", "id"), ("FK", "account_id"), ("FK", "contact_id (null)"),
       ("FK", "user_id"), ("", "interaction_type (enum)"),
       ("", "interaction_subject"), ("..", "+ 3 more")], "blue")

table("t_rfq", "rfqs", 1140, CY[0], 460,
      [("PK", "id"), ("FK", "account_id (null)"), ("FK", "contact_id (null)"),
       ("FK", "created_by_user_id"), ("", "title"), ("", "stage (enum)"),
       ("..", "+ 3 more")], "orange")
table("t_quo", "quotes", 1140, CY[1], 370,
      [("PK", "id"), ("FK", "rfq_id"), ("", "quote_amount"),
       ("", "discount (null)"), ("", "validity_start_date (null)"),
       ("", "validity_end_date (null)")], "orange")
table("t_res", "rfq_inventory_reservations", 1140, CY[2], 460,
      [("PK", "id"), ("FK", "rfq_id"), ("FK", "product_id"),
       ("", "quantity_reserved"), ("", "reservation_status (enum)"),
       ("..", "+ 2 more")], "orange")

table("t_pro", "products", 1680, CY[0], 460,
      [("PK", "id"), ("", "product_name"), ("", "sku (unique)"),
       ("", "price"), ("", "description (null)"), ("..", "+ 2 more")], "green")
table("t_inv", "inventory", 1680, CY[1], 460,
      [("PK", "id"), ("FK", "product_id (unique)"), ("", "available_quantity"),
       ("", "reserved_quantity"), ("", "low_stock_threshold"),
       ("", "updated_at")], "green")
table("t_mov", "inventory_movements", 1680, CY[2], 460,
      [("PK", "id"), ("FK", "product_id (null)"), ("FK", "user_id (null)"),
       ("", "movement_type (enum)"), ("", "quantity_delta (null)"),
       ("..", "+ 7 more")], "green")

ER = ("edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;html=1;"
      "strokeColor=#4d4d4d;fontSize=13;fontStyle=1;labelBackgroundColor=#ffffff;"
      "startArrow={};startFill=0;endArrow=ERmany;endFill=0;")
ONE = ER.format("ERone")
OPT = ER.format("ERzeroToOne")
ONE1 = ("edgeStyle=orthogonalEdgeStyle;rounded=1;orthogonalLoop=1;jettySize=auto;html=1;"
        "strokeColor=#4d4d4d;fontSize=13;fontStyle=1;labelBackgroundColor=#ffffff;"
        "startArrow=ERone;startFill=0;endArrow=ERone;endFill=0;")

def X(x, y): return (x, y)

# identity band
edge("e1", "t_rol", "t_usr", ONE + "exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;", "1 : N")
edge("e2", "t_rol", "t_rp", ONE + "exitX=0;exitY=0.5;exitDx=0;exitDy=0;entryX=1;entryY=0.5;entryDx=0;entryDy=0;", "1 : N")

# users fan-out
edge("e3", "t_usr", "t_cam", ONE + "exitX=0.15;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;",
     "1 : N", [X(1120, 340), X(290, 340)])
edge("e4", "t_usr", "t_ap", ONE + "exitX=0.02;exitY=1;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;",
     "1 : N", [X(1068, 300), X(30, 300), X(30, 1200)])
edge("e5", "t_usr", "t_rfq", ONE + "exitX=0.78;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;",
     "1 : N")
edge("e6", "t_usr", "t_int", ONE + "exitX=0.3;exitY=1;exitDx=0;exitDy=0;entryX=1;entryY=0.4;entryDx=0;entryDy=0;",
     "1 : N", [X(1180, 400), X(1110, 400), X(1110, 1200)])
edge("e7", "t_usr", "t_mov", ONE + "exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;",
     "1 : N", [X(1655, 140), X(1655, 1200)])

# campaign
edge("e8", "t_cam", "t_ca", ONE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;", "1 : N")
edge("e9", "t_acc", "t_ca", OPT + "exitX=0;exitY=0.6;exitDx=0;exitDy=0;entryX=1;entryY=0.3;entryDx=0;entryDy=0;",
     "0..1 : N", [X(545, 620), X(545, 860)])
edge("e10", "t_con", "t_ca", OPT + "exitX=0;exitY=0.6;exitDx=0;exitDy=0;entryX=1;entryY=0.7;entryDx=0;entryDy=0;",
     "0..1 : N", [X(575, 900), X(575, 920)])

# customer
edge("e11", "t_acc", "t_con", ONE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;", "1 : N")
edge("e12", "t_con", "t_int", ONE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;",
     "0..1 : N")
edge("e13", "t_acc", "t_int", ONE + "exitX=1;exitY=0.8;exitDx=0;exitDy=0;entryX=1;entryY=0.75;entryDx=0;entryDy=0;",
     "1 : N", [X(1075, 620), X(1075, 1240)])
edge("e14", "t_acc", "t_acc",
     "edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#4d4d4d;fontSize=13;fontStyle=1;"
     "labelBackgroundColor=#ffffff;startArrow=ERzeroToOne;startFill=0;endArrow=ERmany;endFill=0;"
     "exitX=0.1;exitY=0;exitDx=0;exitDy=0;entryX=0.9;entryY=0;entryDx=0;entryDy=0;",
     "0..1 : N  parent", [X(646, 412), X(1014, 412)])

# rfq
edge("e15", "t_acc", "t_rfq", OPT + "exitX=1;exitY=0.2;exitDx=0;exitDy=0;entryX=0;entryY=0.2;entryDx=0;entryDy=0;",
     "0..1 : N")
edge("e16", "t_con", "t_rfq", OPT + "exitX=1;exitY=0.35;exitDx=0;exitDy=0;entryX=0;entryY=0.8;entryDx=0;entryDy=0;",
     "0..1 : N", [X(1090, 850), X(1090, 640)])
edge("e17", "t_rfq", "t_quo", ONE + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;", "1 : N")
edge("e18", "t_rfq", "t_res", ONE + "exitX=0.92;exitY=1;exitDx=0;exitDy=0;entryX=0.92;entryY=0;entryDx=0;entryDy=0;",
     "1 : N")

# inventory
edge("e19", "t_pro", "t_inv", ONE1 + "exitX=0.5;exitY=1;exitDx=0;exitDy=0;entryX=0.5;entryY=0;entryDx=0;entryDy=0;",
     "1 : 1")
edge("e20", "t_pro", "t_mov", ONE + "exitX=1;exitY=0.7;exitDx=0;exitDy=0;entryX=1;entryY=0.5;entryDx=0;entryDy=0;",
     "1 : N", [X(2180, 600), X(2180, 1200)])
edge("e21", "t_pro", "t_res", ONE + "exitX=0;exitY=0.4;exitDx=0;exitDy=0;entryX=1;entryY=0.3;entryDx=0;entryDy=0;",
     "1 : N", [X(1620, 540), X(1620, 1160)])

box("p2_title", "Data model  —  15 tables, crow’s-foot cardinality from the actual FOREIGN KEY constraints",
    60, 10, 2080, 36,
    "text;html=1;align=left;verticalAlign=middle;fontSize=22;fontStyle=1;fontColor=#333333;")
box("p2_leg",
    "<b>1 : N</b> = FK column is NOT NULL  •  <b>0..1 : N</b> = FK column is NULL-able "
    " •  <b>1 : 1</b> = FK column is UNIQUE  •  "
    "colour = owning module (blue Customer, orange RFQ, purple Campaign, green Inventory, grey shared identity)",
    60, 1380, 2080, 30,
    "text;html=1;align=left;verticalAlign=middle;fontSize=13;fontColor=#555555;")

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
