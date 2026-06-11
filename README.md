# Akabbo Social Fund
## Enterprise Savings & Loan Management System (ESLMS) — v1.0.0

> **"Growing Together, Prospering Together"**
>
> A secure, scalable, mobile-first web platform purpose-built for SACCOs,
> savings groups, investment clubs and financial cooperatives.

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Technology Stack](#technology-stack)
3. [Architecture](#architecture)
4. [Modules & Features](#modules--features)
   - [Authentication & Security](#1-authentication--security)
   - [Member Management](#2-member-management)
   - [Groups Management](#3-groups-management)
   - [Savings Management](#4-savings-management)
   - [Loan Management](#5-loan-management)
   - [Loan Products](#6-loan-products)
   - [Shares Management](#7-shares-management)
   - [Social Fund Fees](#8-social-fund-fees)
   - [Fund Transfers](#9-fund-transfers)
   - [Expenses Tracking](#10-expenses-tracking)
   - [Approval Workflow](#11-approval-workflow)
   - [Transactions](#12-transactions)
   - [Reports & Analytics](#13-reports--analytics)
   - [Import / Export](#14-import--export)
   - [Notifications](#15-notifications)
   - [Audit Log](#16-audit-log)
   - [Trash & Recovery](#17-trash--recovery)
   - [System Settings](#18-system-settings)
   - [User Management & RBAC](#19-user-management--rbac)
5. [Role-Based Access Control](#role-based-access-control)
6. [Database Schema](#database-schema)
7. [Directory Structure](#directory-structure)
8. [Installation Guide](#installation-guide)
9. [Default Credentials](#default-credentials)
10. [Security Features](#security-features)
11. [API / AJAX Endpoints](#api--ajax-endpoints)
12. [Approval Workflow Rules](#approval-workflow-rules)
13. [Share Privilege System](#share-privilege-system)
14. [Member Number Generation](#member-number-generation)
15. [Known Limitations & Roadmap](#known-limitations--roadmap)

---

## System Overview

**Akabbo Social Fund** is a production-grade PHP web application that centralises
all financial operations of a SACCO or cooperative into a single integrated platform.

### Core Objectives

| Objective | How Achieved |
|-----------|-------------|
| Digitise savings operations | Full deposit/withdrawal ledger with real-time balance |
| Transparent loan management | Complete lifecycle from application to completion |
| Member financial identity | Per-member savings accounts, loan history, share holdings |
| Accountability & governance | Mandatory approval notes, full audit trail, role separation |
| Operational efficiency | Bulk CSV import, automated fee generation, AJAX interactions |
| Scalability | Modular MVC architecture, optimised queries, pagination everywhere |
| Security | CSRF, bcrypt, session lockout, SQL injection prevention via PDO |

---

## Technology Stack

| Layer | Technology | Notes |
|-------|-----------|-------|
| **Backend** | PHP 8.1+ | OOP, namespaced, PSR-4 autoloaded |
| **Frontend** | Tailwind CSS (CDN) | Mobile-first, utility-first |
| **Interactions** | Vanilla JavaScript + Fetch API | No jQuery dependency |
| **Database** | MySQL 8 / MariaDB 10.5+ | utf8mb4, InnoDB, prepared statements |
| **Charts** | Chart.js 4 (CDN) | Dashboard bar/donut, cash-flow |
| **Icons** | Font Awesome 6 (CDN) | Consistent icon language |
| **Fonts** | Plus Jakarta Sans + Fraunces | Professional + brand identity |
| **Auth** | PHP Session-based | Bcrypt passwords, lockout, CSRF |
| **Server** | Apache 2.4 / Nginx | .htaccess routing, WAMP compatible |
| **OS** | Windows (WAMP) / Linux | Path separator handled via DIRECTORY_SEPARATOR |

---

## Architecture

The application follows a **custom MVC-like pattern** with a single front-controller:

```
HTTP Request
    │
    ▼
public/index.php          ← Entry point; sets security headers
    │
    ▼
routes/web.php            ← Pattern-matching router (no framework)
    │
    ▼
App\Controllers\*         ← Controller handles request
    │          │
    ▼          ▼
App\Models\*   App\Services\*     ← Business logic & DB access
    │
    ▼
Database (PDO)            ← Singleton; prepared statements only
    │
    ▼
resources/views/**/*.php  ← PHP template rendered into layout
    │
    ▼
resources/layouts/main.php ← Sidebar + topbar shell
```

### Key Engineering Decisions

- **No framework** — zero external Composer dependencies; fully portable
- **Singleton Database** — one PDO connection per request; no ORM overhead
- **Eager controller loading** — all controller files required at startup for Windows compatibility
- **DIRECTORY_SEPARATOR** — all file paths safe on Windows (WAMP) and Linux
- **CSRF on every state-change** — hidden token + X-CSRF-Token header for AJAX
- **Soft deletes** — members, loans, expenses moved to Trash, never hard-deleted immediately
- **Atomic member sequences** — `SELECT … FOR UPDATE` prevents duplicate member numbers during concurrent imports
- **Reusable helpers** — sharable utilities reused acros views
- **Approval** — all transactions with funds out should apply Approval workflow (withdraw, loan requests, disbursments, reversals, grants, etc)

---

## Modules & Features

---

### 1. Authentication & Security

| Feature | Detail |
|---------|--------|
| Secure login | Email + bcrypt-hashed password |
| Account lockout | Configurable max attempts (default 5); auto-unlock after 15 min |
| Session management | 30-minute timeout; regenerated ID on login |
| CSRF protection | Token in every form + X-CSRF-Token header for AJAX |
| Security headers | X-Frame-Options, X-Content-Type-Options, CSP, Referrer-Policy (PHP-based; no mod_headers required) |
| Password reset | Email token flow; 1-hour expiry |
| Must-change-password | Flag for new/reset accounts |
| Remember me | 7-day persistent cookie option |
| Multi-session tracking | `login_sessions` table records IP, user-agent, timestamps |
| HTTPS enforcement | Automatic redirect in production mode |

**Routes:** `GET /login`, `POST /auth/login`, `GET /auth/logout`,
`GET|POST /auth/forgot-password`, `GET|POST /auth/reset-password`

---

### 2. Member Management

The member is the core entity of the system. Every financial activity is tied to a member.

| Feature | Detail |
|---------|--------|
| 4-step registration wizard | Personal → Contact & KYC → Next of Kin → Membership |
| Member number | Format `AKB-00001`; **atomic conflict-free generation** (see §14) |
| KYC verification | Officer marks identity as verified; logged with timestamp and officer name |
| Photo upload | Avatar stored in `storage/uploads/avatars/` |
| Document upload | ID front/back scans stored in `storage/uploads/documents/` |
| Group assignment | Member assigned to a savings group |
| Status management | Active / Inactive / Suspended / Exited |
| Member search | AJAX live search by name, phone, member number |
| Edit member | Full profile edit with group reassignment |
| Printable statement | Full account statement: savings, loans, transaction ledger — no layout, print-ready |
| Member export | CSV download of all members with savings totals |
| Soft delete | Moved to Trash with active-loan guard |
| Share flag | `is_shareholder` and `shares_held` columns auto-updated |
| Next of kin | Full contact + relationship tracking |
| Savings summary | Total savings, active loan balance shown on profile |
| Overdue loan tracking | Dashboard widget + member-level overdue installments |

**Routes:** `GET /members`, `GET /members/create`, `POST /members/store`,
`GET /members/{id}`, `GET /members/{id}/edit`, `POST /members/{id}/update`,
`POST /members/{id}/delete`, `POST /members/{id}/kyc-verify`,
`GET /members/{id}/statement`, `GET /members/search`, `GET /members/export`

---

### 3. Groups Management

Organise members into savings groups, SACCOs or investment clubs.

| Feature | Detail |
|---------|--------|
| Group code | Auto-generated: `GRP-0001` |
| Leadership | Chairperson, Treasurer, Secretary (linked to member records) |
| Meeting schedule | Free-text meeting schedule description |
| Member listing | All group members with savings totals |
| Group stats | Member count, total savings, active loans |
| Status | Active / Inactive / Closed |
| Guard on delete | Cannot delete a group that has members |

**Routes:** `GET /groups`, `GET /groups/create`, `POST /groups/store`,
`GET /groups/{id}`, `GET /groups/{id}/edit`, `POST /groups/{id}/update`,
`POST /groups/{id}/delete`, `GET /groups/list`

---

### 4. Savings Management

| Feature | Detail |
|---------|--------|
| Account types | Regular, Fixed, Target, Share |
| Deposit | Records to savings account; updates balance; logs transaction |
| Withdrawal | Balance lockup validation: minimum savings + active loan collateral |
| Min savings balance | Configurable; default 10,000 UGX |
| Withdrawal approval | All withdrawals above threshold routed to approval workflow |
| Account status | Active / Dormant / Frozen / Closed |
| Interest application | Configurable per-account interest rate; batch apply function |
| Account history | Full transaction ledger per account |
| Member savings summary | Aggregated totals on member profile and dashboard |

**Routes:** `GET /savings`, `GET /savings/deposit`, `POST /savings/deposit`,
`GET /savings/withdraw`, `POST /savings/withdraw`, `GET /savings/{id}`

---

### 5. Loan Management

Complete loan lifecycle management from application to final repayment.

#### Lifecycle

```
Draft → Pending → Approved → Disbursed/Active → Completed
                ↘ Rejected
                            ↘ Defaulted / Written-off
```

| Feature | Detail |
|---------|--------|
| Loan application | Member, product, amount, term, purpose, disbursement method |
| Loan number | Format `LN-2025-00001`; year-prefixed sequential |
| EMI calculator | Live JavaScript calculator (flat / reducing balance / compound) |
| Repayment schedule | Auto-generated on application; regenerated on edit |
| Shareholder discount | Interest rate automatically discounted for shareholders |
| Approval workflow | Approve/Reject require mandatory notes (recorded in audit) |
| Disbursement approval | Secondary approval step before funds released |
| Repayment recording | Distributed across oldest-first installments |
| Penalty tracking | `penalty_accrued` column; overdue installment marking |
| Guarantors | Multiple guarantors with consent tracking |
| Edit (draft/pending) | Regenerates schedule on save |
| Soft delete | Active/disbursed loans cannot be deleted |
| Loan history | Full repayment history per loan |
| Schedule viewer | Colour-coded: paid (green), due (amber), overdue (red) |

**Routes:** `GET /loans`, `GET /loans/create`, `POST /loans/store`,
`GET /loans/{id}`, `GET /loans/{id}/edit`, `POST /loans/{id}/update`,
`POST /loans/{id}/approve`, `POST /loans/{id}/reject`, `POST /loans/{id}/disburse`,
`POST /loans/{id}/delete`, `POST /loans/repayment`, `GET /loans/{id}/schedule`,
`GET /loans/calculate`

---

### 6. Loan Products

Configurable loan product types that drive all loan parameters.

| Field | Description |
|-------|-------------|
| Name & Code | e.g. "Emergency Loan — EMRG" |
| Interest rate | Annual percentage rate |
| Interest type | Flat rate / Reducing balance / Compound |
| Min / Max amount | Amount range members can apply for |
| Min / Max term | Acceptable term range in months |
| Processing fee % | Deducted from principal at disbursement |
| Insurance fee % | Optional insurance contribution |
| Requires guarantor | Forces guarantor section in application form |
| Requires collateral | Flags collateral requirement |
| Max loan multiplier | Loan limit = multiplier × member savings |
| Shareholder rate discount | Additional rate discount for shareholders |
| Shareholder multiplier bonus | Higher multiplier for shareholders |
| Status | Active / Inactive |
| Live preview | JavaScript calculator shows sample EMI as officer types |

**Default products:** Emergency, Normal, Business, School Fees, Development

**Routes:** `GET /loan-products`, `GET /loan-products/create`, `POST /loan-products/store`,
`GET /loan-products/{id}/edit`, `POST /loan-products/{id}/update`,
`POST /loan-products/{id}/toggle`

---

### 7. Shares Management

Members can purchase shares in the SACCO, gaining enhanced financial privileges.

#### Share Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| Par value | UGX 1,000 | Price per share unit |
| Min shares | 1 | Minimum purchase |
| Max shares per member | 1,000 | Upper limit |
| Loan rate discount | 2.00% | Interest discount vs standard rate |
| Loan multiplier bonus | +1× | Extra loan headroom |
| Additional discount (≥100 shares) | 1.00% | Reward for major shareholders |
| Dividend rate | 5.00% p.a. | Annual dividend distribution |
| Transferable | No (configurable) | Whether shares can transfer between members |

#### Share Transactions

| Type | Description |
|------|-------------|
| Purchase | Member buys new shares (requires approval) |
| Sale | Member sells shares back |
| Transfer In/Out | Share transfer between members |
| Dividend | Dividend distribution recorded |
| Refund | Exit/refund of share capital |

#### Approval Requirement
All share transactions go through the **approval workflow**. The approver
must provide mandatory notes. On approval, the member's `is_shareholder`
flag and `shares_held` count are automatically updated.

#### Payment by Savings Deduction
If payment method is "Savings Deduction", the system automatically debits
the member's savings account upon approval.

#### Loan Privileges for Shareholders
When a shareholder applies for a loan, the system automatically applies:
- Reduced interest rate (base rate minus discount)
- Higher loan ceiling (base multiplier plus bonus multiplier)
- Further 1% discount if member holds ≥ 100 shares

**Routes:** `GET /shares`, `GET /shares/create`, `POST /shares/store`,
`GET /shares/member/{id}`, `GET /shares/report`,
`POST /shares/{id}/approve`, `POST /shares/{id}/reject`,
`POST /shares/config/update`

---

### 8. Social Fund Fees

Configurable periodic fees charged to members to fund SACCO operations.

| Feature | Detail |
|---------|--------|
| Multiple fee configs | Different fees for different periods or member groups |
| Frequency options | Monthly / Quarterly / Annually / One-time |
| Applies to | All members / Shareholders only / Non-shareholders only |
| Due day | Configurable day of month (1–28) |
| Grace period | Days after due date before penalty applies |
| Late penalty | Fixed amount penalty for late payment |
| Mandatory flag | Cannot be waived without authorisation |
| Generate period | Batch-create fee records for all applicable members |
| Payment recording | Cash, mobile money, bank transfer, savings deduction |
| Waiver | Authorised personnel can waive individual fee records (reason required) |
| Overdue marking | Manual/scheduled marking of overdue records |
| Arrears report | Members with outstanding fee balances |
| Payment history | Per-member fee payment history |
| Financial integration | Payment automatically creates a `membership_fee` transaction record |

**Routes:** `GET /social-fund`, `GET /social-fund/create`, `POST /social-fund/store`,
`POST /social-fund/{id}/update`, `GET /social-fund/payments`,
`POST /social-fund/generate-period`, `POST /social-fund/record-payment`,
`POST /social-fund/waive`, `POST /social-fund/mark-overdue`

---

### 9. Fund Transfers

Member-to-member internal savings transfers with full approval workflow.

| Feature | Detail |
|---------|--------|
| Description required | Transfer reason is mandatory |
| Approval workflow | All transfers routed to approval queue |
| Balance validation | Sender's available balance checked before submission |
| Atomic execution | Debit + credit in one database transaction |
| Debit/credit records | Two transaction records created on approval (debit sender, credit receiver) |
| Reversal | Completed transfers can be reversed (reversal notes mandatory) |
| Status tracking | Pending → Approved/Rejected → Completed / Reversed |
| Approval notes | Approver must state reason in mandatory notes field |

**Routes:** `GET /transfers`, `GET /transfers/create`, `POST /transfers/store`,
`GET /transfers/{id}`, `POST /transfers/{id}/reverse`

---

### 10. Expenses Tracking

Full operational expense management for the SACCO.

#### Expense Categories (default)

Office Supplies · Utilities · Staff Costs · Travel & Transport ·
Meetings & Events · Software & Systems · Loan Write-offs ·
Auditing & Legal · Marketing · Miscellaneous

| Feature | Detail |
|---------|--------|
| Expense reference | Auto-generated: `EXP-2025-0001` |
| Category | Linked to expense categories table |
| Payee tracking | Name, contact, receipt number |
| Receipt upload | JPG, PNG or PDF stored in `storage/uploads/receipts/` |
| Approval workflow | All expenses submitted to approval queue |
| Mark as paid | Separate "paid" step after approval (requires payment notes) |
| Period tracking | Month + year fields for budget period reporting |
| Soft delete | Paid expenses cannot be deleted |
| CSV export | Full expense list exportable to CSV |
| Category analytics | Breakdown by category with totals |
| Monthly trend | 12-month expense trend chart |
| Edit guard | Only draft/pending expenses editable |

**Routes:** `GET /expenses`, `GET /expenses/create`, `POST /expenses/store`,
`GET /expenses/{id}`, `GET /expenses/{id}/edit`, `POST /expenses/{id}/update`,
`POST /expenses/{id}/paid`, `POST /expenses/{id}/delete`, `GET /expenses/export`

---

### 11. Approval Workflow

A centralised approval engine that gates all significant financial actions.

#### Actions Requiring Approval

| Action | Triggered By |
|--------|-------------|
| Savings withdrawal | SavingsController::withdraw() |
| Fund transfer | FundTransferController::store() |
| Loan disbursement | LoanController::disburse() |
| Expense | ExpenseController::store() |
| Share transaction | ShareController::store() |
| Transaction reversal | TransactionController::reverse() |

#### Workflow Rules

| Rule | Detail |
|------|--------|
| **Mandatory notes** | Every approve AND reject action requires notes (minimum 10 characters) |
| **No self-approval** | Initiator cannot approve their own request |
| **SLA deadline** | Each approval has a 24-hour due-by timestamp |
| **Overdue flagging** | Requests past SLA are flagged red in the queue |
| **Audit trail** | Every decision logged in `audit_logs` with notes, timestamp, IP, device |
| **Cascading execution** | On approval, the underlying action executes atomically |
| **Rejection handling** | Referenced record status reverted gracefully |
| **Queue filtering** | Filter by type (withdrawal/transfer/expense etc.) and status |
| **Role filtering** | Finance Manager sees own-assigned + unassigned items |

**Routes:** `GET /approvals`, `GET /approvals/{id}`, `POST /approvals/{id}/approve`,
`POST /approvals/{id}/reject`, `GET /approvals/pending-count`

---

### 12. Transactions

Every financial movement is recorded as an immutable transaction record.

| Transaction Type | Triggered By |
|----------------|-------------|
| `deposit` | Savings deposit |
| `withdrawal` | Savings withdrawal (after approval) |
| `loan_disbursement` | Loan disbursement (after approval) |
| `loan_repayment` | Repayment recording |
| `membership_fee` | Social fund fee payment |
| `penalty` | Late payment penalty |
| `transfer` | Internal fund transfer (debit + credit pair) |
| `reversal` | Approved reversal |
| `account_balance_check_fee` | Checked account balance |
| `transaction_statement_fee` | Requested transaction statement |

| Feature | Detail |
|---------|--------|
| External reference | Mobile money or bank transaction ID |
| Balance before/after | Snapshot at time of transaction |
| Payment method | Cash / Mobile Money / Bank Transfer / Internal |
| Approval notes | Stored on approved/reversed transactions |
| Filter & search | By type, date range, member, reference |
| CSV export | Full transaction list |
| Reversal | Requires approval workflow |

---

### 13. Reports & Analytics

Enterprise-grade reporting with CSV export on every report.

| Report | Contents |
|--------|---------|
| **Dashboard** | KPI cards, 6-month bar chart (savings vs disbursements), loan portfolio donut, pending approvals, overdue loans, recent activity |
| **Savings Report** | All savings accounts with deposit/withdrawal totals for period |
| **Loans Report** | Full loan portfolio with filter by status and product |
| **Transactions Report** | All transactions with type breakdown and totals |
| **Members Report** | Member list with savings and loan totals; filtered by join date |
| **Cash Flow Report** | Monthly inflow vs outflow for full fiscal year with Chart.js visualisation |
| **Share Analytics** | Shareholder list, monthly share transaction history, capital raised |
| **Expense Analytics** | Category breakdown, monthly trend, totals by status |
| **Social Fund Report** | Per-period collection rate, arrears list |
| **Audit Report** | System activity filtered by user, module, date |

All reports support:
- Date range filtering
- Status filtering
- CSV export with UTF-8 BOM (Excel-compatible)
- Print-friendly layout

---

### 14. Import / Export

#### Bulk Member Import (CSV)

| Feature | Detail |
|---------|--------|
| Template download | Pre-formatted CSV template with sample row |
| Required columns | `first_name`, `last_name`, `phone`, `membership_date` |
| Optional columns | All other member fields |
| Conflict-free numbering | Atomic `SELECT … FOR UPDATE` sequence reservation (see §14 below) |
| Duplicate phone guard | Existing phone numbers skipped with row-level error message |
| Duplicate NID guard | Existing National IDs skipped |
| Auto savings account | `SAV-XXXXXX` account created for each imported member |
| Progress feedback | Import count, skipped count, error list (max 25 shown) |
| Drag & drop | File drop zone on import page |
| Audit logged | Total imported/skipped recorded in audit log |

#### Data Export

| Export | Format |
|--------|--------|
| Members | CSV |
| Savings | CSV |
| Loans | CSV |
| Transactions | CSV |
| Expenses | CSV |

---

### 15. Notifications

| Feature | Detail |
|---------|--------|
| In-system notifications | Bell icon with unread count badge |
| Mark as read | Single or mark-all-read |
| Notification types | Loan application, approval, rejection, disbursement, broadcast |
| Broadcast | Send to all users or by role (Finance Manager etc.) |
| Auto-notifications | Triggered on loan status changes |
| Unread count | Shown in sidebar and top navbar |

---

### 16. Audit Log

Every action in the system creates an immutable audit entry.

| Field | Description |
|-------|-------------|
| User | Who performed the action |
| Action | e.g. `loan_approved`, `member_created`, `expense_deleted` |
| Module | e.g. `loans`, `members`, `approvals` |
| Record ID | Which record was affected |
| Description | Human-readable description including mandatory notes |
| Old / New values | JSON snapshot (for updates) |
| IP address | Requester IP |
| User agent | Browser/client identifier |
| Session ID | Linked to `login_sessions` |
| Severity | Info / Warning / Critical |
| Timestamp | Created at (immutable) |

Filter by: user, module, date range, search term. Export to CSV.

---

### 17. Trash & Recovery

| Feature | Detail |
|---------|--------|
| Soft delete | Members, loans, transactions moved to trash (not permanently deleted) |
| Trash listing | Filterable by record type |
| One-click restore | Clears `deleted_at`; record returns to active list |
| Permanent delete | Hard delete (irreversible); both trash and source record removed |
| Active loan guard | Member with active loans cannot be deleted |
| Paid expense guard | Paid expenses cannot be deleted |
| Audit trail | Deletion and restoration both logged |

---

### 18. System Settings

All key parameters are configurable by Super Admin or Admin.

| Group | Settings |
|-------|---------|
| **General** | Organisation name, tagline, email, phone, address |
| **Finance** | Currency, currency symbol, min savings, default interest rate, max loan multiplier, processing fee, late penalty, grace period |
| **Approval Workflow** | Require withdrawal approval, require transfer approval, require disbursement approval, auto-approve threshold |
| **Shares** | Par value per share |
| **Social Fund** | Monthly fee amount, due day, grace days, penalty amount |
| **Security** | Session timeout, max login attempts, lockout duration |
| **Notifications** | SMS enabled, email enabled |
| **Backup** | Backup frequency |

Manual backup can be triggered from the Settings page.

---

### 19. User Management & RBAC

#### Supported Roles

| Role | Slug | Description |
|------|------|-------------|
| Super Administrator | `super_admin` | Full unrestricted access to all modules |
| System Administrator | `admin` | Administrative access, cannot delete critical records |
| Finance Manager | `finance_manager` | Full financial operations, approve/reject transactions |
| Loans Officer | `loans_officer` | Create and manage loan applications |
| Savings Officer | `savings_officer` | Record deposits and withdrawals |
| Auditor | `auditor` | Read-only access to all financial records |
| Group Chairperson | `chairperson` | Group-level access |
| Treasurer | `treasurer` | Financial records, limited management |
| Standard Member | `member` | Portal access to own records only |

#### Permissions Matrix (selected)

| Permission | Finance Manager | Loans Officer | Savings Officer | Auditor |
|-----------|:-:|:-:|:-:|:-:|
| members.view | ✓ | ✓ | ✓ | ✓ |
| members.create | ✓ | — | ✓ | — |
| loans.approve | ✓ | — | — | — |
| loans.disburse | ✓ | — | — | — |
| savings.deposit | ✓ | — | ✓ | — |
| savings.withdraw | ✓ | — | ✓ | — |
| approvals.process | ✓ | — | — | — |
| shares.manage | ✓ | — | — | — |
| expenses.approve | ✓ | — | — | — |
| reports.view | ✓ | ✓ | ✓ | ✓ |
| audit.view | ✓ | — | — | ✓ |
| settings.edit | — | — | — | — |

---

## Role-Based Access Control

Permission checks are enforced at the controller level:

```php
// Require a specific permission
$this->auth->requirePermission('loans.approve');

// Check in views
<?php if ($auth->can('members.create')): ?>
    <a href="/members/create" class="btn btn-primary">Add Member</a>
<?php endif; ?>

// Check any of multiple permissions
$this->auth->canAny(['loans.approve', 'loans.disburse']);
```

Super Administrators bypass all permission checks.

---

## Database Schema

### Core Tables

| Table | Purpose |
|-------|---------|
| `users` | System user accounts |
| `roles` | Role definitions (9 roles) |
| `permissions` | Permission slugs (28 permissions) |
| `role_permissions` | Role ↔ Permission mapping |
| `members` | Member profiles and KYC data |
| `groups` | Savings groups |
| `savings_accounts` | Member savings accounts (multiple per member) |
| `transactions` | All financial transactions (unified ledger) |
| `loan_products` | Loan product configurations |
| `loans` | Loan records and lifecycle state |
| `loan_guarantors` | Loan guarantor records |
| `loan_repayment_schedules` | Generated repayment schedules |
| `member_shares` | Shareholder holdings ledger |
| `share_transactions` | Share purchase/sale/transfer history |
| `share_config` | Share par value and privilege configuration |
| `social_fund_fees` | Fee configurations |
| `social_fund_fee_payments` | Per-member, per-period fee records |
| `fund_transfers` | Member-to-member transfer records |
| `expenses` | SACCO operational expenses |
| `expense_categories` | Expense classification |
| `approvals` | Approval workflow queue |
| `notifications` | In-system notification inbox |
| `audit_logs` | Immutable activity audit trail |
| `login_sessions` | Session tracking per user |
| `settings` | Key-value system configuration |
| `member_sequence` | Atomic sequence for conflict-free member numbers |
| `documents` | File attachment references |
| `trash` | Soft-delete archive |

### Database Views

| View | Purpose |
|------|---------|
| `v_member_savings_summary` | Members with total savings aggregated |
| `v_loan_overview` | Loans with member and product info |

---

## Directory Structure

```
akabbo/
├── app/
│   ├── Controllers/
│   │   ├── BaseController.php         ← Shared: view, JSON, CSRF, redirect
│   │   ├── AuthController.php         ← Login, logout, password reset
│   │   ├── DashboardController.php    ← KPIs, charts, summary stats
│   │   ├── MemberController.php       ← Member CRUD, search, export, statement
│   │   ├── LoanController.php         ← Loan lifecycle, repayments, calculator
│   │   ├── SavingsController.php      ← Deposits, withdrawals, accounts
│   │   ├── TransactionController.php  ← Transaction ledger, approvals
│   │   ├── GroupController.php        ← Group CRUD and member listing
│   │   ├── LoanProductController.php  ← Product configuration
│   │   ├── ShareController.php        ← Share issuance, approvals, analytics
│   │   ├── ApprovalController.php     ← Centralised approval workflow
│   │   ├── ExpenseController.php      ← Expense tracking and approval
│   │   ├── SocialFundFeeController.php← Fee config, generation, payments
│   │   ├── FundTransferController.php ← Member-to-member transfers
│   │   ├── ImportController.php       ← CSV bulk member import
│   │   ├── ReportController.php       ← All report generation and export
│   │   ├── NotificationController.php ← Inbox and broadcast
│   │   ├── AuditController.php        ← Audit log viewer
│   │   ├── UserController.php         ← User management and profile
│   │   ├── SettingsController.php     ← System settings and backup
│   │   ├── TrashController.php        ← Trash restore and permanent delete
│   │   └── SearchController.php       ← Global search endpoint
│   ├── Models/
│   │   ├── BaseModel.php              ← ORM-like: CRUD, paginate, soft-delete
│   │   ├── Member.php                 ← Member queries and stats
│   │   ├── Loan.php                   ← Loan lifecycle and schedule generation
│   │   ├── Share.php                  ← Share ledger and privilege calculation
│   │   ├── Approval.php               ← Workflow engine
│   │   ├── Expense.php                ← Expense queries and analytics
│   │   ├── SocialFundFee.php          ← Fee management and payment tracking
│   │   ├── Transaction.php            ← Transaction stats and statement
│   │   ├── SavingsAccount.php         ← Account management and interest
│   │   ├── Group.php                  ← Group with member stats
│   │   └── LoanProduct.php            ← Products with loan count stats
│   ├── Services/
│   │   └── AuthService.php            ← Login, session, permissions, audit
│   ├── Helpers/
│   │   ├── Security.php               ← CSRF, XSS, hashing, upload validation
│   │   ├── Format.php                 ← Currency, dates, status pills, initials
│   │   └── MemberSequence.php         ← Atomic conflict-free member numbering
│   └── Libraries/                     ← (reserved for future third-party wrappers)
│
├── config/
│   ├── config.php                     ← Constants, autoloader, error reporting
│   └── database.php                   ← PDO singleton with helpful error page
│
├── database/
│   ├── schema.sql                     ← Full initial schema with seed data
│   └── migration_patch3.sql           ← Patch 3: shares, fees, approvals, expenses
│
├── public/
│   ├── index.php                      ← Front controller (entry point)
│   ├── .htaccess                      ← Routing rules (Apache)
│   └── assets/
│       ├── css/app.css                ← Print styles and animation overrides
│       ├── js/app.js                  ← Sidebar, toasts, global search, confirm
│       └── js/ajax.js                 ← Fetch wrapper, form submit, table loader
│
├── resources/
│   ├── layouts/
│   │   └── main.php                   ← Sidebar + topbar + flash + footer shell
│   └── views/
│       ├── auth/                      ← login, forgot-password, reset-password
│       ├── dashboard/                 ← index (KPIs + charts)
│       ├── members/                   ← index, create, show, edit, statement
│       ├── groups/                    ← index, create, show, edit
│       ├── loans/                     ← index, create, show, edit
│       ├── loan-products/             ← index, create, edit
│       ├── savings/                   ← index, show, deposit, withdraw
│       ├── transactions/              ← index, show
│       ├── shares/                    ← index, create, member, report
│       ├── approvals/                 ← index, show
│       ├── expenses/                  ← index, create, show, edit
│       ├── social-fund/               ← index, create, payments
│       ├── transfers/                 ← index, create, show
│       ├── reports/                   ← index, savings, loans, transactions, members, cash-flow
│       ├── import/                    ← index (upload + export hub)
│       ├── notifications/             ← index
│       ├── audit/                     ← index, show
│       ├── settings/                  ← index
│       ├── users/                     ← index, create, show, profile, change-password
│       ├── trash/                     ← index
│       └── errors/                    ← 404
│
├── routes/
│   └── web.php                        ← URL → Controller routing table (75+ routes)
│
├── storage/
│   ├── logs/                          ← PHP error logs
│   ├── backups/                       ← Manual/automated database backups
│   └── uploads/
│       ├── avatars/                   ← Member profile photos
│       ├── documents/                 ← KYC document scans
│       └/receipts/                   ← Expense receipts and invoices
│
├── .htaccess                          ← Root redirect to public/ (WAMP-safe)
├── .gitignore
└── README.md
```

---

## Installation Guide

### Requirements

| Requirement | Minimum Version |
|------------|----------------|
| PHP | 8.1 |
| MySQL | 8.0 or MariaDB 10.5 |
| Apache | 2.4 (with mod_rewrite) |
| Browser | Chrome 90+, Edge 90+, Firefox 88+ |

### Step 1 — Download & Extract

Place the `akabbo/` folder inside your WAMP `www/` directory:

```
C:\wamp64\www\akabbo\
```

### Step 2 — Database Setup

1. Open **phpMyAdmin** → Create database `akabbo_fund` (utf8mb4_unicode_ci)
2. Import the schema:

```sql
-- Initial schema (tables, seed data, views)
SOURCE akabbo/database/schema.sql;

-- Patch 3 (shares, fees, approvals, expenses, sequences)
SOURCE akabbo/database/migration_patch3.sql;
```

### Step 3 — Configuration

Edit `config/database.php` if your MySQL credentials differ from the defaults:

```php
private const DB_HOST = 'localhost';
private const DB_NAME = 'akabbo_fund';
private const DB_USER = 'root';
private const DB_PASS = '';          // WAMP default: empty
```

`APP_URL` is **auto-detected** from `SCRIPT_NAME` — no manual configuration needed
for subdirectory installs like `http://localhost/akabbo/`.

### Step 4 — Enable mod_rewrite (WAMP)

In WAMP tray → Apache → Apache modules → tick **rewrite_module**

Also ensure `AllowOverride All` is set for your `www/` directory in
`httpd.conf` or `httpd-vhosts.conf`.

### Step 5 — Storage Directories

Ensure these directories are **writable** by Apache:

```
akabbo/storage/logs/
akabbo/storage/uploads/avatars/
akabbo/storage/uploads/documents/
akabbo/storage/uploads/receipts/
akabbo/storage/backups/
```

### Step 6 — First Login

Navigate to `http://localhost/akabbo/` and log in:

| Field | Value |
|-------|-------|
| Email | `admin@akabbofund.org` |
| Password | `Admin@123` |

> **Important:** Change the password immediately via Profile → Change Password.

### Nginx Configuration (Production)

```nginx
server {
    listen 80;
    server_name yourdomain.org;
    root /var/www/akabbo/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~* \.(env|git|sql)$ { deny all; }
}
```

---

## Default Credentials

| Role | Email | Password |
|------|-------|---------|
| Super Administrator | `admin@akabbofund.org` | `Admin@123` |

> Change this immediately after first login. The system will prompt you.

---

## Security Features

| Feature | Implementation |
|---------|---------------|
| Password hashing | `bcrypt` with cost factor 12 |
| SQL injection prevention | PDO prepared statements — no string concatenation in queries |
| CSRF protection | `hash_equals()` token comparison; token in every form + AJAX header |
| XSS prevention | `htmlspecialchars()` on all output; `strip_tags()` on sanitized input |
| Session security | `httponly`, `samesite=Strict` cookie flags |
| Account lockout | 5 failed attempts → 15-minute lockout (configurable) |
| Security headers | X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, CSP (PHP-based, no mod_headers required) |
| File upload validation | MIME type verified via `finfo`, not browser-reported type |
| Self-approval prevention | Initiator cannot approve their own financial requests |
| Mandatory approval notes | Enforced at both controller and UI level |
| Audit logging | Every state-changing action logged with user, IP, timestamp |
| HTTPS enforcement | Auto-redirect in production mode |

---

## API / AJAX Endpoints

All AJAX endpoints return JSON: `{ success: bool, message: string, data: any }`.

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/auth/login` | POST | Public | Authenticate user |
| `/members/search` | GET | ✓ | Live member search |
| `/members/export` | GET | ✓ | CSV member export |
| `/loans/calculate` | GET | ✓ | Loan EMI calculation |
| `/loans/{id}/approve` | POST | ✓ | Approve loan |
| `/loans/{id}/reject` | POST | ✓ | Reject loan with notes |
| `/loans/{id}/disburse` | POST | ✓ | Disburse approved loan |
| `/loans/repayment` | POST | ✓ | Record repayment |
| `/savings/deposit` | POST | ✓ | Record deposit |
| `/savings/withdraw` | POST | ✓ | Process withdrawal |
| `/approvals/{id}/approve` | POST | ✓ | Approve with mandatory notes |
| `/approvals/{id}/reject` | POST | ✓ | Reject with mandatory notes |
| `/approvals/pending-count` | GET | ✓ | Badge count for navbar |
| `/shares/store` | POST | ✓ | Submit share purchase |
| `/shares/{id}/approve` | POST | ✓ | Approve share transaction |
| `/shares/member-info/{id}` | GET | ✓ | Member's current share holding |
| `/social-fund/generate-period` | POST | ✓ | Batch generate fee records |
| `/social-fund/record-payment` | POST | ✓ | Record fee payment |
| `/social-fund/waive` | POST | ✓ | Waive fee with reason |
| `/transfers/store` | POST | ✓ | Submit fund transfer |
| `/transfers/{id}/reverse` | POST | ✓ | Reverse with notes |
| `/expenses/store` | POST | ✓ | Record expense |
| `/expenses/{id}/paid` | POST | ✓ | Mark expense paid |
| `/groups/list` | GET | ✓ | Groups for dropdowns |
| `/loan-products/{id}/toggle` | POST | ✓ | Toggle product active/inactive |
| `/import/members` | POST | ✓ | Bulk CSV member import |
| `/import/template/{type}` | GET | ✓ | Download CSV template |
| `/notifications/mark-read` | POST | ✓ | Mark notification read |
| `/notifications/mark-all-read` | POST | ✓ | Mark all read |
| `/search` | GET | ✓ | Global search |

---

## Approval Workflow Rules

```
1. A financial action (withdrawal / transfer / disbursement / expense / share) is initiated.
2. The record is saved with status = 'pending'.
3. An entry is created in the `approvals` table with a 24-hour SLA.
4. The approval queue (GET /approvals) shows all pending items.
5. An authorised reviewer (Finance Manager / Super Admin) opens the item.
6. They must enter NOTES (minimum 10 characters) before approving or rejecting.
7a. APPROVE → underlying action executes atomically (balance deducted, etc.)
7b. REJECT  → referenced record reverted to safe state; initiator notified.
8. Decision + notes recorded in `audit_logs`.
9. Self-approval is blocked at the controller level (HTTP 403).
```

---

## Share Privilege System

```
Member applies for loan
        │
        ▼
Is member a shareholder?
   │              │
  YES             NO
   │              │
   ▼              ▼
Get base rate    Use standard rate & multiplier
Subtract loan_rate_discount (default -2%)
Add loan_multiplier_bonus   (default +1×)
        │
        ▼
Holds ≥ 100 shares?
   │              │
  YES             NO
   │              │
   ▼              ▼
Additional -1%   Done
        │
        ▼
Display adjusted rate & ceiling to loan officer
```

---

## Member Number Generation

The system uses an **atomic, locking sequence** to prevent duplicate member
numbers during concurrent registrations or bulk CSV imports.

```
┌─────────────────────────────────────────────────────┐
│ MemberSequence::next($count)                        │
│                                                     │
│  BEGIN TRANSACTION                                  │
│  SELECT last_seq FROM member_sequence WHERE id=1    │
│    FOR UPDATE          ← blocks concurrent callers  │
│  first = last_seq + 1                               │
│  UPDATE member_sequence SET last_seq = first+count-1│
│  COMMIT                                             │
│                                                     │
│  Returns: first reserved sequence number            │
│                                                     │
│  Batch import of 500 members? One DB round-trip.    │
│  All 500 numbers reserved atomically.               │
└─────────────────────────────────────────────────────┘

Format: AKB-{padded 5-digit sequence}
Example: AKB-00001, AKB-00002, …, AKB-99999
```

If a generated number somehow already exists (edge case), the system
regenerates on the fly and logs the anomaly.

---

## Known Limitations & Roadmap

### Current Limitations

| Item | Status |
|------|--------|
| SMS notifications | Setting exists; SMS gateway integration not included |
| Email notifications | Setting exists; SMTP integration not included |
| Mobile application | Web-only; responsive mobile browser supported |
| Multi-currency | Single currency per installation |
| Multi-branch | Architecture supports it; UI not yet built |
| PDF export | Print-to-PDF via browser; dedicated PDF library not integrated |
| Biometric auth | Not implemented |
| Cloud backup | Manual only; S3/cloud integration not included |

### Roadmap (future versions)

- [ ] SMS integration (Africa's Talking / Twilio)
- [ ] Email notifications (PHPMailer / SMTP)
- [ ] Mobile app (PWA or React Native)
- [ ] Multi-branch support
- [ ] PDF generation (TCPDF / DomPDF)
- [ ] Automated scheduled tasks (cron: overdue marking, fee generation)
- [ ] Payment gateway integration (MTN Mobile Money API, Airtel Money API)
- [ ] Cloud backup to S3-compatible storage
- [ ] Two-factor authentication (TOTP)
- [ ] Member self-service portal

---

## Support & Contact

**Organisation:** Akabbo Social Fund
**System Version:** 1.0.0
**PHP Minimum:** 8.1
**Built with:** PHP · MySQL · Tailwind CSS · Chart.js · Font Awesome

---

*© 2025 Akabbo Social Fund. Enterprise Savings & Loan Management System.*
*Developed for SACCOs, cooperatives and savings groups across Uganda.*
