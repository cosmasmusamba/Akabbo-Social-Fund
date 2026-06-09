<?php
namespace App\Controllers;

use App\Models\Expense;
use App\Models\Approval;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND — Expense Controller
 * Tracks all SACCO operational expenditure with approval workflow.
 */
class ExpenseController extends BaseController
{
    private Expense  $model;
    private Approval $approval;
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model    = new Expense();
        $this->approval = new Approval();
        $this->db       = Database::getInstance();
    }

    public function index(): void
    {
        $this->auth->requirePermission('expenses.view');
        ['page' => $page, 'limit' => $limit] = $this->getPaginationParams();

        $filters = [
            'status'      => $this->getQuery('status', ''),
            'category_id' => $this->getQuery('category', ''),
            'from'        => $this->getQuery('from', date('Y-m-01')),
            'to'          => $this->getQuery('to',   date('Y-m-d')),
            'search'      => $this->getQuery('search', ''),
        ];

        $result     = $this->model->getList($page, $limit, $filters);
        $stats      = $this->model->getSummaryStats($filters['from'], $filters['to']);
        $byCategory = $this->model->getByCategory($filters['from'], $filters['to']);
        $categories = $this->db->fetchAll("SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name");
        $settings   = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Expenses';
        $activePage  = 'expenses';
        $breadcrumbs = ['Expenses' => null];

        $this->view('expenses/index', compact(
            'result','stats','byCategory','categories','filters',
            'settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    public function create(): void
    {
        $this->auth->requirePermission('expenses.create');
        $categories = $this->db->fetchAll("SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name");
        $settings   = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Record Expense';
        $activePage  = 'expenses';
        $breadcrumbs = ['Expenses' => APP_URL.'/expenses', 'New' => null];

        $this->view('expenses/create', compact(
            'categories','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    public function store(): void
    {
        $this->auth->requirePermission('expenses.create');
        $this->verifyCsrf();

        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['category_id','title','amount','expense_date','payment_method']);
        if ($missing) {
            $this->jsonError('Required fields missing.', array_fill_keys($missing,'Required'), 422);
        }
        if ((float)$data['amount'] <= 0) {
            $this->jsonError('Amount must be greater than zero.', null, 422);
        }

        $this->db->beginTransaction();
        try {
            $ref  = $this->model->generateRef();
            $date = \DateTime::createFromFormat('Y-m-d', $data['expense_date']);

            $id = $this->model->create([
                'expense_ref'    => $ref,
                'category_id'    => (int)$data['category_id'],
                'title'          => $data['title'],
                'description'    => $data['description'] ?? null,
                'amount'         => (float)$data['amount'],
                'payment_method' => $data['payment_method'],
                'payee_name'     => $data['payee_name']    ?? null,
                'payee_contact'  => $data['payee_contact'] ?? null,
                'receipt_no'     => $data['receipt_no']    ?? null,
                'expense_date'   => $data['expense_date'],
                'period_month'   => $date ? (int)$date->format('n') : (int)date('n'),
                'period_year'    => $date ? (int)$date->format('Y') : (int)date('Y'),
                'status'         => 'pending',
                'created_by'     => $_SESSION['user_id'],
            ]);

            // Handle receipt upload
            if (!empty($_FILES['receipt']['name'])) {
                $file = $_FILES['receipt'];
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $name = 'receipt_' . $id . '_' . uniqid() . '.' . $ext;
                    $dir  = UPLOADS_PATH . '/receipts';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    if (move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
                        $this->model->update($id, ['attachment_path' => $name]);
                    }
                }
            }

            // Request approval
            $this->approval->request(
                'expense', $id, $ref,
                (float)$data['amount'],
                $_SESSION['user_id'],
                "Expense: {$data['title']} — " . Format::currency((float)$data['amount'])
                . (isset($data['description']) ? '. ' . $data['description'] : '')
            );

            $this->auth->logAudit($_SESSION['user_id'], 'expense_created', 'expenses', $id, 'Expense',
                "Expense {$ref}: {$data['title']} — " . Format::currency((float)$data['amount']));

            $this->db->commit();
            $this->jsonSuccess(
                ['expense_id' => $id, 'redirect' => APP_URL.'/expenses/'.$id],
                "Expense {$ref} recorded and sent for approval."
            );

        } catch (\Exception $e) {
            $this->db->rollback();
            $this->jsonError('Failed to record expense: ' . $e->getMessage(), null, 500);
        }
    }

    public function show(int $id): void
    {
        $this->auth->requirePermission('expenses.view');
        $expense = $this->model->getDetail($id);
        if (!$expense) { $this->flash('error','Expense not found.'); $this->redirect('/expenses'); }

        $approval = $this->approval->getForRecord('expense', $id);
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = $expense['expense_ref'];
        $activePage  = 'expenses';
        $breadcrumbs = ['Expenses' => APP_URL.'/expenses', $expense['expense_ref'] => null];

        $this->view('expenses/show', compact(
            'expense','approval','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    public function edit(int $id): void
    {
        $this->auth->requirePermission('expenses.create');
        $expense = $this->model->getDetail($id);
        if (!$expense || !in_array($expense['status'], ['draft','pending'])) {
            $this->flash('error','Only draft or pending expenses can be edited.');
            $this->redirect('/expenses');
        }
        $categories = $this->db->fetchAll("SELECT * FROM expense_categories WHERE is_active=1 ORDER BY name");
        $settings   = $this->getSettings();
        $unreadNotifications = $this->getUnread();

        $pageTitle   = 'Edit ' . $expense['expense_ref'];
        $activePage  = 'expenses';
        $breadcrumbs = ['Expenses' => APP_URL.'/expenses', 'Edit' => null];

        $this->view('expenses/edit', compact(
            'expense','categories','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'
        ));
    }

    public function update(int $id): void
    {
        $this->auth->requirePermission('expenses.create');
        $this->verifyCsrf();

        $expense = $this->model->find($id);
        if (!$expense || !in_array($expense['status'], ['draft','pending'])) {
            $this->jsonError('Expense cannot be edited in its current state.', null, 400);
        }

        $data = $this->getPost();
        $this->model->update($id, [
            'category_id'    => (int)$data['category_id'],
            'title'          => $data['title'],
            'description'    => $data['description'] ?? null,
            'amount'         => (float)$data['amount'],
            'payment_method' => $data['payment_method'],
            'payee_name'     => $data['payee_name']    ?? null,
            'receipt_no'     => $data['receipt_no']    ?? null,
            'expense_date'   => $data['expense_date'],
        ]);

        $this->auth->logAudit($_SESSION['user_id'], 'expense_updated', 'expenses', $id, 'Expense',
            "Expense {$expense['expense_ref']} updated");

        $this->jsonSuccess(['redirect' => APP_URL.'/expenses/'.$id], 'Expense updated successfully.');
    }

    public function markPaid(int $id): void
    {
        $this->auth->requirePermission('expenses.approve');
        $this->verifyCsrf();

        $expense = $this->model->find($id);
        if (!$expense || $expense['status'] !== 'approved') {
            $this->jsonError('Only approved expenses can be marked as paid.', null, 400);
        }

        $notes = trim($this->getPost()['payment_notes'] ?? '');
        if (empty($notes)) {
            $this->jsonError('Payment notes are required.', null, 422);
        }

        $this->db->execute("
            UPDATE expenses SET status='paid', paid_by=?, paid_at=NOW(),
            approval_notes=CONCAT(COALESCE(approval_notes,''),' | Payment: ',?), updated_at=NOW()
            WHERE id=?
        ", [$_SESSION['user_id'], $notes, $id]);

        $this->auth->logAudit($_SESSION['user_id'], 'expense_paid', 'expenses', $id, 'Expense',
            "Expense {$expense['expense_ref']} marked as paid: {$notes}");

        $this->jsonSuccess(null, 'Expense marked as paid.');
    }

    public function delete(int $id): void
    {
        $this->auth->requirePermission('expenses.delete');
        $this->verifyCsrf();

        $expense = $this->model->find($id);
        if (!$expense) $this->jsonError('Expense not found.', null, 404);
        if ($expense['status'] === 'paid') $this->jsonError('Paid expenses cannot be deleted.', null, 409);

        $this->model->delete($id);
        $this->approval->cancel($this->approval->getForRecord('expense', $id)['id'] ?? 0);

        $this->auth->logAudit($_SESSION['user_id'], 'expense_deleted', 'expenses', $id, 'Expense',
            "Expense {$expense['expense_ref']} deleted");

        $this->jsonSuccess(['redirect' => APP_URL.'/expenses'], 'Expense deleted.');
    }

    public function export(): void
    {
        $this->auth->requirePermission('expenses.view');
        $filters = [
            'from'   => $this->getQuery('from', date('Y-01-01')),
            'to'     => $this->getQuery('to',   date('Y-m-d')),
            'status' => $this->getQuery('status', ''),
        ];

        $result = $this->model->getList(1, 5000, $filters);

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
        exit;
    }

    private function getSettings(): array
    {
        return array_column($this->db->fetchAll("SELECT `key`,`value` FROM settings"), 'value', 'key');
    }
    private function getUnread(): int
    {
        return (int)$this->db->fetchColumn("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0", [$_SESSION['user_id']]);
    }
}