# CRM System for Typhon Cat
## Software Requirements Specification (SRS – Capstone Level)

---

# 1. Introduction

## 1.1 Purpose
This document defines the requirements for a lightweight Customer Relationship Management (CRM) system developed for Typhon Cat. The system is designed to support core business operations including customer management, sales pipeline tracking, marketing campaigns, and inventory management.

The system will be implemented as a monolithic web application using PHP and MySQL.

---

## 1.2 Project Goals
The goal of this CRM system is to:
- Centralize customer and business interaction data
- Track sales opportunities through RFQ lifecycle
- Manage digital marketing campaigns
- Maintain inventory visibility for products/services
- Provide a unified operational dashboard for decision-making

---

## 1.3 Scope

The system will include four primary modules:

### Student 1 — Customer Management
- Customer profile creation and maintenance
- Contact management
- Interaction history tracking

### Student 2 — RFQ / Pipeline Management
- RFQ creation and tracking
- Sales pipeline stages
- Quote and deal tracking

### Student 3 — Digital Campaign Management
- Campaign creation and tracking
- Customer segmentation
- Campaign performance metrics

### Student 4 — Inventory Management
- Product catalog
- Stock tracking
- Inventory allocation to RFQs

### All — Integration Layer
- Cross-module data sharing
- Reporting and unified dashboard
- Internal APIs for module communication

---

## 1.4 Technology Stack

### Frontend
- HTML5
- CSS3
- JavaScript
- jQuery
- Bootstrap

### Backend
- PHP

### Database
- MySQL

### Additional Tools
- Git / GitHub for version control
- Apache or Nginx web server

---

# 2. Overall System Description

## 2.1 System Perspective
The CRM is a web-based monolithic application where all modules operate within a shared codebase and database.

---

## 2.2 User Roles

### 2.2.1 Admin
- Full system access
- User management
- System configuration

### 2.2.2 Sales User
- Manage customers
- Create and track RFQs

### 2.2.3 Marketing User
- Manage campaigns
- View campaign analytics

### 2.2.4 Inventory Manager
- Manage products and stock
- Update inventory availability

---

## 2.3 Assumptions
- Users have browser access
- Internet connectivity available
- Single organization deployment for Typhon Cat internal operations

---

# 3. Functional Requirements

---

# S1 — Customer Management

## FR-S1.1 Customer Creation
System shall allow users to create customer records including:
- Name / Company
- Email
- Phone
- Address
- Industry
- Tags

---

## FR-S1.2 Customer Search
System shall allow searching customers by:
- Name
- Email
- Tags

---

## FR-S1.3 Interaction Tracking
System shall log:
- Calls
- Emails
- Notes
- Meeting history

---

# S2 — RFQ / Pipeline Management

## FR-S2.1 RFQ Creation
System shall allow creation of RFQs linked to customers.

---

## FR-S2.2 Pipeline Stages
RFQs shall move through:
- New
- In Review
- Quoted
- Negotiation
- Won
- Lost

---

## FR-S2.3 Quote Tracking
System shall store:
- Quote amount
- Discount
- Validity period

---

## FR-S2.4 Deal Conversion
System shall allow conversion of RFQ into a “Deal Won”.

---

# S3 — Digital Campaign Management

## FR-S3.1 Campaign Creation
System shall allow creation of:
- Email campaigns
- SMS campaigns (optional simulation)

---

## FR-S3.2 Audience Selection
Campaigns shall be linked to:
- Customer segments
- Tags
- Filters

---

## FR-S3.3 Campaign Tracking
System shall track:
- Sent count
- Open rate
- Click rate (optional)

---

# S4 — Inventory Management

## FR-S4.1 Product Management
System shall allow creation of products:
- Product name
- SKU
- Price
- Description

---

## FR-S4.2 Stock Management
System shall track:
- Available quantity
- Reserved quantity

---

## FR-S4.3 RFQ Reservation
System shall allow inventory to be reserved for RFQs.

---

# S5 — Integration Layer

## FR-S5.1 Unified Dashboard
System shall provide a dashboard showing:
- Customer count
- Active RFQs
- Campaign performance
- Inventory status

---

## FR-S5.2 Cross-module Linking
System shall ensure:
- RFQ → Customer linkage
- Campaign → Customer linkage
- RFQ → Inventory linkage

---

# 4. Non-Functional Requirements

## NFR1 Performance
- Page load time should be under 3 seconds
- Database queries should use indexing optimization

---

## NFR2 Security
- Password hashing using bcrypt
- Role-based access control
- Session management

---

## NFR3 Usability
- Responsive Bootstrap UI
- Simple navigation menus

---

## NFR4 Maintainability
- Modular PHP folder structure
- Clean separation of concerns

---

## NFR5 Reliability
- Database backup support
- Error logging enabled

---

# 5. System Architecture

## 5.1 Architecture Style
Monolithic 3-tier architecture:
1. Presentation Layer (UI)
2. Application Layer (PHP Business Logic)
3. Data Layer (MySQL Database)

---

## 5.2 Recommended Folder Structure

```text
/crm-typhoncat
  /modules
    /customer
    /rfq
    /campaign
    /inventory
    /integration

  /public
    /css
    /js
    /bootstrap

  /config
  /includes
  /database
