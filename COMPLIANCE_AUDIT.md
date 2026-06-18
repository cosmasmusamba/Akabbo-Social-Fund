# Akabbo Social Fund – Core Financial Workflows & Business Rules

## 1. Financial Architecture Principles

The financial management system is built upon the following foundational principles:

### 1.1 Immutable Financial Ledger

Every financial event is permanently recorded in the `transactions` ledger with:

* Transaction type
* Amount
* Balance before
* Balance after
* Timestamp
* Initiating user

Financial records are never deleted. Corrections are performed through controlled reversal transactions, ensuring a complete and auditable history of all account activity.

### 1.2 Separation of Financial Ledgers

Savings, loans, shares, service fees and Social fund are maintained as independent financial ledgers.

Key rules include:

* Loan disbursements do not increase member savings balances.
* Savings balances are not used to represent loan proceeds.
* Share purchases and investments are tracked independently from savings.
* Each financial product maintains its own balances, schedules, and reporting structure.

This prevents inaccurate financial reporting and preserves the integrity of member account balances.

### 1.3 Centralized Approval Framework

High-risk financial operations are routed through a centralized approval workflow.

Examples include:

* Withdrawals
* Fund transfers
* Share purchases
* Expense payments
* Transaction reversals

Requests remain pending until reviewed and approved by an authorized officer.

The system enforces segregation of duties by preventing users from approving their own requests.

---

# 2. Savings Management

## 2.1 Deposits

### Workflow

Deposit Entry → Validation → Immediate Posting → Notification

### Business Rules

* Savings accounts are automatically created for eligible members when missing.
* Deposits are posted immediately and do not require approval.
* The member savings balance is updated instantly.
* An immutable ledger transaction is created.
* Members receive immediate confirmation notifications.

---

## 2.2 Withdrawals

### Workflow

Request → Validation → Approval → Execution

### Business Rules

Before submission, the system validates:

* Minimum required savings balance.
* Loan collateral protection requirements.
* Account status and eligibility.

### Available Withdrawal Calculation

Available balance is determined by:

`Available Savings = Total Savings – Protected Loan Collateral`

Where protected collateral is calculated using configurable loan multiplier settings.

### Approval Processing

Upon approval:

* Savings balance is debited.
* Transaction status changes from Pending to Completed.
* Audit logs are recorded.
* The member receives approval and completion notifications.

No funds are deducted while the request remains pending.

---

# 3. Fund Transfers

## Workflow

Transfer Request → Validation → Approval → Atomic Settlement

## Business Rules

The system enforces:

* No self-transfers.
* Sufficient available balance.
* Loan collateral protection rules.
* Mandatory transfer description.

### Settlement Processing

Upon approval:

1. Sender account is debited.
2. Receiver account is credited.
3. Matching transfer-out and transfer-in ledger records are generated.
4. Transfer status is updated to Completed.

Processing occurs within a single database transaction to ensure atomicity and prevent partial execution.

---

# 4. Loan Management

## 4.1 Loan Application

### Workflow

Application → Eligibility Validation → Approval Review

### Business Rules

The system supports:

* Flat-rate interest
* Reducing-balance interest
* Compound interest

Additional configurable rules include:

* Shareholder interest discounts
* Shareholder borrowing multipliers
* Loan product limits
* Affordability assessments

### Active Loan Limits

A configurable maximum number of active loans may be enforced per member.

If the limit is exceeded:

* The application is automatically rejected.
* The applicant is notified immediately.
* An audit record is maintained.

---

## 4.2 Loan Disbursement

### Workflow

Approval → Loan Activation → Repayment Schedule Generation

### Business Rules

Upon approval:

* Loan status changes to Active.
* Repayment schedules are generated.
* Outstanding loan balance is initialized.

Loan disbursements must never update the member savings account balance.

Loan and savings ledgers remain completely independent.

---

## 4.3 Loan Repayments

### Workflow

Payment Entry → Allocation → Balance Update

### Allocation Priority

Repayments are applied in the following order:

1. Principal
2. Interest
3. Penalties

The system prioritizes the oldest outstanding installments first.

Each repayment:

* Updates loan balances.
* Updates repayment schedules.
* Creates an immutable loan repayment transaction.

---

# 5. Loan Guarantor Management

## Supported Guarantor Types

* Member guarantors
* Shareholder guarantors
* Member-shareholder guarantors

## Financial Enforcement Framework

Guarantor obligations are governed through configurable rules.

### Exposure Controls

The system may enforce:

* Maximum guarantee exposure limits.
* Savings-based guarantee thresholds.
* Share-capital-based guarantee thresholds.
* Net financial position requirements.

### Default Recovery

For overdue or defaulted loans, the system may:

* Recover funds from pledged savings.
* Recover from dividends or shareholder earnings.
* Recover from pledged share capital.
* Apply proportional recovery across guarantors.

### Restriction Controls

Guarantors with unresolved obligations may be subject to:

* Withdrawal restrictions.
* Share redemption restrictions.
* Loan application restrictions.
* Additional guarantee restrictions.

All enforcement thresholds, grace periods, and recovery methods are configurable.

---

# 6. Share Management

## Workflow

Purchase Request → Approval → Share Allocation

## Business Rules

Upon approval:

* Share holdings are updated atomically.
* Investment totals are recalculated.
* Shareholder status is activated or maintained.
* Share transactions are permanently recorded.

The process prevents duplicate ownership records and maintains accurate share balances.

---

# 7. Transaction Reversals

## Workflow

Reversal Request → Approval → Execution

## Business Rules

Reversals are performed through compensating transactions rather than record deletion.

Examples:

* Reversing a deposit creates an equivalent debit.
* Reversing a withdrawal creates an equivalent credit.

Each reversal:

* References the original transaction.
* Maintains a complete audit trail.
* Requires authorization and justification.

---

# 8. Notifications & Member Communication

Members must receive notifications for all significant financial events, including:

* Successful transactions
* Failed transactions
* Pending approvals
* Approved requests
* Rejected requests
* Loan status updates
* Share transaction updates

Notifications should include:

* Transaction reference
* Transaction type
* Amount
* Date and time
* Current status
* Reason for rejection or failure where applicable

All notification activity must be auditable.

---

# 9. Service Charges

The system supports configurable service fees for member self-service enquiries, including:

* Balance enquiries
* Statement requests
* Other member-initiated information services

Configuration options include:

* Fixed or percentage-based charges
* Daily or monthly free limits
* Member category exemptions
* Charge frequency controls

Members must be informed of applicable charges before the request is processed.

---

# 10. Security, Compliance & Governance

## Access Control

Role-based access control ensures users can only access authorized records.

Examples:

* Members may only access their own data.
* Group Managers may access assigned groups.
* Authorized staff may access organization-wide records.

Object-level security controls prevent unauthorized access through manipulated URLs or identifiers.

## Segregation of Duties

The system enforces:

* Requester ≠ Approver
* Maker ≠ Checker
* Mandatory approval notes
* Complete audit logging

## Audit & Compliance

Every approval, rejection, reversal, and financial adjustment records:

* User identity
* Timestamp
* Action performed
* Supporting justification

This ensures full traceability and regulatory compliance across all financial operations.

## Interest Processing

The system supports periodic savings interest accrual.

Interest posting:

* Calculates eligible earnings.
* Credits qualifying savings accounts.
* Creates immutable interest transactions.
* Maintains a complete audit trail.

# Additional Financial Controls and Approval Enhancements

## 11. Approval-Time Balance Validation

### Purpose

Submission-time validation alone is insufficient because multiple pending requests may exist against the same account. Between submission and approval, the member's financial position may change due to:

* Other approved withdrawals.
* Other approved transfers.
* Loan repayments.
* Loan disbursements.
* Expense deductions.
* Reversals.
* Interest postings.
* Any other balance-affecting transaction.

To prevent over-commitment of funds, all financial validations must be re-executed immediately before final approval and execution.

### Approval-Time Validation Rules

Before executing any fund-out transaction, the system shall:

1. Lock the affected account record.
2. Recalculate the current available balance.
3. Include all completed and pending obligations.
4. Revalidate all business rules.
5. Reject execution if any validation fails.

### Validation Checks

The following must be validated at approval time:

* Current savings balance.
* Minimum required savings balance.
* Loan collateral protection requirements.
* Account status.
* Member eligibility status.
* Existing pending commitments.
* Available withdrawable balance.
* Guarantor restrictions.
* Regulatory or product-specific limits.

### Covered Transaction Types

The validation framework shall apply to all transactions that reduce available funds, including:

* Withdrawals.
* Member transfers.
* Loan disbursements.
* Expense payments.
* Share redemptions.
* Guarantor recoveries.
* Reversals that result in a debit.
* Any future fund-out transaction type.

If validation fails at approval time:

* The transaction shall not execute.
* The request status shall be marked as Rejected or Validation Failed.
* The requester and approver shall be notified with the specific reason.

---

## 12. Configurable Approval Requirements

### Transaction-Level Approval Controls

Each transaction type shall support a configurable setting:

`is_approval_required`

This setting determines whether a transaction follows the approval workflow or is executed immediately.

### Supported Transaction Types

Examples include:

* Deposits
* Withdrawals
* Transfers
* Loan applications
* Loan disbursements
* Share purchases
* Share redemptions
* Expense payments
* Reversals
* Guarantor recoveries

### Processing Rules

When `is_approval_required = true`:

* Request is created in Pending status.
* Approval workflow is initiated.
* No financial posting occurs until approval.

When `is_approval_required = false`:

* Validation is performed.
* Transaction executes immediately.
* Ledger records are created instantly.
* Notifications are generated automatically.

### Governance Controls

System administrators may configure:

* Approval requirements per transaction type.
* Multi-level approval thresholds.
* Amount-based approval limits.
* Department-specific approval rules.
* Emergency override permissions.

All configuration changes must be fully audited.

---

## 13. Pending Commitment Protection

### Reserved Funds Calculation

The system shall calculate available funds using both completed and pending obligations.

Example:

Current Savings Balance: USh 1,000,000

Pending Withdrawal: USh 300,000
Pending Transfer: USh 200,000

Reserved Amount: USh 500,000

Available Balance: USh 500,000

Subsequent requests shall be evaluated against the available balance rather than the ledger balance alone.

This prevents approval of multiple pending transactions that collectively exceed available funds.

---

## 14. Guarantor Lifecycle Management

### Guarantor Restrictions

When a member guarantees a loan, the system may:

* Freeze pledged savings.
* Restrict withdrawals.
* Restrict share redemptions.
* Restrict additional guarantees.
* Restrict new loan applications.

Restrictions shall be configurable and proportional to the guaranteed exposure.

### Automatic Release of Restrictions

Upon loan settlement, refinancing, write-off, or guarantee release:

1. Recalculate guarantor exposure.
2. Verify whether other active guarantees remain.
3. Remove obsolete restrictions.
4. Release frozen savings or shares where applicable.
5. Restore normal account status.

### Post-Settlement Validation

The system must validate that a guarantor is not left in a frozen or restricted state when no active obligations justify the restriction.

Any remaining restrictions must be linked to:

* Another active guarantee.
* An active loan obligation.
* A compliance hold.
* A manual administrative action with documented justification.

### Guarantor Audit Trail

Every guarantor action shall be auditable, including:

* Guarantee creation.
* Guarantee modification.
* Exposure adjustments.
* Restriction application.
* Restriction release.
* Recovery deductions.
* Loan settlement releases.

This ensures complete transparency throughout the guarantor lifecycle.

## 15. Service Fees for Account Enquiries

### Purpose

The system shall support configurable service fees for member-initiated account enquiry services to recover operational costs and encourage responsible use of self-service channels.

### Supported Chargeable Services

The following services shall support configurable charging:

#### Account Balance Enquiries

Configuration Parameters:

* `balance_inquiry_fee_amount`
* `balance_inquiry_fee_required` (true/false)
* `balance_inquiry_free_limit`
* `balance_inquiry_charge_frequency`

#### Account Statement Requests

Configuration Parameters:

* `statement_request_fee_amount`
* `statement_request_fee_required` (true/false)
* `statement_request_free_limit`
* `statement_request_charge_frequency`

### Applicability

Service fees shall apply only when:

* The requester is a member.
* The request is made against the member's own account.
* Charging is enabled for the service.

Service fees shall not apply to:

* Administrative users performing official duties.
* Auditors and compliance personnel.
* System-generated statements and reports.
* Exempt member categories as defined in system settings.

### Fee Validation

Before processing a chargeable request, the system shall:

1. Determine whether charging is enabled.
2. Check applicable exemptions.
3. Validate free usage limits.
4. Calculate the applicable fee.
5. Verify sufficient available balance.
6. Notify the member of the charge.

### Fee Collection

Where charging is enabled:

* The fee shall be deducted from the member's savings account.
* A separate immutable transaction record shall be created.
* The transaction type shall be recorded as:

  * `balance_inquiry_fee`, or
  * `statement_request_fee`.
* Balance before and balance after values shall be captured.

### Available Balance Validation

Fee deductions shall be treated as fund-out transactions and must comply with all account protection rules, including:

* Minimum savings balance requirements.
* Reserved balance restrictions.
* Loan collateral protection requirements.
* Guarantor restrictions.
* Account status validations.

The enquiry request shall be rejected if the fee deduction would violate any financial control except 

### Member Notification

Before processing the request, the member shall be informed of:

* The service requested.
* The applicable fee amount.
* The account to be charged.
* The resulting charge upon successful processing.

After processing:

* A success notification shall be issued if the request is completed.
* A failure notification shall be issued if the request is rejected or the fee cannot be collected.

### Audit Requirements

All service enquiries and associated fee transactions shall be fully auditable, including:

* Requester identity.
* Service requested.
* Fee charged.
* Exemption applied (if any).
* Date and time.
* Processing outcome.

No enquiry fee transaction shall be deleted; corrections must be performed through approved reversal procedures.

In scenerios where a user checks balance or requests statement with insufficient chargable balance, account should have a pending debit to be settled upon deposit.

