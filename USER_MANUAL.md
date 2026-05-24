# QMS Archive System — User Manual

**System:** QMS Archive System
**Institution:** Leyte Normal University — QMS (ISO) Office

---

## Table of Contents

1. [Overview](#overview)
2. [Logging In and Out](#logging-in-and-out)
3. [Dashboard](#dashboard)
4. [Documents](#documents)
5. [Document Change Request (DCR)](#document-change-request-dcr)
6. [Opportunity for Improvement (OFI)](#opportunity-for-improvement-ofi)
7. [Corrective Action Report (CAR)](#corrective-action-report-car)
8. [Performance](#performance)
9. [Manuals](#manuals)
10. [Inbox](#inbox)
11. [Users](#users)
12. [Settings](#settings)
13. [Notifications](#notifications)
14. [User Roles Summary](#user-roles-summary)

---

## Overview

The QMS Archive System is a web-based document management and quality records system used by the QMS (ISO) Office. It allows staff to:

- Store and manage controlled documents (procedures, forms, manuals)
- Create and track quality records — DCR, OFI, and CAR forms
- Monitor performance documents (IPCR, DPCR, UPCR)
- Route records for approval through an admin inbox workflow
- Receive real-time notifications on record status changes

The system has two roles: **Admin** and **Admin Officer**. Admins have full access to all modules. Admin Officers can create and manage their own records and view documents they have permission to access.

---

## Logging In and Out

### Logging In

1. Open the system URL in a web browser.
2. On the Login page, enter your **Username** and **Password**.
3. Click **Login**.
4. Admins are redirected to the Admin Dashboard. Admin Officers are redirected to the User Dashboard.

> If your credentials are incorrect, an error message will appear under the username field. Contact your administrator to reset your password.

### Logging Out

Click your profile/avatar in the navigation bar, then click **Logout**. You will be redirected to the Login page.

---

## Dashboard

The dashboard shown depends on your role.

### Admin Dashboard

The Admin Dashboard shows a system-wide overview:

- **Summary cards** — Total document types, active vs. obsolete counts, total uploads, and pending QMS form counts (OFI, DCR, CAR)
- **Documents Needing Revision** — List of document types flagged for revision that have no current active upload
- **Recent Uploads** — The five most recently uploaded documents
- **Series Distribution** — Chart of document types per series (F, QP, WI, etc.)
- **Recent QMS Activity** — The five most recently updated OFI, DCR, and CAR records across all users
- **Yearly Statistics** — Bar chart of OFI/DCR/CAR records created and closed per year

### User Dashboard

The User Dashboard shows information specific to the logged-in user:

- **Summary cards** — Your own OFI, DCR, and CAR counts broken down by draft, pending, approved, and rejected
- **Needs Attention** — Records that were rejected by admin and need correction
- **My Drafts** — Records you started but have not yet submitted
- **Pending Records** — Records you submitted that are awaiting admin approval
- **Recent Activity** — Your five most recently updated records

---

## Documents

> **Access:** Admin only. All Document module operations — browsing, viewing, uploading, previewing, and downloading — require Admin access. Admin Officers cannot access this module. Anonymous (unauthenticated) access is not possible.

The Documents module stores all controlled QMS documents organized by series and type.

### Viewing the Document List

1. Click **Documents** in the sidebar.
2. The list shows all document types grouped by series.
3. Use the **search bar** to filter by document code, title, or status.
4. Use the **Series filter** to show only a specific document series.
5. Use the **Status filter** to show Active or Obsolete document types.
6. Toggle between **Group view** (documents grouped by series) and **List view** using the view buttons.

### Viewing a Document Type

Click any document type row to open its detail page. Here you can see:

- Document code, title, series, revision history
- Active upload (current version) — preview or download it
- All previous uploads (version history)

### Uploading a New Document Version (Admin Only)

1. Open a document type.
2. Click **Upload New Version**.
3. Select the file (PDF, DOC, DOCX supported).
4. Enter a revision number and optional remarks.
5. Click **Upload**. The previous active version is automatically marked Obsolete.

### Creating a New Document Type (Admin Only)

1. On the Documents list, click **Add Document Type**.
2. Fill in the code, title, series, and other fields.
3. Click **Save**.

### Marking a Document Type as Obsolete (Admin Only)

Open a document type, then click **Mark as Obsolete**. The document type and its uploads will be marked Obsolete but remain in the system for historical reference.

### Deleting a Document Type (Admin Only)

Only document types with no uploads can be deleted. Open the document type and click **Delete**.

### Previewing and Downloading

- Click **Preview** to view a document inline in the browser (supported for PDF and Office files).
- Click **Download** to save the file to your computer.

> Office files (DOCX, XLSX, PPTX) are converted to PDF for preview. The first preview of a document may take a few seconds.

> All preview and download actions require an active Admin session. Anonymous (unauthenticated) access to documents is not possible.

---

## Document Change Request (DCR)

> **Access:** All authenticated users can create DCR records. Admin Officers can only view and edit records they personally created — they cannot access records created by other users. Admin can view and manage all records, and can approve, reject, and publish.

A DCR (Document Change Request, form code R-QMS-013) is used to formally request changes to documents.

### Creating a New DCR

1. Click **DCR** in the sidebar.
2. A blank DCR form opens.
3. Fill in the form fields (DCR No., To/For, From, and the dynamic fields for the change request).
4. Click **Save Draft** to save without submitting, or **Submit to Admin** to send for approval.

> The DCR No. and other header fields are optional at save time but required before submission.

### Editing a Saved DCR

1. Click **DCR** in the sidebar.
2. If you have a draft or rejected DCR, the form will load it automatically via the URL parameter. Otherwise, navigate from the Inbox → My Records.
3. Make your changes.
4. Click **Save Draft** to save, or **Submit to Admin** to re-submit.

> Records with **Pending** or **Approved** workflow status cannot be edited by regular users.

### DCR Workflow Statuses

| Status | Meaning |
|--------|---------|
| *(no status)* | Draft — not yet submitted |
| Pending | Submitted to admin, awaiting decision |
| Approved | Admin approved the record |
| Rejected | Admin rejected the record — requires correction |

### Admin Actions on DCR

Admins have additional controls on DCR forms:

- **Generate DOCX** — Generate a Word document of the current DCR data
- **Download** — Download the generated DOCX
- **Publish** — Generate and save the DOCX as an official document upload linked to this DCR record
- **Approve** (from Inbox) — Approves the DCR and auto-publishes it
- **Reject** (from Inbox) — Returns the DCR to the creator with a rejection reason
- **Update Resolution Status** — Change the resolution status from Open → Ongoing → Closed

### Resolution Statuses

| Status | Meaning |
|--------|---------|
| Open | Change request is open |
| Ongoing | Change is being implemented |
| Closed | Change has been completed |

> Resolution status can only move forward (Open → Ongoing → Closed). It cannot be reversed.

---

## Opportunity for Improvement (OFI)

> **Access:** All authenticated users can create OFI records. Admin Officers can only view and edit records they personally created — they cannot access records created by other users. Admin can view and manage all records, and can approve, reject, and publish.

An OFI (Opportunity for Improvement, form code R-QMS-018) records identified opportunities to improve processes or systems.

### Creating a New OFI

1. Click **OFI Form** in the sidebar or navigate to `/ofi-form`.
2. Fill in the OFI form fields (OFI No., Ref No., To, and dynamic form fields).
3. Click **Save Draft** or **Submit to Admin**.

### OFI Workflow

The OFI workflow follows the same pattern as DCR:

1. User creates and saves a draft
2. User submits to admin
3. Admin approves (auto-publishes) or rejects with a reason
4. If rejected, user corrects and resubmits
5. Admin updates resolution status (Open → Ongoing → Closed)

All DCR workflow and resolution status rules apply identically to OFI.

---

## Corrective Action Report (CAR)

> **Access:** All authenticated users can create CAR records. Admin Officers can only view and edit records they personally created — they cannot access records created by other users. Admin can view and manage all records, and can approve, reject, and publish.

A CAR (Corrective Action Report, form code R-QMS-017) documents nonconformities and the corrective actions taken.

### Creating a New CAR

1. Click **CAR Form** in the sidebar or navigate to `/car`.
2. Fill in the CAR form fields (CAR No., Ref No., Dept/Section, Auditor, and dynamic form fields).
3. Click **Save Draft** or **Submit to Admin**.

### CAR Workflow

The CAR workflow follows the same pattern as DCR and OFI:

1. User creates draft → submits → admin approves or rejects
2. Approved CARs are auto-published as document uploads
3. Admin updates resolution status (Open → Ongoing → Closed)

---

## Performance

> **Access:** Admin only. Admin Officers have no access to the Performance module — they cannot browse, upload, preview, or download performance files.

The Performance module stores IPCR, DPCR, and UPCR performance evaluation files.

### Browsing Performance Files

1. Click **Performance** in the sidebar.
2. Select a **Category** tab: IPCR, DPCR, or UPCR.
3. Select a **Record Type**: Target or Accomplishment.
4. Select a **Year**.
5. Select a **Period**: January–June or July–December.
6. The file list for that selection appears below.

Use the **search bar** to filter files by name, remarks, or uploader. Use the **sort** dropdown to order by latest, oldest, or filename.

### Uploading Performance Files

1. Navigate to a specific Category, Record Type, Year, and Period.
2. Click **Upload Files**.
3. Select one or more files (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, GIF, WEBP — max 20 MB each).
4. Enter optional remarks.
5. Click **Upload**.

### Previewing and Downloading

- Click **Preview** to view a file inline.
- Click **Download** to save the file.

---

## Manuals

> **Access:** All logged-in users can view manuals they have permission to access. Upload requires Admin access.

The Manuals module stores the institution's controlled and uncontrolled procedure manuals, organized by category.

### Manual Categories

| Category | Full Name |
|----------|-----------|
| ASM | Administrative Services Manual |
| QSM | Quality Systems Manual |
| HRM | Human Resource Manual |
| RIEM | Records and Information, Evidence Manual |
| REM | Records and Evidence Manual |

### Viewing a Manual

1. Click the relevant manual category in the sidebar (e.g., **QSM**).
2. The page shows two sections: **Controlled** and **Uncontrolled**.
3. The active (current) version is shown at the top.
4. Version history is shown below.

> Visibility of controlled vs. uncontrolled sections depends on your access permissions. Contact your administrator if you cannot see a section you need.

### Uploading a Manual (Admin Only)

1. Navigate to the relevant manual category page.
2. Click **Upload** in the Controlled or Uncontrolled section.
3. Select the file (PDF, DOC, DOCX — max 20 MB).
4. Enter a revision number (optional) and remarks (optional).
5. Click **Upload**. The previous active version is automatically marked Obsolete.

### Previewing and Downloading

- Click **Preview** to view the manual inline.
- Click **Download** to save it.

---

## Inbox

The Inbox module has two views depending on your role.

### Admin Inbox

> **Access:** Admin only

1. Click **Inbox** in the sidebar.
2. The Admin Inbox shows all submitted OFI, DCR, and CAR records from non-admin users awaiting review.
3. Use the **workflow status filter** (Pending, Approved, Rejected) and the **type filter** (All, OFI, CAR, DCR) to narrow the list.
4. Click a record row to open the form and review the submission.

**To approve a record:**
1. Open the record from the Inbox.
2. Review the submitted data.
3. Click **Approve**. The record is approved and automatically published as a document.

**To reject a record:**
1. Open the record from the Inbox.
2. Click **Reject**.
3. Enter a rejection reason (required).
4. Click **Confirm Reject**. The record is returned to the creator with your reason.

### My Records (User Inbox)

> **Access:** Admin Officer (non-admin users)

1. Click **My Records** in the sidebar (or go to `/my-records`).
2. View all your OFI, DCR, and CAR records in one unified list.
3. Filter by workflow status and record type.
4. Click **View** on any record to open and edit it.

> Records with status **Rejected** need your attention — open them, correct the issue based on the rejection reason, and resubmit.

---

## Users

> **Access:** Admin only

The Users module lets admins manage system accounts.

### Viewing the User List

1. Click **Users** in the sidebar.
2. The list shows all users with their name, username, email, role, position, department, and office.
3. Use the **search bar** to filter by name, username, email, position, department, or role.

### Creating a New User

1. Click **Add User**.
2. Fill in the required fields:
   - **Username** — Must be unique, letters/numbers/dashes/underscores only
   - **Full Name**
   - **Email** — Must be unique
   - **Role** — Admin or Admin Officer
   - **Password** — Minimum 8 characters
3. Optional fields: Position, Department, Office Location.
4. Click **Save**.

### Resetting a Password

1. Find the user in the list.
2. Click the **Reset Password** button for that user.
3. Enter and confirm the new password.
4. Click **Save**.

> Admins cannot reset another admin's password (only their own).

### Deleting a User

Click the **Delete** button for a user. You cannot delete your own account.

---

## Settings

### Profile Settings (All Users)

1. Click your profile/avatar or navigate to **Settings**.
2. Under **Profile**, update your:
   - First, middle, and last name
   - Email address
   - Position, department, office location
   - Profile photo (JPG, PNG, WEBP — max 2 MB)
3. To change your password, enter your current password, then the new password and confirmation.
4. Click **Save Profile**.

### General Settings (Admin Only)

Under the **General** tab in Settings, admins can update:

- **System Name** — Displayed in the browser tab and system header
- **Institution Name** — Name of the institution
- **Office Name** — Name of the office
- **Maintenance Mode** — Toggles system maintenance mode

Click **Save General Settings**.

### E-Signature (Admin Only)

Under **E-Signature**, admins can upload an authorized e-signature image that is embedded into generated QMS form documents (DOCX).

- Click **Upload Signature** and select an image (JPG, PNG, WEBP — max 2 MB).
- To remove the current signature, click **Remove Signature**.

### System Logo (Admin Only)

Under **Logo**, admins can upload the institution logo.

- Click **Upload Logo** and select an image (JPG, PNG, WEBP — max 2 MB).
- To remove the current logo, click **Remove Logo**.

### Backup (Admin Only)

Under **Backup**, admins can manage system backups:

- **Create Backup** — Creates a ZIP archive containing all uploaded document files and a full database snapshot (`database.json`). Rate-limited to 3 requests per hour.
- **Download Latest Backup** — Downloads the most recently created backup ZIP file.
- **Restore from Backup** — Upload a previously downloaded `.zip` backup file to restore both uploaded document files and all database records. The database restore is atomic — if any error occurs during restore, no database changes are committed.
- **Backup Settings** — Configure automatic backup frequency (Daily, Weekly, Monthly), storage location, and auto-backup toggle.

> **Warning:** Restoring from a backup overwrites existing database records with the backup's data. This action cannot be undone. Always test restores on a staging environment before applying to production data.

> **Note:** Backup archives created before May 2026 contain uploaded files only. Restoring one of these older archives will restore document files but will not restore any database records.

### QMS Template Settings (Admin Only)

Admins can upload and manage the DOCX templates used to generate OFI, CAR, and DCR form documents.

- Navigate to Settings → the relevant template section (OFI Templates, CAR Templates, DCR Templates).
- Upload a new `.docx` template file.
- Activate a template version to make it the one used for document generation.
- Manage dynamic fields (custom form fields embedded in the template).

---

## Notifications

Real-time notifications appear in the notification bell icon in the top navigation bar.

- The bell shows an unread count badge when you have new notifications.
- Click the bell to view recent notifications.
- You receive notifications when:
  - A record you submitted is **approved** or **rejected** by admin
  - A record is submitted to admin for approval (admin receives this)
- Click **Mark All Read** to clear the unread count.
- Click an individual notification to mark it as read.

---

## User Roles Summary

| Feature | Admin | Admin Officer |
|---------|-------|---------------|
| Admin Dashboard | ✓ | — |
| User Dashboard | — | ✓ |
| View Documents | ✓ | — |
| Upload Documents | ✓ | — |
| Create Document Types | ✓ | — |
| Mark Document Obsolete | ✓ | — |
| Create DCR / OFI / CAR | ✓ | ✓ (own records only) |
| Submit DCR / OFI / CAR | ✓ | ✓ (own records only) |
| Approve / Reject Records | ✓ | — |
| Publish Records | ✓ | — |
| Update Resolution Status | ✓ | — |
| Admin Inbox | ✓ | — |
| My Records (User Inbox) | — | ✓ |
| Performance Module | ✓ | — |
| Manuals — View | ✓ | ✓ (by permission) |
| Manuals — Upload | ✓ | — |
| Users Management | ✓ | — |
| System Settings | ✓ | — |
| Backup | ✓ | — |
| QMS Template Settings | ✓ | — |
| Profile Settings | ✓ | ✓ |

> **Note on DCR / OFI / CAR record access:** Admin Officers can only view and edit records they personally created. Attempting to access a record created by another user returns a 403 Forbidden error. Admins can view and manage all records regardless of creator.

---

*For technical support or account issues, contact your system administrator.*
