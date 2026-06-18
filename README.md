***

# Akabbo Social Fund
**Enterprise Savings & Loan Management System (ESLMS) — v1.0.0**
*"Growing Together, Prospering Together"*

A secure, scalable, mobile-first web platform purpose-built for SACCOs, savings groups, investment clubs, and financial cooperatives.

## Table of Contents
- [System Overview](#system-overview)
- [Technology Stack](#technology-stack)
- [Architecture](#architecture)
- [Modules & Features](#modules--features)
- [Advanced Access Control (RBAC)](#advanced-access-control-rbac) Security & Access Control (RBAC + IDOR)
- [Database Schema](#database-schema)
- [Directory Structure](#directory-structure)
- [Installation Guide](#installation-guide)
- [Default Credentials](#default-credentials)
- [Security Features](#security-features)
- [API / AJAX Endpoints](#api--ajax-endpoints)
- [Approval Workflow Rules](#approval-workflow-rules)
- [Share Privilege System](#share-privilege-system)
- [Atomic Number Generation](#atomic-number-generation)
- [Known Limitations & Roadmap](#known-limitations--roadmap)

---

## System Overview
Akabbo Social Fund is a production-grade PHP web application that centralises all financial operations of a SACCO or cooperative into a single integrated platform.

| Objective | How Achieved |
|---|---|
| **Digitise savings operations** | Full deposit/withdrawal ledger with real-time balance and interest application |
| **Transparent loan management** | Complete lifecycle from application to final repayment with atomic numbering |
| **Unified Member Identity** | System Users linked to Member records via NIN/Passport; single profile for all financial data |
| **Accountability & governance** | Mandatory approval notes, full JSON audit trail, strict role separation |
| **Operational efficiency** | Bulk CSV import, automated fee generation, AJAX interactions, tabbed UIs |
| **Scalability** | Modular MVC architecture, optimised PDO queries, pagination everywhere |
| **Security** | CSRF, bcrypt, session lockout, SQL injection prevention, Super Admin protections |

## Technology Stack
| Layer | Technology | Notes |
|---|---|---|
| **Backend** | PHP 8.1+ | OOP, namespaced, PSR-4 autoloaded, no external dependencies |
| **Frontend** | Tailwind CSS (CDN) | Mobile-first, utility-first, custom green/gold theme |
| **Interactions** | Vanilla JavaScript + Fetch API | Zero jQuery dependency, custom AJAX library |
| **Database** | MySQL 8.0+ | `utf8mb4_0900_ai_ci`, InnoDB, prepared statements only |
| **Charts** | Chart.js 4 (CDN) | Dashboard bar/donut, cash-flow visualisations |
| **Icons** | Font Awesome 6 (CDN) | Consistent icon language |
| **Fonts** | Plus Jakarta Sans + Fraunces | Professional typography + brand identity |
| **Auth** | PHP Session-based | Bcrypt passwords, lockout, CSRF, remember me |
| **Server** | Apache 2.4 / Nginx | `.htaccess` routing, WAMP compatible |

## Architecture
The application follows a custom MVC-like pattern with a single front-controller:

```text
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
resources/layouts/main.php ← Sidebar + topbar shell (Permission-Guarded)
```

## Directory Structure

```
akabbo/
├── app/
│   ├── Controllers/
│   │   ├── BaseController.php         ← Shared: view, JSON, CSRF, redirect, IDOR guards
│   │   ├── AccessControlController.php← Access Control & Permission UI
│   │   ├── ApprovalController.php     ← Centralised approval workflow & balance execution
│   │   ├── AuditController.php        ← Audit log viewer
│   │   ├── AuthController.php         ← Login, logout, password reset
│   │   ├── CronController.php         ← NEW: Automated background tasks (CLI/Web)
│   │   ├── DashboardController.php    ← KPIs, charts, scoped summary stats
│   │   ├── ExpenseController.php      ← Expense tracking and approval
│   │   ├── FundTransferController.php ← Member-to-member transfers
│   │   ├── GroupController.php        ← Group CRUD, member assignment
│   │   ├── ImportController.php       ← CSV bulk member import
│   │   ├── LoanController.php         ← Loan lifecycle, repayments, calculator
│   │   ├── LoanProductController.php  ← Product configuration
│   │   ├── MemberController.php       ← Member CRUD, search, export, statement, KYC
│   │   ├── NotificationController.php ← Inbox, broadcast, and read states
│   │   ├── ReportController.php       ← All report generation and export
│   │   ├── SavingsController.php      ← Deposits, withdrawals, accounts, interest
│   │   ├── SearchController.php       ← Global search endpoint
│   │   ├── SettingsController.php     ← System settings, logo upload, backup
│   │   ├── ShareController.php        ← Share issuance, approvals, analytics
│   │   ├── SocialFundFeeController.php← Fee config, generation, payments
│   │   ├── TransactionController.php  ← Transaction ledger, approvals, reversals
│   │   ├── TrashController.php        ← Trash restore and permanent delete
│   │   └── UserController.php         ← User management, profile, NIN linking
│   │
│   ├── Models/
│   │   ├── Approval.php               ← Workflow engine (SLA, queue, stats)
│   │   ├── BaseModel.php              ← ORM-like: CRUD, paginate, soft-delete
│   │   ├── Expense.php                ← Expense queries and analytics
│   │   ├── Group.php                  ← Group with member stats
│   │   ├── Loan.php                   ← Loan lifecycle and schedule generation
│   │   ├── LoanProduct.php            ← Products with loan count stats
│   │   ├── Member.php                 ← Member queries and stats
│   │   ├── Notification.php           ← NEW: Permission-filtered notification retrieval
│   │   ├── Permission.php             ← Permission queries & scoping
│   │   ├── SavingsAccount.php         ← Account management and interest posting
│   │   ├── Share.php                  ← Share ledger and privilege calculation
│   │   ├── SocialFundFee.php          ← Fee management and payment tracking
│   │   └── Transaction.php            ← Transaction stats and statement
│   │
│   ├── Services/
│   │   ├── AuthService.php            ← Login, session, permissions, audit
│   │   ├── NotificationService.php    ← NEW: Centralized notification dispatch
│   │   └── ReminderService.php        ← NEW: KYC, overdue, and inactivity reminders
│   │
│   └── Helpers/
│       ├── Avatar.php                 ← Avatar/image rendering, logo, favicon
│       ├── Format.php                 ← Currency, dates, status pills, initials
│       ├── LoanSequence.php           ← Atomic conflict-free loan numbering
│       ├── MemberSequence.php         ← Atomic conflict-free member numbering
│       ├── SavingsAccountSequence.php ← NEW: DRY savings account number generation
│       ├── Security.php               ← CSRF, XSS, hashing, upload validation
│       └── StorageHelper.php          ← Centralized secure file uploads
│
├── config/
│   ├── config.php                     ← Constants, autoloader, error reporting
│   └── database.php                   ← PDO singleton with helpful error page
│
├── database/
│   └── schema.sql                     ← Full initial schema with seed data
│
── public/
│   ├── index.php                      ← Front controller (entry point)
│   ├── .htaccess                      ← Routing rules (Apache)
│   └── assets/
│       ├── css/app.css                ← Print styles, mobile-first overrides, animations
│       ├── js/app.js                  ← Sidebar, toasts, global search, confirm modals
│       └── js/ajax.js                 ← Fetch wrapper, form submit, table loader
│
├── resources/
│   ├── layouts/
│   │   └── main.php                   ← Sidebar + topbar shell (Permission-guarded)
│   │
│   └── views/
│       ├── access-control/index.php
│       ├── approvals/index.php, show.php
│       ├── audit/index.php, show.php
│       ├── auth/login.php, forgot-password.php, reset-password.php
│       ├── dashboard/index.php
│       ├── errors/404.php
│       ├── expenses/index.php, create.php, edit.php, show.php
│       ├── groups/index.php, create.php, edit.php, show.php
│       ├── import/index.php
│       ├── loan-products/index.php, create.php, edit.php
│       ├── loans/index.php, create.php, edit.php, show.php
│       ├── members/index.php, create.php, edit.php, show.php, statement.php
│       ├── notifications/index.php
│       ├── reports/index.php, savings.php, loans.php, transactions.php, members.php, cash-flow.php
│       ├── savings/index.php, deposit.php, withdraw.php, transfer.php, show.php, statement.php
│       ├── settings/index.php
│       ├── shares/index.php, create.php, member.php, report.php
│       ├── social-fund/index.php, create.php, payments.php
│       ├── transactions/index.php, show.php
│       ├── transfers/index.php, create.php, show.php
│       ├── trash/index.php
│       └── users/index.php, create.php, show.php, profile.php, change-password.php
│
├── routes/
│   └── web.php                        ← URL → Controller routing table (80+ routes)
│
├── storage/
│   └── uploads/
│       ├── avatars/                   ← Member/Group profile photos
│       ├── documents/                 ← General document uploads
│       ├── kyc/                       ← KYC document scans (ID front/back)
│       ├── logos/                     ← Organization logo & favicon
│       └── receipts/                  ← Expense receipts and invoices
│
├── .htaccess                          ← Root redirect to public/ (WAMP-safe)
├── .gitignore
├── COMPLIANCE_AUDIT.md                ← Audit and compliance documentation
└── README.md                          ← Project overview and setup instructions
```

**Key Engineering Decisions:**
* **No framework** — zero external Composer dependencies; fully portable.
* **Singleton Database** — one PDO connection per request; no ORM overhead.
* **Eager controller loading** — all controller files required at startup for Windows compatibility.
* **DIRECTORY_SEPARATOR** — all file paths safe on Windows (WAMP) and Linux.
* **CSRF on every state-change** — hidden token + `X-CSRF-Token` header for AJAX.
* **Soft deletes** — members, loans, expenses moved to Trash, never hard-deleted immediately.
* **Atomic sequences** — `SELECT … FOR UPDATE` prevents duplicate member/loan numbers during concurrent imports.

---

## Modules & Features

### 1. Authentication & Security
* **Secure login:** Email + bcrypt-hashed password.
* **Account lockout:** Configurable max attempts (default 5); auto-unlock after 15 min.
* **Session management:** 30-minute timeout; regenerated ID on login; multi-session tracking.
* **Password reset:** Token flow with 1-hour expiry.
* **Must-change-password:** Flag for new/reset accounts.

### 2. Member Management
* **4-step registration wizard:** Personal → Contact & KYC → Next of Kin → Membership.
* **Atomic numbering:** Format `AKB-00001`; conflict-free generation.
* **User-Member Linking:** System users are automatically linked to member records via National ID (NIN) or Passport Number.
* **Unified Profile:** User profiles automatically pull Savings, Loans, Transactions, and Shares from the linked member record.
* **KYC verification:** Officer marks identity as verified; logged with timestamp.
* **Document upload:** Avatar, ID front/back scans stored securely.

### 3. Groups Management
* **Group code:** Auto-generated `GRP-0001`.
* **Leadership:** Chairperson, Treasurer, Secretary (linked to member records).
* **Live Member Assignment:** Modal with live-search to assign *existing* members to a group.
* **Guard on delete:** Cannot delete a group that has members.

### 4. Savings Management
* **Account types:** Regular, Fixed, Target, Share.
* **Balance lockup validation:** Minimum savings + active loan collateral enforced on withdrawals.
* **Withdrawal approval:** All withdrawals above threshold routed to approval workflow.
* **Interest application:** Configurable per-account interest rate; batch apply function.

### 5. Loan Management
* **Lifecycle:** Draft → Pending → Approved → Disbursed/Active → Completed.
* **Loan number:** Format `LN-2025-00001`; year-prefixed sequential.
* **EMI calculator:** Live JavaScript calculator (flat / reducing balance / compound).
* **Repayment schedule:** Auto-generated on application; regenerated on edit.
* **Guarantors:** Multiple guarantors with consent tracking.

### 6. Loan Products
* **Configurable parameters:** Interest rate, type, min/max amount, term, processing/insurance fees.
* **Shareholder benefits:** Configurable rate discount and multiplier bonus for shareholders.

### 7. Shares Management
* **Share Configuration:** Par value, min/max shares, loan rate discount, dividend rate.
* **Transactions:** Purchase, Sale, Transfer In/Out, Dividend, Refund.
* **Approval Requirement:** All share transactions go through the approval workflow.
* **Savings Deduction:** If payment method is "Savings Deduction", system automatically debits upon approval.

### 8. Social Fund Fees
* **Configurable periodic fees:** Monthly/Quarterly/Annually/One-time.
* **Batch generation:** Create fee records for all applicable members for a specific period.
* **Payment recording:** Cash, mobile money, bank transfer, savings deduction.
* **Waiver:** Authorised personnel can waive individual fee records (reason required).

### 9. Fund Transfers
* **Member-to-member:** Internal savings transfers with full approval workflow.
* **Atomic execution:** Debit + credit in one database transaction.
* **Reversal:** Completed transfers can be reversed (reversal notes mandatory).

### 10. Expenses Tracking
* **Categories:** 10 default categories (Office Supplies, Utilities, Staff Costs, etc.).
* **Receipt upload:** JPG, PNG or PDF stored in `storage/uploads/receipts/`.
* **Mark as paid:** Separate "paid" step after approval (requires payment notes).

### 11. Approval Workflow
* **Centralised engine:** Gates all significant financial actions (withdrawals, transfers, disbursements, expenses, shares).
* **Mandatory notes:** Every approve AND reject action requires notes (minimum 10 characters).
* **No self-approval:** Initiator cannot approve their own request.
* **SLA deadline:** Each approval has a 24-hour due-by timestamp.
* **Cascading execution:** On approval, the underlying action executes atomically.

### 12. Transactions
* **Immutable ledger:** Every financial movement recorded with balance before/after snapshots.
* **Types:** deposit, withdrawal, loan_disbursement, loan_repayment, membership_fee, penalty, interest, transfer, reversal.

### 13. Reports & Analytics
* **Dashboard:** KPI cards, 6-month bar chart, loan portfolio donut, pending approvals, overdue loans.
* **Exports:** All reports support CSV export with UTF-8 BOM (Excel-compatible).

### 14. Import / Export
* **Bulk Member Import:** CSV upload with drag & drop, progress feedback, duplicate phone/NID guards.
* **Auto savings account:** `SAV-XXXXXX` account created for each imported member.

### 15. Notifications
* **In-system notifications:** Bell icon with unread count badge.
* **Auto-notifications:** Triggered on loan status changes.

### 16. Audit Log
* **Immutable trail:** Every action logged with user, IP, user agent, session ID.
* **JSON Snapshots:** `old_values` and `new_values` stored for updates.

### 17. Trash & Recovery
* **Soft delete:** Members, loans, transactions moved to trash.
* **One-click restore:** Clears `deleted_at`; record returns to active list.
* **Active loan guard:** Member with active loans cannot be deleted.

### 18. System Settings
* **Tabbed UI:** General, Finance, Security, Notifications, Backup, System.
* **Branding:** Organization Logo and Favicon upload (applied to UI, login page, and browser tab).
* **Manual backup:** Triggered from the Settings page.

---

## Advanced Access Control (RBAC)

The system features an enterprise-grade Access Control UI (`/access-control`) that evaluates permissions in a strict **Three-Tier Hierarchy**:

1. **User-Specific Deny (Revocation):** Highest priority. Explicitly blocks a permission even if the role has it.
2. **User-Specific Allow (Override):** Grants a permission not assigned to the user's role.
3. **Role-Based Permissions:** Standard RBAC matrix.
4. **Default Deny:** Fallback if no rules match.

### Permission Scoping
Permissions are scoped to control data visibility:
| Scope | Description |
|---|---|
| `global` | User can view/manage all records in the system (e.g., `members.view`). |
| `own` | User can only view/manage their own linked member records (e.g., `members.view_own`). |
| `group` | User can view/manage records within their assigned group (e.g., `groups.view_members`). |

### Super Admin Protections
| Rule | Detail |
|---|---|
| **Immutable Role** | The `super_admin` role permissions cannot be edited via the UI. |
| **Lockout Prevention** | System prevents demoting or deleting the last active Super Admin. |
| **Self-Management** | Regular admins cannot edit their own user accounts (must contact Super Admin). |
| **Peer Management** | Only Super Admins can view, edit, or manage other Super Admin accounts. |

---

## Database Schema

**Core Tables:**
| Table | Purpose |
|---|---|
| `users` | System user accounts (includes `is_super_admin`, `member_id` for linking) |
| `user_permissions` | User-specific permission overrides (Allow/Deny) |
| `roles` | Role definitions (9 roles) |
| `permissions` | Permission slugs (includes `scope`: global, own, group) |
| `role_permissions` | Role ↔ Permission mapping |
| `members` | Member profiles and KYC data |
| `savings_groups` | Savings groups |
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
| `loan_sequence` | Atomic sequence for conflict-free loan numbers |
| `documents` | File attachment references |
| `trash` | Soft-delete archive |

**Database Views:**
| View | Purpose |
|---|---|
| `v_loan_overview` | Loans with member and product info |

---

## Directory Structure

```text
akabbo/
├── COMPLIANCE_AUDIT.md
├── README.md
├── app
│   ├── Controllers
│   │   ├── AccessControlController.php  ← Access Control & Permission UI
│   │   ├── Approvalcontroller.php       ← Centralised approval workflow
│   │   ├── AuditController.php          ← Audit log viewer
│   │   ├── AuthController.php           ← Login, logout, password reset
│   │   ├── BaseController.php           ← Shared: view, JSON, CSRF, redirect
│   │   ├── DashboardController.php      ← KPIs, charts, summary stats
│   │   ├── Expensecontroller.php        ← Expense tracking and approval
│   │   ├── Fundtransfercontroller.php   ← Member-to-member transfers
│   │   ├── GroupController.php          ← Group CRUD, member assignment
│   │   ├── ImportController.php         ← CSV bulk member import
│   │   ├── LoanController.php           ← Loan lifecycle, repayments
│   │   ├── LoanProductController.php    ← Product configuration
│   │   ├── MemberController.php         ← Member CRUD, search, export
│   │   ├── NotificationController.php   ← Inbox and broadcast
│   │   ├── ReportController.php         ← All report generation and export
│   │   ├── SavingsController.php        ← Deposits, withdrawals, accounts
│   │   ├── SearchController.php         ← Global search endpoint
│   │   ├── SettingsController.php       ← System settings, logo, backup
│   │   ├── Sharecontroller.php          ← Share issuance, approvals
│   │   ├── Socialfundfeecontroller.php  ← Fee config, generation, payments
│   │   ├── TransactionController.php    ← Transaction ledger, approvals
│   │   ├── TrashController.php          ← Trash restore and permanent delete
│   │   └── UserController.php           ← User management, profile, NIN linking
│   ├── Helpers
│   │   ├── Avatar.php                   ← Avatar/image rendering, logo, favicon
│   │   ├── Format.php                   ← Currency, dates, status pills
│   │   ├── LoanSequence.php             ← Atomic loan number generation
│   │   ├── Membersequence.php           ← Atomic conflict-free member numbering
│   │   ├── Security.php                 ← CSRF, XSS, hashing, upload validation
│   │   ── StorageHelper.php            ← Centralized secure file uploads
│   ├── Models
│   │   ├── Approval.php                 ← Workflow engine
│   │   ├── BaseModel.php                ← ORM-like: CRUD, paginate, soft-delete
│   │   ├── Expense.php                  ← Expense queries and analytics
│   │   ├── Group.php                    ← Group with member stats
│   │   ├── Loan.php                     ← Loan lifecycle and schedule generation
│   │   ├── LoanProduct.php              ← Products with loan count stats
│   │   ├── Member.php                   ← Member queries and stats
│   │   ├── Permission.php               ← Permission queries & scoping
│   │   ├── SavingsAccount.php           ← Account management and interest
│   │   ├── Share.php                    ← Share ledger and privilege calculation
│   │   ├── Socialfundfee.php            ← Fee management and payment tracking
│   │   └── Transaction.php              ← Transaction stats and statement
│   └── Services
│       └── AuthService.php              ← Login, session, permissions, audit
├── config
│   ├── config.php                       ← Constants, autoloader, error reporting
│   └── database.php                     ← PDO singleton with helpful error page
├── database
│   └── akabbo_fund.sql                  ← Full initial schema with seed data
├── public
│   ├── assets
│   │   ├── css
│   │   │   └── app.css                  ← Print styles and animation overrides
│   │   └── js
│   │       ├── ajax.js                  ← Fetch wrapper, form submit, table loader
│   │       └── app.js                   ← Sidebar, toasts, global search, confirm
│   └── index.php                        ← Front controller (entry point)
├── resources
│   ├── layouts
│   │   └── main.php                     ← Sidebar + topbar shell (Permission-Guarded)
│   └── views
│       ├── access-control
│       │   └── index.php                ← Access Control UI
│       ├── approvals                    ← index, show
│       ├── audit                        ← index, show
│       ├── auth                         ← login, forgot-password, reset-password
│       ├── dashboard                    ← index (KPIs + charts)
│       ├── errors                       ← 404
│       ├── expenses                     ← create, edit, index, show
│       ├── groups                       ← create, edit, index, show, members
│       ├── import                       ← index (upload + export hub)
│       ├── loan-products                ← create, edit, index
│       ├── loans                        ← create, edit, index, show
│       ├── members                      ← create, edit, index, show, statement
│       ├── notifications                ← index
│       ├── reports                      ← cash-flow, index, loans, members, savings, transactions
│       ├── savings                      ← deposit, index, show, statement, transfer, withdraw
│       ├── settings                     ← index
│       ├── shares                       ← create, index, member, report
│       ├── social-fund                  ← create, index, payments
│       ├── transactions                 ← index, show
│       ├── transfers                    ← create, index, show
│       ├── trash                        ← index
│       └── users                        ← change-password, create, index, profile, show
├── routes
│   └── web.php                          ← URL → Controller routing table (80+ routes)
└── storage
    └── uploads
        ├── avatars                      ← Member/Group profile photos
        ├── documents                    ← KYC document scans
        ├── kyc                          ← ID front/back scans
        ├── logos                        ← Organization logo & favicon
        ── receipts                     ← Expense receipts and invoices
```

---

## Installation Guide

### Requirements
| Requirement | Minimum Version |
|---|---|
| PHP | 8.1 |
| MySQL | 8.0 or MariaDB 10.5 |
| Apache | 2.4 (with mod_rewrite) |
| Browser | Chrome 90+, Edge 90+, Firefox 88+ |

### Step 1 — Download & Extract
Place the `akabbo/` folder inside your WAMP `www/` directory:
`C:\wamp64\www\akabbo\`

### Step 2 — Database Setup
1. Open phpMyAdmin → Create database `akabbo_fund` (utf8mb4_unicode_ci).
2. Import the schema:
   ```sql
   SOURCE akabbo/database/akabbo_fund.sql;
   ```

### Step 3 — Configuration
Edit `config/database.php` if your MySQL credentials differ from the defaults:
```php
private const DB_HOST = 'localhost';
private const DB_NAME = 'akabbo_fund';
private const DB_USER = 'root';
private const DB_PASS = '';          // WAMP default: empty
```
*`APP_URL` is auto-detected from `SCRIPT_NAME` — no manual configuration needed for subdirectory installs like `http://localhost/akabbo/`.*

### Step 4 — Enable mod_rewrite (WAMP)
In WAMP tray → Apache → Apache modules → tick `rewrite_module`.
Also ensure `AllowOverride All` is set for your `www/` directory in `httpd.conf` or `httpd-vhosts.conf`.

### Step 5 — Storage Directories
Ensure these directories are writable by Apache:
```text
akabbo/storage/logs/
akabbo/storage/uploads/avatars/
akabbo/storage/uploads/kyc/
akabbo/storage/uploads/logos/
akabbo/storage/uploads/receipts/
akabbo/storage/backups/
```

### Step 6 — First Login
Navigate to `http://localhost/akabbo/` and log in:
| Field | Value |
|---|---|
| Email | `admin@akabbofund.org` |
| Password | `Admin@123` |

**Important:** Change the password immediately via Profile → Change Password.

---

## Default Credentials
| Role | Email | Password |
|---|---|---|
| Super Administrator | admin@akabbofund.org | Admin@123 |

---

## Security Features
| Feature | Implementation |
|---|---|
| **Password hashing** | bcrypt with cost factor 12 |
| **SQL injection prevention** | PDO prepared statements — no string concatenation in queries |
| **CSRF protection** | `hash_equals()` token comparison; token in every form + AJAX header |
| **XSS prevention** | `htmlspecialchars()` on all output; `strip_tags()` on sanitized input |
| **Session security** | `httponly`, `samesite=Strict` cookie flags |
| **Account lockout** | 5 failed attempts → 15-minute lockout (configurable) |
| **Security headers** | X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, CSP |
| **File upload validation** | MIME type verified via `finfo`, not browser-reported type |
| **Self-approval prevention** | Initiator cannot approve their own financial requests |
| **Mandatory approval notes** | Enforced at both controller and UI level (min 10 chars) |
| **Audit logging** | Every state-changing action logged with user, IP, timestamp |
| **HTTPS enforcement** | Auto-redirect in production mode |

---

## API / AJAX Endpoints
All AJAX endpoints return JSON: `{ success: bool, message: string, data: any }`.

| Endpoint | Method | Auth | Description |
|---|---|---|---|
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
| `/social-fund/generate-period` | POST | ✓ | Batch generate fee records |
| `/social-fund/record-payment` | POST | ✓ | Record fee payment |
| `/social-fund/waive` | POST | ✓ | Waive fee with reason |
| `/transfers/store` | POST | ✓ | Submit fund transfer |
| `/transfers/{id}/reverse` | POST | ✓ | Reverse with notes |
| `/expenses/store` | POST | ✓ | Record expense |
| `/expenses/{id}/paid` | POST | ✓ | Mark expense paid |
| `/groups/{id}/add-member` | POST | ✓ | Assign existing member to group |
| `/groups/{id}/remove-member/{memberId}` | POST | ✓ | Remove member from group |
| `/import/members` | POST | ✓ | Bulk CSV member import |
| `/notifications/mark-read` | POST | ✓ | Mark notification read |
| `/notifications/mark-all-read` | POST | ✓ | Mark all read |
| `/search` | GET | ✓ | Global search |
| `/access-control/update` | POST | ✓ | Update user permission overrides |
| `/access-control/save-permission` | POST | ✓ | Create/Update permission |
| `/access-control/save-role` | POST | ✓ | Create/Update role |

---

## Approval Workflow Rules
1. A financial action (withdrawal / transfer / disbursement / expense / share) is initiated.
2. The record is saved with `status = 'pending'`.
3. An entry is created in the `approvals` table with a 24-hour SLA.
4. The approval queue (`GET /approvals`) shows all pending items.
5. An authorised reviewer (Finance Manager / Super Admin) opens the item.
6. They **must** enter NOTES (minimum 10 characters) before approving or rejecting.
7. **APPROVE** → underlying action executes atomically (balance deducted, etc.).
8. **REJECT** → referenced record reverted to safe state; initiator notified.
9. Decision + notes recorded in `audit_logs`.
10. **Self-approval is blocked** at the controller level (HTTP 403).

---

## Share Privilege System
When a shareholder applies for a loan, the system automatically applies:
1. Reduced interest rate (base rate minus `loan_rate_discount`).
2. Higher loan ceiling (base multiplier plus `loan_multiplier_bonus`).
3. Further 1% discount if member holds ≥ 100 shares.

---

## Atomic Number Generation
The system uses an atomic, locking sequence to prevent duplicate member and loan numbers during concurrent registrations or bulk CSV imports.

**Member Number Format:** `AKB-{padded 5-digit sequence}` (e.g., AKB-00001)
**Loan Number Format:** `LN-{year}-{padded 5-digit sequence}` (e.g., LN-2026-00001)

```text
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
─────────────────────────────────────────────────────┘
```

---

## Known Limitations & Roadmap

### Current Limitations
| Item | Status |
|---|---|
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

**Support & Contact**
Organisation: Akabbo Social Fund
System Version: 1.0.0
PHP Minimum: 8.1
Built with: PHP · MySQL · Tailwind CSS · Chart.js · Font Awesome

© 2026 Akabbo Social Fund. Enterprise Savings & Loan Management System.
Developed for SACCOs, cooperatives and savings groups across Uganda.