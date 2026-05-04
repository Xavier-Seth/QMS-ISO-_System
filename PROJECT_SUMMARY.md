# QMS ISO System — Project Summary

## What This Project Does

This system is a web-based **Quality Management and Document Archive** platform built to help organizations manage their ISO-compliance documents and quality records in one place. It replaces paper-based or scattered file-based workflows with a structured system where staff can submit improvement requests, document change proposals, and corrective action reports — all tracked through a formal review-and-approval process. Administrators can manage the full document library, approve or reject submissions, and view analytics on the organization's quality activities over time.

## Who It's Built For

This system is built for a **Philippine government agency or state university** (likely a higher education institution) that is required to follow ISO quality management standards and Civil Service Commission (CSC) performance evaluation rules. The presence of document series like IPCR (Individual Performance Commitment and Review), DPCR (Departmental), and UPCR (Unit) — all standard CSC instruments — alongside ISO document control workflows points strongly to this context. Any similar organization needing centralized, auditable document and quality record management would benefit from this system.

## Key Features

- **Document Library** — Stores and organizes official documents by series (Administrative, Quality System, Human Resources, etc.), tracks revisions, and marks outdated versions as "Obsolete" automatically when a new version is uploaded.
- **OFI (Opportunity for Improvement) Submissions** — Staff can identify and formally report improvement opportunities using a structured form that gets routed to administrators for review and approval.
- **DCR (Document Change Request) Submissions** — Staff can request changes to existing documents through a formal workflow; administrators approve or reject with written feedback.
- **CAR (Corrective Action Records)** — Staff and auditors log corrective actions taken in response to non-conformities, with status tracked from open to resolved.
- **Approval Workflow** — All three record types follow a Draft → Pending → Approved/Rejected lifecycle. Rejected records include a written reason and the submitter can revise and resubmit.
- **Real-time Notifications** — Users receive live in-app alerts when their submissions are approved or rejected, without needing to refresh the page.
- **Performance Document Browser** — Staff can browse and download annual performance forms (IPCR, DPCR, UPCR) organized by year and period.
- **Admin Dashboard with Analytics** — Administrators see charts and counts for form activity by year, pending items requiring attention, document distribution by series, and recent uploads.
- **User Dashboard** — Regular users see a personal view of their own submissions — what's pending, what was rejected (with reasons), and what needs action.
- **Activity Audit Log** — Every significant action in the system is recorded with timestamps, providing a full audit trail for compliance purposes.
- **Controlled vs. Uncontrolled Manuals** — Some documents (controlled manuals) are restricted to administrators only; others are accessible to all authenticated staff.
- **Downloadable Records** — Approved QMS forms can be downloaded as formatted Word documents.
- **User Management** — Administrators can create, assign roles to, and manage staff accounts.

## Tech Stack

| Technology                     | Role in This Project                                                                                  |
| ------------------------------ | ----------------------------------------------------------------------------------------------------- |
| **Laravel 12 (PHP 8.2)**       | Backend framework — handles routing, authentication, database operations, and business logic          |
| **Vue 3**                      | Frontend JavaScript framework — builds the interactive UI components and pages                        |
| **Inertia.js v2**              | Bridges Laravel and Vue so the app behaves like a single-page app without a separate API              |
| **Tailwind CSS v3**            | Utility-first CSS framework — responsible for all visual styling and responsive layout                |
| **MySQL**                      | Relational database — stores all records, documents, users, and audit logs                            |
| **Laravel Reverb + Pusher.js** | WebSocket server and client — enables real-time push notifications without page refresh               |
| **PHPWord**                    | PHP library — generates downloadable Word (.docx) documents from approved QMS records                 |
| **PHPUnit v11**                | Testing framework — automated tests that verify approval workflows, file handling, and data integrity |
| **Vite**                       | Frontend build tool — bundles and optimizes JavaScript and CSS for the browser                        |

## System Architecture

The system is structured as a **server-rendered single-page application**:

- **Backend (Laravel)** — The server handles all business rules, data validation, authentication, and database queries. It processes form submissions, enforces approval workflows, manages file storage, and sends notifications.
- **Frontend (Vue 3 via Inertia.js)** — Instead of a traditional website that reloads on every click, the frontend renders pages dynamically in the browser. The server sends data; Vue handles the display. There is no separate mobile app or public API — the frontend is tightly coupled to the Laravel backend through Inertia.
- **Database (MySQL)** — Stores eleven core tables covering users, document types, document uploads, OFI/DCR/CAR records, QMS form templates, audit logs, and notifications. Revision history and workflow state are tracked directly in the database.
- **File Storage** — Uploaded documents are stored on the server's filesystem in two areas: a public disk (accessible by URL) and a private disk (requires server-side access control). Sensitive templates and controlled documents use the private disk.
- **Real-time Layer (Reverb)** — A WebSocket server runs alongside Laravel to push live notifications to logged-in users the moment an admin acts on their submission.

## Notable Technical Decisions

1. **Pessimistic Database Locking on Status Transitions** — When a record's resolution status changes, the system uses a database-level lock (`SELECT FOR UPDATE`) to prevent two simultaneous requests from overwriting each other. This is a deliberate safeguard against race conditions in the approval workflow — a subtle but important correctness guarantee in a multi-user environment.

2. **Preview Caching with Cache Invalidation** — Uploaded documents (PDFs, images) are converted into browser-ready previews and cached on disk to avoid regenerating them on every view. When a document is republished or re-submitted, the cache is explicitly cleared. This balances performance (fast previews) with correctness (users always see the latest version).

3. **Inertia.js for SPA Without API Complexity** — Rather than building a REST or GraphQL API and a separate frontend app, the project uses Inertia.js to share data between Laravel and Vue through a single request cycle. This significantly reduces complexity — there is no API versioning, no authentication token management on the frontend, and no duplication of validation logic. The result is a modern, fast, app-like interface built almost entirely with familiar Laravel patterns.

## Challenges & What I Learned

> **TODO:** Challenges & What I Learned

One of the hardest problems I faced wasn't a bug — it was figuring out how to generate properly formatted documents. I initially tried building the print layout purely in HTML, but the output was always distorted and didn't match what the organization actually used. The breakthrough came when I realized I could use a real Word document as the template, with placeholders inside it, and have the system fill those placeholders with the form data. The HTML design I built is just a preview — the actual output goes into the Word template, which preserves the exact formatting. Before this project, I had no idea that approach was even possible. I also had to deeply understand the organization's actual workflow — how they handle OFIs, DCRs, and CARs in real life — while already being in the middle of development, which forced me to refactor parts of the system. If I could start over, I would define the template system and understand the organization's processes first before writing a single line of code.

---

_Generated from source code analysis_
