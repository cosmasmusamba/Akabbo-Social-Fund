<?php
// We should notify both approver and applicant use (dispatch instead of sendToUser) with relevant messages for all actions of this contoller and any other event of success and failure. Avoid using default `prompt` instead use modals. Ensure to use ApprovalController for DRY and consistence. Enforce logAudit and (RBAC + IDOR). Embrace canAccessMemberRecord for all methods that fetch records. Determine the user's data scope (Global, Group, Personal, or None). Prevent overdraft(we rely on social fund, service fees generated, interests gained) if to use savings account notify approver, with amounts pulled and well documented, remaining balance well updated.
namespace App\Controllers;

use App\Models\Expense;
use App\Models\Approval;
use App\Helpers\Format;
use App\Helpers\StorageHelper;
use App\Services\NotificationService;

class ExpenseController extends BaseController
{
    private Expense $model;
    private Approval $approval;
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new Expense();
        $this->approval = new Approval();
        $this->notif = new NotificationService();
    }

    // ── SCOPING ENGINE ─────────────────────────────────────────────
    
    /**
     * Expenses are organizational, but personal scope restricts users to their own submissions.
     */
    private function getExpenseScopeCondition(array $scope, string $alias = 'e'): string 
    {
        if ($scope['type'] === 'personal') {
            return "AND {$alias}.created_by = " . (int)($this->auth->user()['id'] ?? 0);
        } elseif ($scope['type'] === 'none') {
            return "AND 1=0";
        }
        // Global and Group scopes can view all organizational expenses
        return ""; 
    }

    // ── CRUD METHODS ───────────────────────────────────────────────

    public function index(): void
    {
        $perms = ['global' => 'expenses.view', 'group' => 'expenses.view', 'personal' => 'expenses.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view expenses.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->getExpenseScopeCondition($scope);

        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $filters = [
            'status'      => $this->getQuery('status', ''),
            'category_id' => $this->getQuery('category', ''),
            'from'        => $this->getQuery('from', date('Y-m-01')),
            'to'          => $this->getQuery('to', date('Y-m-d')),
            'search'      => $this->getQuery('search', ''),
        ];

        $result = $this->model->getList($page, $limit, $filters, $scopeCondition);
        $stats = $this->model->getSummaryStats($filters['from'], $filters['to'], $scopeCondition);
        $byCategory = $this->model->getByCategory($filters['from'], $filters['to'], $scopeCondition);
        $categories = $this->db->fetchAll("SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name");

        $this->logAudit('expenses_viewed', 'expenses', null, null, "Viewed expenses list");

        $this->view('expenses/index', array_merge(
            $this->prepareViewData('Expenses', 'expenses'),
            ['result' => $result, 'stats' => $stats, 'byCategory' => $byCategory, 'categories' => $categories, 'filters' => $filters]
        ));
    }

    public function create(): void
    {
        $perms = ['global' => 'expenses.create', 'group' => 'expenses.create', 'personal' => 'expenses.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to create expenses.');
        
        $categories = $this->db->fetchAll("SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name");
        $this->view('expenses/create', array_merge(
            $this->prepareViewData('Record Expense', 'expenses', ['Expenses' => APP_URL.'/expenses', 'New' => null]),
            ['categories' => $categories]
        ));
    }

    public function store(): void
    {
        $perms = ['global' => 'expenses.create', 'group' => 'expenses.create', 'personal' => 'expenses.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to create expenses.');
        $this->verifyCsrf();
        
        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['category_id', 'title', 'amount', 'expense_date', 'payment_method']);
        if ($missing) $this->jsonError('Required fields missing.', array_fill_keys($missing, 'Required'), 422);
        if ((float)($data['amount'] ?? 0) <= 0) $this->jsonError('Amount must be greater than zero.', null, 422);

        $newFileName = null;
        $this->db->beginTransaction();
        try {
            $ref = $this->model->generateRef();
            $date = \DateTime::createFromFormat('Y-m-d', $data['expense_date']);
            
            $expenseData = [
                'expense_ref'    => $ref,
                'category_id'    => (int)$data['category_id'],
                'title'          => $data['title'],
                'description'    => $data['description'] ?? null,
                'amount'         => (float)$data['amount'],
                'payment_method' => $data['payment_method'],
                'payee_name'     => $data['payee_name'] ?? null,
                'payee_contact'  => $data['payee_contact'] ?? null,
                'receipt_no'     => $data['receipt_no'] ?? null,
                'expense_date'   => $data['expense_date'],
                'period_month'   => $date ? (int)$date->format('n') : (int)date('n'),
                'period_year'    => $date ? (int)$date->format('Y') : (int)date('Y'),
                'status'         => 'pending',
                'created_by'     => $_SESSION['user_id'],
            ];

            $id = $this->model->create($expenseData);

            if (!empty($_FILES['receipt']['name'])) {
                $newFileName = StorageHelper::upload($_FILES['receipt'], 'receipts', ALLOWED_DOC_TYPES, 2 * 1024 * 1024, 'receipt_');
                $this->model->update($id, ['attachment_path' => $newFileName]);
            }

            // DRY: Route through centralized approval engine
            $this->approval->request('expense', $id, $ref, (float)$data['amount'], $_SESSION['user_id'],
                "Expense: {$data['title']} — " . Format::currency((float)$data['amount']));

            // ENFORCE: Notify Applicant (Multi-channel dispatch)
            $this->notif->dispatch(
                (int)$_SESSION['user_id'],
                'expense_submitted',
                'Expense Submitted for Approval',
                "Your expense request ({$ref}) for " . Format::currency((float)$data['amount']) . " has been submitted and is pending approval."
            );

            // ENFORCE: Notify Approvers (Multi-channel dispatch)
            $this->notifyApproversViaDispatch('expense', $ref, (float)$data['amount'], $data['description'] ?? 'No additional notes');

            $this->logAudit('expense_created', 'expenses', $id, 'Expense', "Expense {$ref} recorded", null, json_encode($expenseData));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL.'/expenses/'.$id], "Expense {$ref} recorded and sent for approval.");
        } catch (\Exception $e) {
            $this->db->rollback();
            // FIX: Clean up uploaded file if DB transaction failed
            if ($newFileName) StorageHelper::delete('receipts', $newFileName);
            $this->notifyAdminsViaDispatch('expense_creation_failed', 'Expense Creation Failed', "Failed to record expense: " . $e->getMessage());
            $this->jsonError('Failed to record expense: ' . $e->getMessage(), null, 500);
        }
    }

    public function show(int $id): void
    {
        $perms = ['global' => 'expenses.view', 'group' => 'expenses.view', 'personal' => 'expenses.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to view expenses.');
        $scope = $this->resolveDataScope($perms);

        $expense = $this->model->getDetail($id);
        if (!$expense) { 
            $this->flash('error','Expense not found.'); 
            $this->redirect('/expenses'); 
        }

        // ENFORCE: IDOR Guard for Personal Scope
        if ($scope['type'] === 'personal' && (int)$expense['created_by'] !== (int)$this->auth->user()['id']) {
            $this->flash('error', 'You do not have permission to view this expense.');
            $this->redirect('/expenses');
        }

        $approval = $this->approval->getForRecord('expense', $id);
        
        $this->logAudit('expense_viewed', 'expenses', $id, 'Expense', "Viewed expense {$expense['expense_ref']}");

        $this->view('expenses/show', array_merge(
            $this->prepareViewData($expense['expense_ref'], 'expenses', ['Expenses' => APP_URL.'/expenses', $expense['expense_ref'] => null]),
            ['expense' => $expense, 'approval' => $approval]
        ));
    }

    public function edit(int $id): void
    {
        $perms = ['global' => 'expenses.create', 'group' => 'expenses.create', 'personal' => 'expenses.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to edit expenses.');
        $scope = $this->resolveDataScope($perms);

        $expense = $this->model->find($id);
        if (!$expense || !in_array($expense['status'], ['draft', 'pending'])) {
            $this->flash('error','Only draft or pending expenses can be edited.');
            $this->redirect('/expenses');
        }

        if ($scope['type'] === 'personal' && (int)$expense['created_by'] !== (int)$this->auth->user()['id']) {
            $this->flash('error', 'You do not have permission to edit this expense.');
            $this->redirect('/expenses');
        }

        $categories = $this->db->fetchAll("SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name");
        $this->view('expenses/edit', array_merge(
            $this->prepareViewData('Edit ' . ($expense['expense_ref'] ?? ''), 'expenses', ['Expenses' => APP_URL.'/expenses', 'Edit' => null]),
            ['expense' => $expense, 'categories' => $categories]
        ));
    }

    public function update(int $id): void
    {
        $perms = ['global' => 'expenses.create', 'group' => 'expenses.create', 'personal' => 'expenses.create_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to edit expenses.');
        $scope = $this->resolveDataScope($perms);
        $this->verifyCsrf();
        
        $oldData = $this->model->getOldValues($id);
        if (!$oldData || !in_array($oldData['status'], ['draft', 'pending'])) {
            $this->jsonError('Expense cannot be edited in its current state.', null, 400);
        }

        if ($scope['type'] === 'personal' && (int)$oldData['created_by'] !== (int)$this->auth->user()['id']) {
            $this->jsonError('You do not have permission to edit this expense.', null, 403);
        }

        $data = $this->getPost();
        $updateData = [
            'category_id'    => (int)$data['category_id'],
            'title'          => $data['title'],
            'description'    => $data['description'] ?? null,
            'amount'         => (float)$data['amount'],
            'payment_method' => $data['payment_method'],
            'payee_name'     => $data['payee_name'] ?? null,
            'receipt_no'     => $data['receipt_no'] ?? null,
            'expense_date'   => $data['expense_date'],
        ];

        $newFileName = null;
        $this->db->beginTransaction();
        try {
            if (!empty($_FILES['receipt']['name'])) {
                $newFileName = StorageHelper::upload($_FILES['receipt'], 'receipts', ALLOWED_DOC_TYPES, 2 * 1024 * 1024, 'receipt_');
                $updateData['attachment_path'] = $newFileName;
            }

            $this->model->update($id, $updateData);

            // FIX: Delete old file ONLY after DB success to prevent data loss
            if ($newFileName && !empty($oldData['attachment_path'])) {
                StorageHelper::delete('receipts', $oldData['attachment_path']);
            }

            // ENFORCE: Notify Applicant (Multi-channel dispatch)
            $this->notif->dispatch(
                (int)$oldData['created_by'],
                'expense_updated',
                'Expense Request Updated',
                "Your expense request ({$oldData['expense_ref']}) has been updated. It remains pending approval."
            );

            // ENFORCE: Notify Approvers of the update
            $this->notifyApproversViaDispatch('expense_updated', $oldData['expense_ref'], (float)$updateData['amount'], "Expense was updated by the applicant.");

            $this->logAudit('expense_updated', 'expenses', $id, 'Expense', "Expense {$oldData['expense_ref']} updated", json_encode($oldData), json_encode($updateData));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL.'/expenses/'.$id], 'Expense updated successfully.');
        } catch (\Exception $e) {
            $this->db->rollback();
            if ($newFileName) StorageHelper::delete('receipts', $newFileName);
            $this->notifyAdminsViaDispatch('expense_update_failed', 'Expense Update Failed', "Failed to update expense {$oldData['expense_ref']}: " . $e->getMessage());
            $this->jsonError('Update failed: ' . $e->getMessage(), null, 500);
        }
    }

    public function markPaid(int $id): void
    {
        $this->auth->requirePermission('expenses.approve');
        $this->verifyCsrf();

        $expense = $this->model->find($id);
        if (!$expense || $expense['status'] !== 'approved') {
            $this->jsonError('Only approved expenses can be marked as paid.', null, 400);
        }

        $data = $this->getPost();
        $notes = trim($data['payment_notes'] ?? '');
        if (strlen($notes) < 10) {
            $this->jsonError('Payment notes are required and must be at least 10 characters long.', null, 422);
        }

        $paymentMethod = $data['payment_method'] ?? $expense['payment_method'];
        $sourceAccountId = (int)($data['source_account_id'] ?? 0);

        $oldData = $this->model->getOldValues($id);
        $newData = [
            'status' => 'paid', 
            'paid_by' => $_SESSION['user_id'], 
            'paid_at' => date('Y-m-d H:i:s'), 
            'approval_notes' => ($oldData['approval_notes'] ?? '') . ' | Payment: ' . $notes,
            'payment_method' => $paymentMethod
        ];

        $this->db->beginTransaction();
        try {
            // COMPLIANCE: Prevent Overdraft if paying from Savings Account
            $savingsDeductionDetails = null;
            if (in_array($paymentMethod, ['savings_account', 'internal_transfer']) && $sourceAccountId > 0) {
                $account = $this->db->fetchOne("SELECT * FROM savings_accounts WHERE id = ? AND status = 'active'", [$sourceAccountId]);
                if (!$account) {
                    throw new \Exception("Source savings account not found or inactive.");
                }
                if ((float)$account['balance'] < (float)$expense['amount']) {
                    throw new \Exception("Insufficient funds in the selected savings account. Current balance: " . Format::currency((float)$account['balance']));
                }
                
                $newBalance = (float)$account['balance'] - (float)$expense['amount'];
                $this->db->execute("UPDATE savings_accounts SET balance = ? WHERE id = ?", [$newBalance, $sourceAccountId]);
                
                // Document the pull immutably in the transactions ledger
                $txnRef = 'EXP-TXN-' . strtoupper(uniqid());
                $this->db->execute("
                    INSERT INTO transactions (txn_ref, txn_type, amount, savings_account_id, payment_method, description, transaction_date, balance_before, balance_after, status, created_by)
                    VALUES (?, 'expense_payment', ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
                ", [
                    $txnRef, $expense['amount'], $sourceAccountId, $paymentMethod, 
                    "Expense payment for {$expense['expense_ref']}: {$expense['title']}", 
                    $expense['expense_date'], $account['balance'], $newBalance, $_SESSION['user_id']
                ]);
                
                $savingsDeductionDetails = [
                    'account_no' => $account['account_no'],
                    'amount_pulled' => (float)$expense['amount'],
                    'remaining_balance' => $newBalance
                ];
            }

            $this->model->update($id, $newData);

            // ENFORCE: Notify Applicant (Multi-channel dispatch)
            $this->notif->dispatch(
                (int)$expense['created_by'],
                'expense_paid',
                'Expense Payment Processed',
                "Your expense request ({$expense['expense_ref']}) for " . Format::currency((float)$expense['amount']) . " has been marked as paid. Payment notes: {$notes}"
            );

            // ENFORCE: Notify Approvers/Admins with detailed deduction info if applicable
            if ($savingsDeductionDetails) {
                $msg = "Expense {$expense['expense_ref']} for " . Format::currency((float)$expense['amount']) . " was paid from savings account {$savingsDeductionDetails['account_no']}. " .
                       "Amount pulled: " . Format::currency($savingsDeductionDetails['amount_pulled']) . ". " .
                       "Remaining balance: " . Format::currency($savingsDeductionDetails['remaining_balance']) . ". Payment notes: {$notes}";
                $this->notifyApproversViaDispatch('expense_paid_from_savings', $expense['expense_ref'], (float)$expense['amount'], $msg);
            } else {
                $this->notifyAdminsViaDispatch('expense_paid', 'Expense Paid', 
                    "Expense {$expense['expense_ref']} for " . Format::currency((float)$expense['amount']) . " has been successfully paid via {$paymentMethod}. Payment notes: {$notes}");
            }

            $this->logAudit('expense_paid', 'expenses', $id, 'Expense', "Expense {$expense['expense_ref']} marked as paid: {$notes}", json_encode($oldData), json_encode($newData));
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL.'/expenses/'.$id], 'Expense marked as paid.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdminsViaDispatch('expense_payment_failed', 'Expense Payment Failed', "Failed to mark expense {$expense['expense_ref']} as paid: " . $e->getMessage());
            $this->jsonError('Failed to mark as paid: ' . $e->getMessage(), null, 500);
        }
    }

    public function delete(int $id): void
    {
        $perms = ['global' => 'expenses.delete', 'group' => 'expenses.delete', 'personal' => 'expenses.delete_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to delete expenses.');
        $scope = $this->resolveDataScope($perms);
        $this->verifyCsrf();

        $expense = $this->model->find($id);
        if (!$expense) $this->jsonError('Expense not found.', null, 404);
        if ($expense['status'] === 'paid') $this->jsonError('Paid expenses cannot be deleted.', null, 409);

        if ($scope['type'] === 'personal' && (int)$expense['created_by'] !== (int)$this->auth->user()['id']) {
            $this->jsonError('You do not have permission to delete this expense.', null, 403);
        }

        $this->db->beginTransaction();
        try {
            $oldData = $this->model->getOldValues($id);
            $this->model->delete($id, $_SESSION['user_id']);
            
            $approvalRecord = $this->approval->getForRecord('expense', $id);
            if ($approvalRecord) $this->approval->cancel($approvalRecord['id']);

            // ENFORCE: Notify Applicant (Multi-channel dispatch)
            $this->notif->dispatch(
                (int)$expense['created_by'],
                'expense_deleted',
                'Expense Request Cancelled',
                "Your expense request ({$expense['expense_ref']}) has been cancelled and moved to trash."
            );

            $this->logAudit('expense_deleted', 'expenses', $id, 'Expense', "Expense {$expense['expense_ref']} moved to trash", json_encode($oldData), null);
            
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL.'/expenses'], 'Expense moved to trash.');
        } catch (\Exception $e) {
            $this->db->rollback();
            $this->notifyAdminsViaDispatch('expense_delete_failed', 'Expense Deletion Failed', "Failed to delete expense {$expense['expense_ref']}: " . $e->getMessage());
            $this->jsonError('Failed to delete expense: ' . $e->getMessage(), null, 500);
        }
    }

    public function export(): void
    {
        $perms = ['global' => 'expenses.view', 'group' => 'expenses.view', 'personal' => 'expenses.view_own'];
        $this->requireScopeAccess($perms, 'You do not have permission to export expenses.');
        $scope = $this->resolveDataScope($perms);
        $scopeCondition = $this->getExpenseScopeCondition($scope);

        $filters = [
            'from'   => $this->getQuery('from', date('Y-01-01')),
            'to'     => $this->getQuery('to', date('Y-m-d')),
            'status' => $this->getQuery('status', ''),
        ];

        $result = $this->model->getList(1, 5000, $filters, $scopeCondition);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="akabbo-expenses-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Ref','Category','Title','Amount','Payee','Receipt No','Payment Method','Date','Status','Approved By']);

        foreach ($result['data'] as $e) {
            fputcsv($out, [
                $e['expense_ref'], $e['category_name'], $e['title'],
                number_format((float)$e['amount'], 2), $e['payee_name'] ?? '',
                $e['receipt_no'] ?? '', $e['payment_method'], $e['expense_date'],
                $e['status'], $e['approved_by_name'] ?? '',
            ]);
        }
        fclose($out);
        
        $this->logAudit('expenses_exported', 'expenses', null, null, "Expenses exported to CSV", null, json_encode($filters));
        exit;
    }

    // ── HELPERS ────────────────────────────────────────────────────

    private function notifyApproversViaDispatch(string $referenceType, string $referenceRef, float $amount, string $notes): void
    {
        $users = $this->db->fetchAll(
            "SELECT DISTINCT u.id FROM users u 
             JOIN role_permissions rp ON rp.role_id = u.role_id 
             JOIN permissions p ON p.id = rp.permission_id 
             WHERE p.slug = 'expenses.approve' AND u.status = 'active'"
        );

        if ($referenceType === 'expense_paid_from_savings') {
            $title = 'Expense Paid from Savings';
            $message = $notes; // Notes already contains the full detailed deduction message
        } elseif ($referenceType === 'expense_updated') {
            $title = 'Expense Request Updated';
            $message = "The expense request ({$referenceRef}) for " . Format::currency($amount) . " has been updated by the applicant. Notes: {$notes}";
        } else {
            $title = "New Expense Approval Request";
            $message = "A new expense request ({$referenceRef}) for " . Format::currency($amount) . " requires your approval. Notes: {$notes}";
        }

        foreach ($users as $user) {
            // USE dispatch instead of sendToUser for multi-channel (System + Email + SMS)
            $this->notif->dispatch((int)$user['id'], 'expense_approval_request', $title, $message);
        }
    }

    private function notifyAdminsViaDispatch(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll(
            "SELECT DISTINCT u.id FROM users u 
             JOIN role_permissions rp ON rp.role_id = u.role_id 
             JOIN permissions p ON p.id = rp.permission_id 
             WHERE p.slug IN ('expenses.approve', 'expenses.view') AND u.status = 'active'"
        );
        foreach ($admins as $admin) {
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}