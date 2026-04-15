## 🧠 Project Overview

This is a **Laravel + Vue (Inertia.js)** Quality Management System (QMS) application.

### Core Stack

- Backend: Laravel 11/12 (PHP 8.2+)
- Frontend: Vue 3 + Inertia.js
- Styling: TailwindCSS
- Database: MySQL
- File Processing: LibreOffice (headless) for DOCX → PDF conversion

---

## 📦 Core Modules

- Documents (Document Types & Uploads)
- DCR (Document Change Request)
- OFI (Opportunities for Improvement)
- CAR (Corrective Action Request)
- Manuals (ASM, QSM, HRM, RIEM, REM)
- Performance Forms (IPCR, DPCR, UPCR)
- Activity Logs

---

## 🏗️ Architecture Principles

### 1. Template System (CRITICAL)

All templates must follow a **database-driven architecture**.

#### Rules:

- Templates MUST NOT be hardcoded in controllers
- Always resolve templates using `QmsTemplateResolver`
- Each module (DCR, OFI, CAR) must have:
    - its own templates
    - its own dynamic fields

- Only ONE active template per module

#### Required Pattern:

```php
$templatePath = $this->qmsTemplateResolver->getActiveTemplatePath('DCR');
```

---

### 2. Dynamic Fields System

Dynamic fields are configurable per module.

#### Rules:

- Must be stored in the database
- Must be validated server-side (NOT only Vue)
- Must NOT override base template placeholders

#### Merge Order (IMPORTANT):

```php
$values = array_merge($dynamicFields, $baseFields);
```

✔ Base fields MUST override dynamic fields

---

### 3. File Storage & Security (CRITICAL)

#### Rules:

- ALL uploads must use `private` disk
- NEVER expose `Storage::url()` for protected files
- Access files ONLY through controller routes

#### Correct:

```php
Storage::disk('private')->put(...)
route('documents.uploads.preview')
route('documents.uploads.download')
```

#### Forbidden:

```php
Storage::url(...)
```

---

### 4. Document Upload System

#### Revision-Controlled Documents:

- MUST use:
    - `DB::transaction()`
    - `lockForUpdate()`

- Only ONE active revision allowed

#### Multi-file Uploads:

- Prefer all-or-nothing (atomic behavior)
- Clean up files on failure

---

### 5. Controllers (IMPORTANT)

Controllers must remain **thin**.

#### Rules:

- NO heavy business logic inside controllers
- Use Services for:
    - template resolution
    - document generation
    - preview/download logic

- Avoid duplication across DCR, OFI, CAR

---

### 6. Services Architecture

#### Shared services must be reusable:

Use:

- `QmsTemplateResolver`
- `QmsDynamicFieldValidator`

Avoid:

- Duplicating services per module unless necessary

---

### 7. Module Separation

Each module (DCR, OFI, CAR) must:

- remain logically independent
- have separate templates and fields
- share common infrastructure

❌ DO NOT:

- copy-paste DCR logic into OFI/CAR

✔ DO:

- generalize into shared services

---

### 8. Preview & Download System

#### Rules:

- All preview/download must go through:
    - `DocumentPreviewService`
    - `DocumentDownloadService`

- Office files must be converted to PDF using LibreOffice
- Generated files must use temporary storage and auto-delete

---

### 9. Activity Logging

#### Rules:

- Log ALL important actions:
    - preview
    - download
    - upload
    - approve/reject

- Logging should happen AFTER successful execution when possible

---

### 10. Authorization (CRITICAL)

#### Rules:

- ALL sensitive actions must be protected server-side
- Do NOT rely on frontend-only restrictions

Use:

```php
abort_unless(auth()->user()?->is_admin, 403);
```

or Laravel Policies

---

### 11. Routes & API Design

- Follow existing route naming conventions
- Keep consistency across modules:
    - `/dcr/...`
    - `/ofi/...`
    - `/car/...`

Dynamic fields endpoints:

```bash
/dcr/dynamic-fields
/ofi/dynamic-fields
/car/dynamic-fields
```

---

### 12. Frontend (Vue + Inertia)

#### Rules:

- Use existing layouts (e.g., AdminLayoutWithHeader)
- Follow existing UI/UX patterns
- Avoid introducing new patterns unless necessary

---

### 13. Naming Conventions

- Document codes:

    ```
    {SERIES}-{3 digit number}
    Example: F-QMS-001
    ```

- Use uppercase for module identifiers:

    ```
    DCR, OFI, CAR
    ```

---

### 14. Performance Forms (SPECIAL CASE)

- Use:
    - performance_category
    - performance_record_type
    - year
    - period (JAN_JUN / JUL_DEC)

- Do NOT treat as standard document types

---

### 15. Deletion Rules

#### Rules:

- Prevent deletion if referenced by:
    - OFI
    - DCR
    - CAR

- Clean files AFTER DB commit

---

### 16. Important Constraints

- ❌ DO NOT hardcode template paths
- ❌ DO NOT expose public file URLs
- ❌ DO NOT duplicate module logic
- ❌ DO NOT skip server-side validation
- ❌ DO NOT bypass authorization

---

## 🚀 Development Guidelines for Codex

When modifying this project:

1. Always check existing patterns before introducing new ones
2. Prefer reuse over duplication
3. Maintain consistency with DCR implementation
4. Follow service-based architecture
5. Do not break existing workflows
6. Ask for clarification if unsure about module behavior

---

## 🏁 Goal

Maintain a **secure, scalable, and reusable QMS architecture**
where DCR, OFI, and CAR share infrastructure but remain modular.
