# AI CRM Agent Development Project

## Medical Device Industry CRM Database

### Course Project

**Build an AI-powered CRM system that automatically discovers, extracts, organizes, and maintains customer information for the Medical Device industry using publicly available information.**

---

# Objective

The objective of this project is to expose students to:

* AI Agents
* Web Crawling
* Web Scraping
* LLM-based Information Extraction
* Entity Resolution
* CRM Design
* Database Design
* Workflow Automation
* Data Engineering
* Responsible AI and Ethical Data Collection

The final system should automatically collect information from public sources and populate a searchable CRM database.

---

# Background

Sales organizations spend significant effort identifying potential customers and decision makers.

Instead of manually searching websites, students will build an AI Agent that can discover and organize publicly available business information.

The project should **not** scrape websites that prohibit automated scraping (such as LinkedIn). Instead, students should use public and legally accessible information sources.

---

# Target Companies

Examples include:

* Medtronic
* Abbott
* Boston Scientific
* Stryker
* Zimmer Biomet
* Edwards Lifesciences
* GE Healthcare
* Philips Healthcare
* Siemens Healthineers
* Johnson & Johnson MedTech

---

# System Architecture

```
                  Company List
                        │
                        ▼
               Search/Crawl Agent
                        │
                        ▼
              Information Extraction
                        │
                        ▼
              AI/LLM Data Cleaning
                        │
                        ▼
              Entity Resolution
           (Duplicate Detection)
                        │
                        ▼
               CRM Database
                        │
                        ▼
              Search & Dashboard
```

---

# Module 1 – Company Database

Create a master table containing:

* Company Name
* Headquarters
* Industry
* Website
* Revenue
* Number of Employees
* Medical Specialty
* Product Categories

Deliverable:

```
Company.csv
```

---

# Module 2 – Public Website Crawler

Develop a crawler that visits:

* Leadership pages
* Executive pages
* Contact pages
* Press releases
* News pages
* Product pages

Store:

* URL
* HTML
* Crawl Date
* Status

Deliverable:

```
crawler.py
```

---

# Module 3 – AI Information Extractor

Use an LLM to extract:

* Person Name
* Job Title
* Department
* Company
* Location
* Biography
* Email (if publicly available)
* Phone (if publicly available)

Example Output:

```json
{
    "name":"Jane Smith",
    "title":"Director of Clinical Affairs",
    "company":"Boston Scientific",
    "location":"Minnesota"
}
```

Deliverable:

```
extractor.py
```

---

# Module 4 – Patent Mining

Search patent databases and extract:

* Inventor
* Assignee
* Technology
* Filing Date
* Location

Use this to identify R&D personnel.

Deliverable:

```
patents.csv
```

---

# Module 5 – Conference Mining

Extract speakers and exhibitors from:

* Medical conferences
* Device conferences
* Scientific meetings

Store:

* Speaker
* Company
* Title
* Session
* Year

Deliverable:

```
conference.csv
```

---

# Module 6 – Publication Mining

Extract authors from:

* PubMed
* Research journals
* Clinical papers

Store:

* Author
* Institution
* Department
* Publication
* Year

Deliverable:

```
publications.csv
```

---

# Module 7 – Entity Resolution

Detect duplicates.

Example:

```
John Smith
John A Smith
J. Smith
```

should become

```
John A. Smith
```

using fuzzy matching and AI-assisted normalization.

---

# Module 8 – CRM Database Design

Suggested Tables:

## Company

* CompanyID
* Name
* Website
* Industry

## Person

* PersonID
* Name
* Title
* Department
* CompanyID

## Publication

* PublicationID
* PersonID
* Title
* Journal

## Patent

* PatentID
* PersonID
* PatentNumber

## Conference

* ConferenceID
* PersonID
* ConferenceName

---

# Module 9 – AI Search Agent

Allow natural language questions such as:

> Show Clinical Affairs Directors at Medtronic.

> Find all AI researchers at Abbott.

> Show R&D Directors in Cardiology companies.

> List inventors with more than 20 patents.

The agent should generate SQL automatically.

---

# Module 10 – CRM Dashboard

Build a web interface showing:

* Companies
* Contacts
* Organization Chart
* Patents
* Publications
* Conferences
* Search
* AI Chat Interface

---

# Bonus Modules

## Email Generator

Generate a personalized email introducing your product.

---

## Meeting Preparation Agent

Summarize a customer before a sales meeting.

---

## Organization Chart Generator

Automatically infer reporting hierarchy.

---

## Company News Monitor

Track new announcements and update CRM records.

---

# Suggested Technology Stack

| Component     | Technology          |
| ------------- | ------------------- |
| Language      | Python              |
| AI            | GPT-5.5             |
| Web Crawling  | Scrapy / Playwright |
| Database      | PostgreSQL          |
| Backend       | FastAPI             |
| Frontend      | React               |
| Vector Search | pgvector            |
| Workflow      | Airflow or n8n      |
| Container     | Docker              |

---

# Ethical Guidelines

Students must:

* Respect website Terms of Service.
* Use publicly available information only.
* Avoid scraping sites that prohibit automated access.
* Preserve source URLs for collected information.
* Never collect or misuse sensitive personal data.
* Follow applicable privacy regulations.

---

# Final Deliverables

* Source Code
* Database Schema
* Sample CRM Database
* AI Agent
* Dashboard
* Documentation
* Presentation
* Demonstration Video

---

# Stretch Goal

Build a complete AI-powered CRM assistant capable of answering:

> "Who are the top Clinical Affairs leaders working in Cardiac Rhythm Management companies in the United States?"

using information automatically collected from publicly available sources.

**Estimated Difficulty:** ★★★★☆

**Recommended Team Size:** 3–5 students

**Duration:** 8–12 weeks

**Skills Learned:** AI Agents, LLMs, Web Crawling, Information Extraction, Data Engineering, CRM Design, Databases, Prompt Engineering, and Full-Stack Development.
