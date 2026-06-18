<?php
$pageTitle   = 'Edit: ' . ($expense['expense_ref'] ?? 'Expense');
$activePage  = 'expenses';
$breadcrumbs = ['Expenses' => APP_URL . '/expenses', $expense['expense_ref'] ?? '' => APP_URL . '/expenses/' . ($expense['id'] ?? ''), 'Edit' => null];
?>

<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Expense</h1>
            <p class="text-sm text-slate-400 mt-0.5"><?= htmlspecialchars($expense['expense_ref'] ?? '') ?> · <?= Format::statusPill($expense['status'] ?? 'draft') ?></p>
        </div>
        <a href="<?= APP_URL ?>/expenses/<?= $expense['id'] ?? 0 ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="editExpenseForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                    <div class="md:col-span-2">
                        <label class="form-label required">Expense Category</label>
                        <select name="category_id" class="form-control form-select" required>
                            <option value="">— Select category —</option>
                            <?php foreach ($categories ?? [] as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= ($expense['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="form-label required">Title / Description</label>
                        <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($expense['title'] ?? '') ?>" required>
                    </div>

                    <div>
                        <label class="form-label required">Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                        <input type="number" name="amount" class="form-control" value="<?= htmlspecialchars($expense['amount'] ?? '') ?>" min="1" step="100" required>
                    </div>

                    <div>
                        <label class="form-label required">Expense Date</label>
                        <input type="date" name="expense_date" class="form-control" value="<?= htmlspecialchars($expense['expense_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div>
                        <label class="form-label required">Payment Method</label>
                        <select name="payment_method" class="form-control form-select" required>
                            <option value="cash" <?= ($expense['payment_method'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="mobile_money" <?= ($expense['payment_method'] ?? '') === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
                            <option value="bank_transfer" <?= ($expense['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                            <option value="cheque" <?= ($expense['payment_method'] ?? '') === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Payee Name</label>
                        <input type="text" name="payee_name" class="form-control" value="<?= htmlspecialchars($expense['payee_name'] ?? '') ?>">
                    </div>

                    <div>
                        <label class="form-label">Payee Contact</label>
                        <input type="text" name="payee_contact" class="form-control" value="<?= htmlspecialchars($expense['payee_contact'] ?? '') ?>">
                    </div>

                    <div>
                        <label class="form-label">Receipt / Invoice Number</label>
                        <input type="text" name="receipt_no" class="form-control" value="<?= htmlspecialchars($expense['receipt_no'] ?? '') ?>">
                    </div>

                    <!-- Existing Attachment Display -->
                    <?php if (!empty($expense['attachment_path'])): ?>
                        <div class="md:col-span-2">
                            <label class="form-label">Current Attachment</label>
                            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                                <?php 
                                $ext = strtolower(pathinfo($expense['attachment_path'], PATHINFO_EXTENSION));
                                $icon = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']) ? 'fa-file-image text-green-600' : 'fa-file-pdf text-red-500';
                                ?>
                                <i class="fa-regular <?= $icon ?> text-2xl"></i>
                                <span class="text-sm flex-1"><?= htmlspecialchars($expense['attachment_path']) ?></span>
                                <label class="text-xs text-blue-600 hover:underline cursor-pointer">
                                    Replace
                                    <input type="file" id="receiptFile" name="receipt" class="hidden" accept="image/jpeg,image/png,image/webp,application/pdf">
                                </label>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="md:col-span-2">
                            <label class="form-label">Receipt Attachment</label>
                            <div class="border-2 border-dashed border-slate-200 rounded-xl p-4 text-center hover:border-green-400 hover:bg-green-50 transition-all cursor-pointer" onclick="document.getElementById('receiptFile').click()">
                                <i class="fa-regular fa-file-image text-2xl text-slate-300 mb-1 block"></i>
                                <p class="text-xs text-slate-400">Click to upload receipt scan</p>
                                <input type="file" id="receiptFile" name="receipt" class="hidden" accept="image/jpeg,image/png,image/webp,application/pdf">
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div id="receiptPreview" class="md:col-span-2 hidden">
                        <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
                            <i id="receiptIcon" class="fa-regular fa-file-image text-green-600 text-2xl"></i>
                            <span class="text-sm flex-1" id="receiptFilename"></span>
                            <button type="button" onclick="clearReceipt()" class="text-red-500 text-xs"><i class="fa-regular fa-trash-can"></i> Remove</button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <a href="<?= APP_URL ?>/expenses/<?= $expense['id'] ?? 0 ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fa-solid fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('receiptFile').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        window.showToast('error', 'File too large, max 2MB.');
        this.value = '';
        return;
    }
    document.getElementById('receiptFilename').textContent = file.name;
    document.getElementById('receiptIcon').className = file.type === 'application/pdf' ? 'fa-regular fa-file-pdf text-red-500 text-2xl' : 'fa-regular fa-file-image text-green-600 text-2xl';
    document.getElementById('receiptPreview').classList.remove('hidden');
});

function clearReceipt() {
    document.getElementById('receiptFile').value = '';
    document.getElementById('receiptPreview').classList.add('hidden');
}

document.getElementById('editExpenseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    
    const formData = new FormData(this);
    try {
        const response = await fetch('<?= APP_URL ?>/expenses/<?= $expense['id'] ?? 0 ?>/update', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => { window.location.href = data.data?.redirect || '<?= APP_URL ?>/expenses/' . ($expense['id'] ?? 0); }, 800);
        } else {
            window.showToast('error', data.message || 'Update failed.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});
</script>