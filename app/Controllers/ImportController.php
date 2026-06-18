<?php
// We should notify admin use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Enforce logAudit and (RBAC + IDOR), Enforce audit COMPLIANCE_AUDIT.md (4. Loan Management). Embrace canAccessMemberRecord for all methods that fetch records. Determine the user's data scope (Global, Group, Personal, or None)
namespace App\Controllers;

use App\Helpers\Security;
use App\Helpers\Format;
use App\Helpers\MemberSequence;
use App\Services\NotificationService;
use Database;

class ImportController extends BaseController
{
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->notif = new NotificationService();
    }

    // ── DASHBOARD & TEMPLATES ──────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'import.upload', 'group' => 'groups.view_members'];
        $this->requireScopeAccess($perms, 'You do not have permission to access the import module.');
        
        $recentImports = $this->db->fetchAll("
            SELECT al.* FROM audit_logs al
            WHERE al.action LIKE 'import_%'
            ORDER BY al.created_at DESC LIMIT 10
        ");

        // ENFORCE: Audit log for viewing
        $this->logAudit('import_viewed', 'import', null, null, "Viewed import dashboard");

        $this->view('import/index', array_merge(
            $this->prepareViewData('Import Data', 'import'),
            ['recentImports' => $recentImports]
        ));
    }

    public function downloadTemplate(string $type): void
    {
        $perms = ['global' => 'import.upload', 'group' => 'groups.view_members'];
        $this->requireScopeAccess($perms, 'You do not have permission to download templates.');
        
        $templates = [
            'members' => [
                'filename' => 'akabbo-member-import-template.csv',
                'headers'  => ['first_name','last_name','middle_name','gender','date_of_birth','phone','phone_alt','email','national_id','district','address','occupation','employer','membership_date','next_of_kin_name','next_of_kin_phone','next_of_kin_relationship'],
                'sample'   => ['Amara','Nakato','','female','1990-05-15','+256700000001','','amara@example.com','CM90000000XYZW','Kampala','Wandegeya, Kampala','Teacher','Kampala City Council','2024-01-15','John Nakato','+256700000002','Husband'],
            ],
            'transactions' => [
                'filename' => 'akabbo-transaction-import-template.csv',
                'headers'  => ['member_no','txn_type','amount','payment_method','transaction_date','description','external_ref','loan_no'],
                'sample'   => ['AKB-00001','deposit','50000','cash','2024-01-20','Monthly contribution','',''],
            ],
            'expenses' => [
                'filename' => 'akabbo-expense-import-template.csv',
                'headers'  => ['category_name','title','amount','expense_date','payee_name','description','payment_method'],
                'sample'   => ['Office Supplies','Printer Paper','50000','2024-01-20','Stationery Shop','Monthly stationery purchase','cash'],
            ],
            'loan_products' => [
                'filename' => 'akabbo-loan-product-import-template.csv',
                'headers'  => ['name','code','min_amount','max_amount','interest_rate','interest_type','min_term_months','max_term_months','processing_fee_pct','requires_guarantor'],
                'sample'   => ['Emergency Loan','EMRG','50000','500000','8.00','flat','1','3','1.00','0'],
            ],
        ];

        if (!isset($templates[$type])) {
            $this->flash('error', 'Unknown template type.');
            $this->redirect('/import');
        }

        // ENFORCE: Audit log for downloading
        $this->logAudit('template_downloaded', 'import', null, null, "Downloaded {$type} template");

        $tpl = $templates[$type];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $tpl['filename'] . '"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, $tpl['headers']);
        fputcsv($out, $tpl['sample']);
        fclose($out);
        exit;
    }

    // ── 1. IMPORT MEMBERS ──────────────────────────────────────────

    public function importMembers(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'members.create', 'group' => 'groups.view_members', 'personal' => 'members.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to import members.');
        $scope = $this->resolveDataScope($perms);
        $this->verifyCsrf();

        if (empty($_FILES['csv_file']['name'])) {
            $this->jsonError('Please select a CSV file to import.', null, 422);
        }

        $file = $_FILES['csv_file'];
        $validation = Security::validateUpload($file, ['text/csv', 'application/csv', 'text/plain'], 5 * 1024 * 1024);
        if (!$validation['valid']) {
            $this->jsonError($validation['error'], null, 422);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) { $this->jsonError('Could not read the uploaded file.', null, 500); }

        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); $this->jsonError('File appears to be empty.', null, 422); }

        $required = ['first_name', 'last_name', 'phone', 'membership_date'];
        $headerMap = array_flip(array_map('trim', $headers));
        foreach ($required as $req) {
            if (!isset($headerMap[$req])) {
                fclose($handle);
                $this->jsonError("Missing required column: {$req}. Download the template for correct format.", null, 422);
            }
        }

        $rows = [];
        while (($rowData = fgetcsv($handle)) !== false) {
            $r = [];
            foreach ($headers as $i => $h) {
                $r[trim($h)] = trim($rowData[$i] ?? '');
            }
            if (!empty($r['first_name']) && !empty($r['last_name']) && !empty($r['phone'])) {
                $rows[] = $r;
            }
        }
        fclose($handle);

        if (empty($rows)) {
            $this->jsonError('No valid data rows found in the file.', null, 422);
        }

        $memberNumbers = MemberSequence::batch(count($rows));
        $inserted = 0; $skipped = 0; $errors = []; $rowNum = 1;

        $this->db->beginTransaction();
        try {
            $mnIndex = 0;
            foreach ($rows as $r) {
                $rowNum++;
                if (empty($r['first_name']) || empty($r['last_name']) || empty($r['phone'])) {
                    $errors[] = "Row {$rowNum}: Missing required fields — skipped.";
                    $skipped++; continue;
                }

                if ($this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE phone=?", [$r['phone']])) {
                    $errors[] = "Row {$rowNum}: Phone {$r['phone']} already exists — skipped.";
                    $skipped++; continue;
                }
                if (!empty($r['national_id']) && $this->db->fetchColumn("SELECT COUNT(*) FROM members WHERE national_id=?", [$r['national_id']])) {
                    $errors[] = "Row {$rowNum}: National ID {$r['national_id']} already exists — skipped.";
                    $skipped++; continue;
                }

                $memberNo = $memberNumbers[$mnIndex] ?? MemberSequence::nextFormatted();
                $mnIndex++;

                // ENFORCE: Apply Group Scope if user is a Group Manager
                $groupId = ($scope['type'] === 'group') ? $scope['group_id'] : null;

                $memberId = (int)$this->db->insert("
                    INSERT INTO members (member_no, first_name, last_name, middle_name, gender, date_of_birth,
                    phone, email, national_id, district, address, occupation, employer, membership_date, 
                    next_of_kin_name, next_of_kin_phone, next_of_kin_relationship, group_id, status, kyc_verified, created_by, created_at, updated_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'active',0,?,NOW(),NOW())
                ", [
                    $memberNo, $r['first_name'], $r['last_name'], $r['middle_name'] ?? null, $r['gender'] ?? null,
                    !empty($r['date_of_birth']) ? $r['date_of_birth'] : null, $r['phone'], $r['email'] ?? null, 
                    $r['national_id'] ?? null, $r['district'] ?? null, $r['address'] ?? null, $r['occupation'] ?? null, 
                    $r['employer'] ?? null, !empty($r['membership_date']) ? $r['membership_date'] : date('Y-m-d'),
                    $r['next_of_kin_name'] ?? null, $r['next_of_kin_phone'] ?? null, $r['next_of_kin_relationship'] ?? null,
                    $groupId, $_SESSION['user_id']
                ]);

                $this->db->execute(
                    "INSERT INTO savings_accounts (account_no, member_id, account_type, balance, status, opened_at, created_by, created_at, updated_at)
                     VALUES (?,?,'regular',0,'active',?,?,NOW(),NOW())",
                    ['SAV-' . str_pad($memberId, 6, '0', STR_PAD_LEFT), $memberId, !empty($r['membership_date']) ? $r['membership_date'] : date('Y-m-d'), $_SESSION['user_id']]
                );

                // ENFORCE: Auto-create or link User Account (Default Member Role ID: 9)
                $nin = $r['national_id'] ?? null;
                $email = $r['email'] ?? null;

                $existingUserId = null;
                if ($nin) $existingUserId = $this->db->fetchColumn("SELECT id FROM users WHERE nin = ? LIMIT 1", [$nin]);
                if (!$existingUserId && $email) $existingUserId = $this->db->fetchColumn("SELECT id FROM users WHERE email = ? LIMIT 1", [strtolower($email)]);

                if ($existingUserId) {
                    $this->db->execute("UPDATE users SET member_id = ? WHERE id = ?", [$memberId, $existingUserId]);
                    $this->db->execute("UPDATE members SET user_id = ? WHERE id = ?", [$existingUserId, $memberId]);
                } else {
                    $tempPass = Security::generateToken(6);
                    $userEmail = $email ? strtolower($email) : strtolower($memberNo) . '@akabbo.local'; 
                    
                    $newUserId = (int)$this->db->insert(
                        "INSERT INTO users (first_name, last_name, email, phone, nin, role_id, password_hash, status, must_change_password, member_id, created_by) 
                         VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                        [
                            $r['first_name'], $r['last_name'], $userEmail, $r['phone'], 
                            $nin ?? '', 9, // 9 is the 'member' role ID
                            Security::hashPassword($tempPass), 'active', 1, $memberId, $_SESSION['user_id']
                        ]
                    );
                    
                    $this->db->execute("UPDATE members SET user_id = ? WHERE id = ?", [$newUserId, $memberId]);
                }

                $inserted++;
            }

            $this->logAudit('import_members', 'members', null, null, 
                "{$inserted} members imported, {$skipped} skipped", 
                null, json_encode(['inserted' => $inserted, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]));

            $this->db->commit();
            $this->notifyAdmins('import_success', 'Bulk Member Import Completed', "{$inserted} members imported successfully. {$skipped} skipped.");
            $this->jsonSuccess([
                'inserted' => $inserted, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)
            ], "{$inserted} member(s) imported successfully." . ($skipped ? " {$skipped} skipped." : ''));
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('import_failure', 'Bulk Member Import Failed', "Import failed at row {$rowNum}: " . $e->getMessage());
            $this->jsonError('Import failed at row ' . $rowNum . ': ' . $e->getMessage(), null, 500);
        }
    }

    // ── 2. IMPORT TRANSACTIONS ─────────────────────────────────────

    public function importTransactions(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'transactions.create', 'group' => 'groups.view_members', 'personal' => 'transactions.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to import transactions.');
        $scope = $this->resolveDataScope($perms);
        $this->verifyCsrf();

        if (empty($_FILES['csv_file']['name'])) {
            $this->jsonError('Please select a CSV file to import.', null, 422);
        }

        $file = $_FILES['csv_file'];
        $validation = Security::validateUpload($file, ['text/csv', 'application/csv', 'text/plain'], 5 * 1024 * 1024);
        if (!$validation['valid']) {
            $this->jsonError($validation['error'], null, 422);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) { $this->jsonError('Could not read the uploaded file.', null, 500); }

        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); $this->jsonError('File appears to be empty.', null, 422); }

        $required = ['member_no', 'txn_type', 'amount', 'transaction_date'];
        $headerMap = array_flip(array_map('trim', $headers));
        foreach ($required as $req) {
            if (!isset($headerMap[$req])) {
                fclose($handle);
                $this->jsonError("Missing required column: {$req}.", null, 422);
            }
        }

        $rows = [];
        while (($rowData = fgetcsv($handle)) !== false) {
            $r = [];
            foreach ($headers as $i => $h) {
                $r[trim($h)] = trim($rowData[$i] ?? '');
            }
            if (!empty($r['member_no']) && !empty($r['txn_type']) && !empty($r['amount'])) {
                $rows[] = $r;
            }
        }
        fclose($handle);

        if (empty($rows)) {
            $this->jsonError('No valid data rows found in the file.', null, 422);
        }

        $inserted = 0; $skipped = 0; $errors = []; $rowNum = 1;
        $allowedTypes = ['deposit', 'withdrawal', 'loan_repayment'];

        $this->db->beginTransaction();
        try {
            foreach ($rows as $r) {
                $rowNum++;
                if (!in_array($r['txn_type'], $allowedTypes)) {
                    $errors[] = "Row {$rowNum}: Invalid txn_type '{$r['txn_type']}'. Allowed: " . implode(', ', $allowedTypes);
                    $skipped++; continue;
                }

                $member = $this->db->fetchOne("SELECT id FROM members WHERE member_no = ? AND deleted_at IS NULL", [$r['member_no']]);
                if (!$member) {
                    $errors[] = "Row {$rowNum}: Member {$r['member_no']} not found.";
                    $skipped++; continue;
                }

                // ENFORCE: IDOR Guard
                if (!$this->canAccessMemberRecord($member['id'])) {
                    $errors[] = "Row {$rowNum}: You do not have permission to import transactions for member {$r['member_no']}.";
                    $skipped++; continue;
                }

                $amount = (float)$r['amount'];
                if ($amount <= 0) {
                    $errors[] = "Row {$rowNum}: Amount must be greater than zero.";
                    $skipped++; continue;
                }

                $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE member_id = ? AND status = 'active' LIMIT 1", [$member['id']]);
                if (!$account && in_array($r['txn_type'], ['deposit', 'withdrawal'])) {
                    $errors[] = "Row {$rowNum}: Member has no active savings account.";
                    $skipped++; continue;
                }

                $txnRef = 'TXN-' . strtoupper(uniqid());
                
                if ($r['txn_type'] === 'deposit') {
                    $balanceBefore = (float)$account['balance'];
                    $balanceAfter = $balanceBefore + $amount;
                    $this->db->execute("UPDATE savings_accounts SET balance = balance + ? WHERE id = ?", [$amount, $account['id']]);
                    $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, external_ref, transaction_date, balance_before, balance_after, status, created_by) VALUES (?, 'deposit', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)",
                        [$txnRef, $amount, $member['id'], $account['id'], $r['payment_method'] ?? 'cash', $r['description'] ?? 'Imported deposit', $r['external_ref'] ?? null, $r['transaction_date'], $balanceBefore, $balanceAfter, $_SESSION['user_id']]
                    );
                } elseif ($r['txn_type'] === 'withdrawal') {
                    if ((float)$account['balance'] < $amount) {
                        $errors[] = "Row {$rowNum}: Insufficient balance for withdrawal.";
                        $skipped++; continue;
                    }
                    $balanceBefore = (float)$account['balance'];
                    $balanceAfter = $balanceBefore - $amount;
                    $this->db->execute("UPDATE savings_accounts SET balance = balance - ? WHERE id = ?", [$amount, $account['id']]);
                    $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, savings_account_id, payment_method, description, external_ref, transaction_date, balance_before, balance_after, status, created_by) VALUES (?, 'withdrawal', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)",
                        [$txnRef, $amount, $member['id'], $account['id'], $r['payment_method'] ?? 'cash', $r['description'] ?? 'Imported withdrawal', $r['external_ref'] ?? null, $r['transaction_date'], $balanceBefore, $balanceAfter, $_SESSION['user_id']]
                    );
                } elseif ($r['txn_type'] === 'loan_repayment') {
                    $loan = $this->db->fetchOne("SELECT * FROM loans WHERE member_id = ? AND loan_no = ? AND status IN ('active','disbursed')", [$member['id'], $r['loan_no'] ?? '']);
                    if (!$loan) {
                        $errors[] = "Row {$rowNum}: Active loan {$r['loan_no']} not found for member.";
                        $skipped++; continue;
                    }
                    
                    $newBalance = max(0, (float)$loan['balance_outstanding'] - $amount);
                    $newStatus = $newBalance <= 0 ? 'completed' : 'active';
                    $this->db->execute("UPDATE loans SET amount_paid = amount_paid + ?, balance_outstanding = ?, status = ? WHERE id = ?", [$amount, $newBalance, $newStatus, $loan['id']]);

                    // COMPLIANCE: STRICT ALLOCATION PRIORITY (Audit Sec 4.3)
                    $remaining = $amount;
                    $installments = $this->db->fetchAll(
                        "SELECT * FROM loan_repayment_schedules WHERE loan_id = ? AND status IN ('upcoming','due','overdue','partially_paid') ORDER BY installment_no ASC", 
                        [$loan['id']]
                    );

                    foreach ($installments as $inst) {
                        if ($remaining <= 0) break;

                        $principalDue = max(0, (float)$inst['principal_due'] - (float)$inst['principal_paid']);
                        $interestDue  = max(0, (float)$inst['interest_due'] - (float)$inst['interest_paid']);
                        $penaltyDue   = max(0, (float)$inst['penalty_due'] - (float)$inst['penalty_paid']);

                        $allocPrincipal = 0; $allocInterest = 0; $allocPenalty = 0;

                        if ($remaining > 0 && $principalDue > 0) {
                            $allocPrincipal = min($remaining, $principalDue);
                            $remaining -= $allocPrincipal;
                        }
                        if ($remaining > 0 && $interestDue > 0) {
                            $allocInterest = min($remaining, $interestDue);
                            $remaining -= $allocInterest;
                        }
                        if ($remaining > 0 && $penaltyDue > 0) {
                            $allocPenalty = min($remaining, $penaltyDue);
                            $remaining -= $allocPenalty;
                        }

                        $totalAllocated = $allocPrincipal + $allocInterest + $allocPenalty;
                        
                        if ($totalAllocated > 0) {
                            $newTotalPaid = (float)$inst['total_paid'] + $totalAllocated;
                            $totalOwed = (float)$inst['total_due'] + (float)$inst['penalty_due'];
                            $isFullyPaid = ($newTotalPaid >= $totalOwed);
                            
                            $this->db->execute("
                                UPDATE loan_repayment_schedules 
                                SET principal_paid = principal_paid + ?, 
                                    interest_paid = interest_paid + ?, 
                                    penalty_paid = penalty_paid + ?, 
                                    total_paid = total_paid + ?,
                                    status = ?, 
                                    paid_date = ? 
                                WHERE id = ?
                            ", [
                                $allocPrincipal, $allocInterest, $allocPenalty, $totalAllocated,
                                $isFullyPaid ? 'paid' : 'partially_paid', $r['transaction_date'], $inst['id']
                            ]);
                        }
                    }

                    $this->db->execute("INSERT INTO transactions (txn_ref, txn_type, amount, member_id, loan_id, payment_method, description, external_ref, transaction_date, status, created_by) VALUES (?, 'loan_repayment', ?, ?, ?, ?, ?, ?, ?, 'completed', ?)",
                        [$txnRef, $amount, $member['id'], $loan['id'], $r['payment_method'] ?? 'cash', $r['description'] ?? 'Imported loan repayment', $r['external_ref'] ?? null, $r['transaction_date'], $_SESSION['user_id']]
                    );

                    // COMPLIANCE: Release guarantors upon full loan settlement (Audit Sec 14)
                    if ($newBalance <= 0) {
                        $this->releaseGuarantors($loan['id']);
                    }
                }
                $inserted++;
            }

            $this->logAudit('import_transactions', 'transactions', null, null, 
                "{$inserted} transactions imported, {$skipped} skipped", 
                null, json_encode(['inserted' => $inserted, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]));

            $this->db->commit();
            $this->notifyAdmins('import_success', 'Bulk Transaction Import Completed', "{$inserted} transactions imported successfully. {$skipped} skipped.");

            $this->jsonSuccess([
                'inserted' => $inserted, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)
            ], "{$inserted} transaction(s) imported successfully." . ($skipped ? " {$skipped} skipped." : ''));
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('import_failure', 'Bulk Transaction Import Failed', "Import failed at row {$rowNum}: " . $e->getMessage());
            $this->jsonError('Import failed at row ' . $rowNum . ': ' . $e->getMessage(), null, 500);
        }
    }

    // ── 3. IMPORT EXPENSES ─────────────────────────────────────────

    public function importExpenses(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper
        $perms = ['global' => 'expenses.create', 'group' => 'groups.view_members', 'personal' => 'expenses.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to import expenses.');
        $this->verifyCsrf();

        if (empty($_FILES['csv_file']['name'])) {
            $this->jsonError('Please select a CSV file to import.', null, 422);
        }

        $file = $_FILES['csv_file'];
        $validation = Security::validateUpload($file, ['text/csv', 'application/csv', 'text/plain'], 5 * 1024 * 1024);
        if (!$validation['valid']) {
            $this->jsonError($validation['error'], null, 422);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) { $this->jsonError('Could not read the uploaded file.', null, 500); }

        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); $this->jsonError('File appears to be empty.', null, 422); }

        $required = ['category_name', 'title', 'amount', 'expense_date'];
        $headerMap = array_flip(array_map('trim', $headers));
        foreach ($required as $req) {
            if (!isset($headerMap[$req])) {
                fclose($handle);
                $this->jsonError("Missing required column: {$req}.", null, 422);
            }
        }

        $rows = [];
        while (($rowData = fgetcsv($handle)) !== false) {
            $r = [];
            foreach ($headers as $i => $h) {
                $r[trim($h)] = trim($rowData[$i] ?? '');
            }
            if (!empty($r['category_name']) && !empty($r['title']) && !empty($r['amount'])) {
                $rows[] = $r;
            }
        }
        fclose($handle);

        if (empty($rows)) {
            $this->jsonError('No valid data rows found in the file.', null, 422);
        }

        $inserted = 0; $skipped = 0; $errors = []; $rowNum = 1;

        $this->db->beginTransaction();
        try {
            foreach ($rows as $r) {
                $rowNum++;
                $category = $this->db->fetchOne("SELECT id FROM expense_categories WHERE name = ?", [$r['category_name']]);
                if (!$category) {
                    $errors[] = "Row {$rowNum}: Category '{$r['category_name']}' not found.";
                    $skipped++; continue;
                }

                $amount = (float)$r['amount'];
                if ($amount <= 0) {
                    $errors[] = "Row {$rowNum}: Amount must be greater than zero.";
                    $skipped++; continue;
                }

                $date = \DateTime::createFromFormat('Y-m-d', $r['expense_date']);
                
                $this->db->insert("
                    INSERT INTO expenses (expense_ref, category_id, title, description, amount, payment_method, payee_name, expense_date, period_month, period_year, status, created_by, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), NOW())
                ", [
                    'EXP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
                    $category['id'],
                    $r['title'],
                    $r['description'] ?? null,
                    $amount,
                    $r['payment_method'] ?? 'cash',
                    $r['payee_name'] ?? null,
                    $r['expense_date'],
                    $date ? (int)$date->format('n') : (int)date('n'),
                    $date ? (int)$date->format('Y') : (int)date('Y'),
                    $_SESSION['user_id']
                ]);
                $inserted++;
            }

            $this->logAudit('import_expenses', 'expenses', null, null, 
                "{$inserted} expenses imported, {$skipped} skipped", 
                null, json_encode(['inserted' => $inserted, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]));

            $this->db->commit();
            $this->notifyAdmins('import_success', 'Bulk Expense Import Completed', "{$inserted} expenses imported successfully. {$skipped} skipped.");

            $this->jsonSuccess([
                'inserted' => $inserted, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)
            ], "{$inserted} expense(s) imported successfully." . ($skipped ? " {$skipped} skipped." : ''));
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdmins('import_failure', 'Bulk Expense Import Failed', "Import failed at row {$rowNum}: " . $e->getMessage());
            $this->jsonError('Import failed at row ' . $rowNum . ': ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPERS ────────────────────────────────────────────────────

    /**
     * COMPLIANCE: Release guarantors upon full loan settlement (Audit Sec 14)
     */
    private function releaseGuarantors(int $loanId): void
    {
        $guarantors = $this->db->fetchAll("SELECT * FROM loan_guarantors WHERE loan_id = ? AND status = 'confirmed'", [$loanId]);
        
        foreach ($guarantors as $guarantor) {
            $gMemberId = (int)$guarantor['guarantor_member_id'];
            
            $activeGuaranteedLoans = (int)$this->db->fetchColumn("
                SELECT COUNT(*) FROM loan_guarantors lg
                JOIN loans l ON l.id = lg.loan_id
                WHERE lg.guarantor_member_id = ? AND l.status IN ('active', 'disbursed', 'pending') AND lg.status = 'confirmed'
            ", [$gMemberId]);

            if ($activeGuaranteedLoans === 0) {
                $this->db->execute("UPDATE loan_guarantors SET status = 'released' WHERE id = ?", [$guarantor['id']]);
                
                $gMember = $this->db->fetchOne("SELECT first_name, last_name, user_id FROM members WHERE id = ?", [$gMemberId]);
                if ($gMember) {
                    $this->logAudit('guarantor_restriction_released', 'loans', $loanId, 'LoanGuarantor', 
                        "Guarantor restrictions released for {$gMember['first_name']} {$gMember['last_name']} as loan {$loanId} settled via import."
                    );
                    
                    // ENFORCE: Notify guarantor via multi-channel dispatch
                    if (!empty($gMember['user_id'])) {
                        $this->notif->dispatch(
                            (int)$gMember['user_id'],
                            'guarantor_released',
                            'Guarantor Obligation Released',
                            "Dear {$gMember['first_name']}, your guarantee obligation for a settled loan has been fully released. Your account restrictions have been lifted."
                        );
                    }
                }
            }
        }
    }

    /**
     * ENFORCE: Notify all admins via multi-channel dispatch
     */
    private function notifyAdmins(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id FROM users u
            JOIN role_permissions rp ON rp.role_id = u.role_id
            JOIN permissions p ON p.id = rp.permission_id
            WHERE p.slug IN ('import.upload', 'members.create', 'transactions.create', 'expenses.create') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            // USE dispatch instead of sendToUser for multi-channel (System + Email + SMS)
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}