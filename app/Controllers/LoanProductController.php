<?php
namespace App\Controllers;

use App\Models\LoanProduct;
use App\Helpers\Format;
use Database;

/**
 * AKABBO SOCIAL FUND — Loan Product Controller
 * Manage loan product types, interest rates, and terms.
 */
class LoanProductController extends BaseController
{
    private LoanProduct $model;
    private Database    $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new LoanProduct();
        $this->db    = Database::getInstance();
    }

    public function index(): void
    {
        $this->auth->requirePermission('settings.view');
        $products    = $this->model->getWithLoanCounts();
        $settings    = $this->getSettings();
        $unreadNotifications = $this->getUnread();
        $pageTitle   = 'Loan Products';
        $activePage  = 'loan-products';
        $breadcrumbs = ['Loan Products' => null];
        $this->view('loan-products/index', compact('products','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function create(): void
    {
        $this->auth->requirePermission('settings.edit');
        $settings    = $this->getSettings();
        $unreadNotifications = $this->getUnread();
        $pageTitle   = 'New Loan Product';
        $activePage  = 'loan-products';
        $breadcrumbs = ['Loan Products' => APP_URL.'/loan-products', 'New' => null];
        $this->view('loan-products/create', compact('settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function store(): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();
        $data    = $this->getPost();
        $missing = $this->validateRequired($data, ['name','min_amount','max_amount','interest_rate','interest_type','min_term_months','max_term_months']);
        if ($missing) { $this->jsonError('Required fields missing.', array_fill_keys($missing,'Required'), 422); }

        if ((float)$data['min_amount'] >= (float)$data['max_amount']) {
            $this->jsonError('Minimum amount must be less than maximum amount.', null, 422);
        }

        $id = $this->model->create([
            'name'               => $data['name'],
            'code'               => strtoupper(substr(preg_replace('/\s+/','',$data['name']),0,6)) . rand(10,99),
            'description'        => $data['description'] ?? null,
            'min_amount'         => (float)$data['min_amount'],
            'max_amount'         => (float)$data['max_amount'],
            'interest_rate'      => (float)$data['interest_rate'],
            'interest_type'      => $data['interest_type'],
            'min_term_months'    => (int)$data['min_term_months'],
            'max_term_months'    => (int)$data['max_term_months'],
            'processing_fee_pct' => (float)($data['processing_fee_pct'] ?? 0),
            'insurance_fee_pct'  => (float)($data['insurance_fee_pct'] ?? 0),
            'requires_collateral'=> isset($data['requires_collateral']) ? 1 : 0,
            'requires_guarantor' => isset($data['requires_guarantor'])  ? 1 : 0,
            'max_loan_multiplier'=> (int)($data['max_loan_multiplier'] ?? 3),
            'status'             => 'active',
        ]);

        $this->auth->logAudit($_SESSION['user_id'], 'loan_product_created', 'loan_products', (int)$id, 'LoanProduct', "Product '{$data['name']}' created");
        $this->jsonSuccess(['redirect' => APP_URL.'/loan-products'], 'Loan product created successfully!');
    }

    public function edit(int $id): void
    {
        $this->auth->requirePermission('settings.edit');
        $product  = $this->model->find($id);
        if (!$product) { $this->flash('error','Product not found.'); $this->redirect('/loan-products'); }
        $settings = $this->getSettings();
        $unreadNotifications = $this->getUnread();
        $pageTitle   = 'Edit: ' . $product['name'];
        $activePage  = 'loan-products';
        $breadcrumbs = ['Loan Products' => APP_URL.'/loan-products', 'Edit' => null];
        $this->view('loan-products/edit', compact('product','settings','pageTitle','activePage','breadcrumbs','unreadNotifications'));
    }

    public function update(int $id): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();
        $product = $this->model->find($id);
        if (!$product) { $this->jsonError('Product not found.', null, 404); }
        $data = $this->getPost();

        $this->model->update($id, [
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'min_amount'         => (float)$data['min_amount'],
            'max_amount'         => (float)$data['max_amount'],
            'interest_rate'      => (float)$data['interest_rate'],
            'interest_type'      => $data['interest_type'],
            'min_term_months'    => (int)$data['min_term_months'],
            'max_term_months'    => (int)$data['max_term_months'],
            'processing_fee_pct' => (float)($data['processing_fee_pct'] ?? 0),
            'insurance_fee_pct'  => (float)($data['insurance_fee_pct'] ?? 0),
            'requires_collateral'=> isset($data['requires_collateral']) ? 1 : 0,
            'requires_guarantor' => isset($data['requires_guarantor'])  ? 1 : 0,
            'max_loan_multiplier'=> (int)($data['max_loan_multiplier'] ?? 3),
            'status'             => $data['status'] ?? 'active',
        ]);

        $this->auth->logAudit($_SESSION['user_id'], 'loan_product_updated', 'loan_products', $id, 'LoanProduct', "Product '{$data['name']}' updated");
        $this->jsonSuccess(['redirect' => APP_URL.'/loan-products'], 'Loan product updated.');
    }

    public function toggleStatus(int $id): void
    {
        $this->auth->requirePermission('settings.edit');
        $this->verifyCsrf();
        $product   = $this->model->find($id);
        if (!$product) { $this->jsonError('Product not found.', null, 404); }
        $newStatus = $product['status'] === 'active' ? 'inactive' : 'active';
        $this->model->update($id, ['status' => $newStatus]);
        $this->jsonSuccess(['status' => $newStatus], 'Product ' . $newStatus . '.');
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
