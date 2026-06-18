<?php
// Enforce logAudit and (RBAC + IDOR). Notify admins of success and failure.
namespace App\Controllers;

use App\Models\LoanProduct;
use App\Services\NotificationService;

class LoanProductController extends BaseController
{
    private LoanProduct $model;
    private NotificationService $notif;

    public function __construct()
    {
        parent::__construct();
        $this->auth->requireAuth();
        $this->model = new LoanProduct();
        $this->notif = new NotificationService();
    }

    // ── CRUD METHODS ───────────────────────────────────────────────

    public function index(): void
    {
        // ENFORCE: Centralized Scope Gatekeeper (Loan products are strictly Global configurations)
        $perms = ['global' => 'loan_products.view'];
        $this->requireScopeAccess($perms, 'You do not have permission to view loan products.');
        
        $products = $this->model->getWithLoanCounts();
        
        // ENFORCE: Audit log for viewing
        $this->logAudit('loan_products_viewed', 'loan_products', null, null, "Viewed loan products list");

        $this->view('loan-products/index', array_merge(
            $this->prepareViewData('Loan Products', 'loan-products'),
            ['products' => $products]
        ));
    }

    public function create(): void
    {
        $perms = ['global' => 'loan_products.create'];
        $this->requireScopeAccess($perms, 'You do not have permission to create loan products.');
        
        $this->view('loan-products/create', $this->prepareViewData('New Loan Product', 'loan-products', ['Loan Products' => APP_URL.'/loan-products', 'New' => null]));
    }

    public function store(): void
    {
        $perms = ['global' => 'loan_products.create'];
        $this->requireScopeAccess($perms, 'You do not have permission to create loan products.');
        $this->verifyCsrf();
        
        $data = $this->getPost();
        $missing = $this->validateRequired($data, ['name','min_amount','max_amount','interest_rate','interest_type','min_term_months','max_term_months']);
        if ($missing) $this->jsonError('Required fields missing.', array_fill_keys($missing,'Required'), 422);
        if ((float)$data['min_amount'] >= (float)$data['max_amount']) $this->jsonError('Minimum amount must be less than maximum amount.', null, 422);

        $productData = [
            'name'                => $data['name'],
            'code'                => strtoupper(substr(preg_replace('/\s+/','',$data['name']),0,6)) . rand(10,99),
            'description'         => $data['description'] ?? null,
            'min_amount'          => (float)$data['min_amount'],
            'max_amount'          => (float)$data['max_amount'],
            'interest_rate'       => (float)$data['interest_rate'],
            'interest_type'       => $data['interest_type'],
            'min_term_months'     => (int)$data['min_term_months'],
            'max_term_months'     => (int)$data['max_term_months'],
            'processing_fee_pct'  => (float)($data['processing_fee_pct'] ?? 0),
            'insurance_fee_pct'   => (float)($data['insurance_fee_pct'] ?? 0),
            'requires_collateral' => isset($data['requires_collateral']) ? 1 : 0,
            'requires_guarantor'  => isset($data['requires_guarantor']) ? 1 : 0,
            'max_loan_multiplier' => (int)($data['max_loan_multiplier'] ?? 3),
            'status'              => 'active',
            'created_by'          => $_SESSION['user_id'],
        ];

        $this->db->beginTransaction();
        try {
            $id = $this->model->create($productData);
            
            // ENFORCE: logAudit with JSON snapshot
            $this->logAudit('loan_product_created', 'loan_products', (int)$id, 'LoanProduct', 
                "Product '{$data['name']}' created", null, json_encode($productData));
                
            // ENFORCE: Notify admins of success
            $this->notifyAdmins('loan_product_created', 'New Loan Product Created', 
                "A new loan product '{$data['name']}' has been created by an administrator.");
                
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL.'/loan-products'], 'Loan product created successfully!');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('loan_product_creation_failed', 'Loan Product Creation Failed', 
                "Failed to create loan product '{$data['name']}': " . $e->getMessage());
            $this->jsonError('Failed to create loan product: ' . $e->getMessage(), null, 500);
        }
    }

    public function edit(int $id): void
    {
        $perms = ['global' => 'loan_products.edit'];
        $this->requireScopeAccess($perms, 'You do not have permission to edit loan products.');
        
        // ENFORCE: IDOR / Existence check
        $product = $this->model->find($id);
        if (!$product) { 
            $this->flash('error','Product not found.'); 
            $this->redirect('/loan-products'); 
        }
        
        $this->view('loan-products/edit', array_merge(
            $this->prepareViewData('Edit: ' . $product['name'], 'loan-products', ['Loan Products' => APP_URL.'/loan-products', 'Edit' => null]),
            ['product' => $product]
        ));
    }

    public function update(int $id): void
    {
        $perms = ['global' => 'loan_products.edit'];
        $this->requireScopeAccess($perms, 'You do not have permission to edit loan products.');
        $this->verifyCsrf();
        
        // ENFORCE: IDOR / Existence check
        $oldData = $this->model->getOldValues($id);
        if (!$oldData) $this->jsonError('Product not found.', null, 404);
        
        $data = $this->getPost();
        
        if ((float)$data['min_amount'] >= (float)$data['max_amount']) $this->jsonError('Minimum amount must be less than maximum amount.', null, 422);

        $updateData = [
            'name'                => $data['name'],
            'description'         => $data['description'] ?? null,
            'min_amount'          => (float)$data['min_amount'],
            'max_amount'          => (float)$data['max_amount'],
            'interest_rate'       => (float)$data['interest_rate'],
            'interest_type'       => $data['interest_type'],
            'min_term_months'     => (int)$data['min_term_months'],
            'max_term_months'     => (int)$data['max_term_months'],
            'processing_fee_pct'  => (float)($data['processing_fee_pct'] ?? 0),
            'insurance_fee_pct'   => (float)($data['insurance_fee_pct'] ?? 0),
            'requires_collateral' => isset($data['requires_collateral']) ? 1 : 0,
            'requires_guarantor'  => isset($data['requires_guarantor']) ? 1 : 0,
            'max_loan_multiplier' => (int)($data['max_loan_multiplier'] ?? 3),
            'status'              => $data['status'] ?? 'active',
        ];

        $this->db->beginTransaction();
        try {
            $this->model->update($id, $updateData);
            
            // ENFORCE: logAudit with JSON snapshots of old and new data
            $this->logAudit('loan_product_updated', 'loan_products', $id, 'LoanProduct', 
                "Product '{$data['name']}' updated", json_encode($oldData), json_encode($updateData));
                
            // ENFORCE: Notify admins of success
            $this->notifyAdmins('loan_product_updated', 'Loan Product Updated', 
                "The loan product '{$data['name']}' has been updated by an administrator.");
                
            $this->db->commit();
            $this->jsonSuccess(['redirect' => APP_URL.'/loan-products'], 'Loan product updated.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('loan_product_update_failed', 'Loan Product Update Failed', 
                "Failed to update loan product '{$data['name']}': " . $e->getMessage());
            $this->jsonError('Failed to update loan product: ' . $e->getMessage(), null, 500);
        }
    }

    public function toggleStatus(int $id): void
    {
        $perms = ['global' => 'loan_products.edit'];
        $this->requireScopeAccess($perms, 'You do not have permission to modify loan products.');
        $this->verifyCsrf();
        
        // ENFORCE: IDOR / Existence check
        $product = $this->model->find($id);
        if (!$product) $this->jsonError('Product not found.', null, 404);
        
        $newStatus = $product['status'] === 'active' ? 'inactive' : 'active';
        $oldData = $product;
        $newData = ['status' => $newStatus];
        
        $this->db->beginTransaction();
        try {
            $this->model->update($id, $newData);
            
            // ENFORCE: logAudit with JSON snapshots
            $this->logAudit('loan_product_status_toggled', 'loan_products', $id, 'LoanProduct', 
                "Product '{$product['name']}' status changed to {$newStatus}", json_encode($oldData), json_encode($newData));
                
            // ENFORCE: Notify admins of success
            $this->notifyAdmins('loan_product_status_toggled', 'Loan Product Status Changed', 
                "The status of loan product '{$product['name']}' was changed to '{$newStatus}' by an administrator.");
                
            $this->db->commit();
            $this->jsonSuccess(['status' => $newStatus], 'Product ' . $newStatus . '.');
        } catch (\Exception $e) {
            $this->db->rollback();
            // ENFORCE: Notify admins of failure
            $this->notifyAdmins('loan_product_toggle_failed', 'Loan Product Status Change Failed', 
                "Failed to change status of loan product '{$product['name']}': " . $e->getMessage());
            $this->jsonError('Failed to change product status: ' . $e->getMessage(), null, 500);
        }
    }

    // ── HELPERS ────────────────────────────────────────────────────

    /**
     * ENFORCE: Notify all admins via multi-channel dispatch
     */
    private function notifyAdmins(string $type, string $title, string $message): void
    {
        $admins = $this->db->fetchAll("
            SELECT DISTINCT u.id 
            FROM users u 
            JOIN role_permissions rp ON rp.role_id = u.role_id 
            JOIN permissions p ON p.id = rp.permission_id 
            WHERE p.slug IN ('loan_products.view', 'loan_products.create', 'loan_products.edit') AND u.status = 'active'
        ");
        foreach ($admins as $admin) {
            // USE dispatch instead of sendToUser for multi-channel (System + Email + SMS)
            $this->notif->dispatch((int)$admin['id'], $type, $title, $message);
        }
    }
}