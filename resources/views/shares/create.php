<?php
// http://localhost/akabbo/shares/create?member=5 should auto populate
use App\Helpers\Format;

$pageTitle   = 'Issue Shares';
$activePage  = 'shares';
$breadcrumbs = ['Shares' => APP_URL.'/shares', 'Issue' => null];
$members     = $members ?? [];
$config      = $config ?? ['par_value' => 1000, 'max_shares_per_member' => 1000];
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Issue Shares to Member</h1>
            <p class="text-sm text-slate-400 mt-0.5">Par value: <?= Format::currency((float)($config['par_value'] ?? 1000)) ?> per share</p>
        </div>
        <a href="<?= APP_URL ?>/shares" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="issueSharesForm">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                
                <!-- Member Search -->
                <div class="mb-5">
                    <label class="form-label required">Member</label>
                    <div class="relative" id="shareMemberWrap">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="shareMemberSearch" autocomplete="off" class="form-control pl-9" 
                           placeholder="Search member by name, phone or number…" oninput="searchMember(this.value)"
                           value="<?= !empty($prefillMember) ? htmlspecialchars($prefillMember['full_name']) : '' ?>">
                        <div id="shareMemberResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-10 max-h-60 overflow-y-auto"></div>
                    </div>
                    <input type="hidden" id="share_member_id" name="member_id" required>
                </div>

                <!-- Selected Member Info -->
                <div id="shareMemberInfo" class="hidden mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="avatar-circle w-10 h-10" id="smAvatar">?</div>
                        <div>
                            <div class="font-bold text-slate-800" id="smName">—</div>
                            <div class="text-sm text-slate-500" id="smNo">—</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3 pt-3 border-t border-emerald-200 text-xs">
                        <div><span class="text-slate-500">Current Shares:</span> <span class="font-bold" id="smShares">0</span></div>
                        <div><span class="text-slate-500">Max Allowed:</span> <span class="font-bold"><?= number_format($config['max_shares_per_member'] ?? 1000) ?></span></div>
                    </div>
                </div>

                <!-- Share Details -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="form-label required">Number of Shares</label>
                        <input type="number" name="shares_qty" id="sharesQty" class="form-control" 
                               min="<?= $config['min_shares'] ?? 1 ?>" max="<?= $config['max_shares_per_member'] ?? 1000 ?>" 
                               placeholder="e.g. 10" required oninput="calcTotal()">
                    </div>
                    <div>
                        <label class="form-label">Total Cost</label>
                        <div class="form-control bg-slate-50 font-bold text-emerald-700 flex items-center" id="totalCost">—</div>
                        <p class="text-xs text-slate-400 mt-1">@ <?= Format::currency((float)($config['par_value'] ?? 1000)) ?> per share</p>
                    </div>
                    <div>
                        <label class="form-label required">Payment Method</label>
                        <select name="payment_method" class="form-control form-select" required>
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="deduction">Savings Deduction</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label required">Transaction Date</label>
                        <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <!-- Mandatory Notes -->
                <div class="mb-5">
                    <label class="form-label required">Notes / Reason <span class="text-red-500">*</span></label>
                    <textarea name="notes" rows="3" class="form-control" required minlength="10" 
                              placeholder="Mandatory: describe the purpose of this share issuance, board resolution reference, etc. (min 10 chars)"></textarea>
                    <p class="text-xs text-amber-600 mt-1"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Notes are mandatory for all share transactions and will be reviewed by an approver.</p>
                </div>

                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <a href="<?= APP_URL ?>/shares" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fa-solid fa-certificate"></i> Issue Shares (Pending Approval)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const PAR_VALUE = <?= (float)($config['par_value'] ?? 1000) ?>;
let searchDebounce;

function searchMember(v) {
    clearTimeout(searchDebounce);
    if (v.length < 2) { document.getElementById('shareMemberResults').classList.add('hidden'); return; }
    searchDebounce = setTimeout(async () => {
        try {
            const r = await fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(v)}`, {headers:{'X-Requested-With':'XMLHttpRequest'}});
            const d = await r.json();
            const box = document.getElementById('shareMemberResults');
            if (!d.data?.length) {
                box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">No members found</div>';
            } else {
                box.innerHTML = d.data.map(m => `
                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 cursor-pointer" onclick='selectMember(${JSON.stringify(m).replace(/"/g,"&quot;")})'>
                        <div class="avatar-circle w-8 h-8 text-xs">${m.initials}</div>
                        <div class="flex-1">
                            <div class="font-semibold text-sm">${m.full_name}</div>
                            <div class="text-xs text-slate-400">${m.member_no} · ${m.total_savings_fmt}</div>
                        </div>
                        ${m.is_shareholder ? '<span class="text-xs font-bold text-amber-600"><i class="fa-solid fa-star"></i> Shareholder</span>' : ''}
                    </div>
                `).join('');
            }
            box.classList.remove('hidden');
        } catch(e) {}
    }, 300);
}

function selectMember(m) {
    document.getElementById('share_member_id').value = m.id;
    document.getElementById('shareMemberSearch').value = m.full_name;
    document.getElementById('shareMemberResults').classList.add('hidden');
    document.getElementById('smAvatar').textContent = m.initials;
    document.getElementById('smName').textContent = m.full_name;
    document.getElementById('smNo').textContent = m.member_no;
    document.getElementById('smShares').textContent = m.shares_held ?? 0;
    document.getElementById('shareMemberInfo').classList.remove('hidden');
    calcTotal();
}

document.addEventListener('click', e => {
    if (!document.getElementById('shareMemberWrap').contains(e.target)) {
        document.getElementById('shareMemberResults').classList.add('hidden');
    }
});

function calcTotal() {
    const qty = parseInt(document.getElementById('sharesQty').value) || 0;
    document.getElementById('totalCost').textContent = qty > 0 ? '<?= $settings['currency_symbol'] ?? 'USh' ?> ' + (qty * PAR_VALUE).toLocaleString() : '—';
}

document.getElementById('issueSharesForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!document.getElementById('share_member_id').value) {
        window.showToast('error', 'Please select a member first.');
        return;
    }
    const btn = document.getElementById('submitBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    
    try {
        const response = await fetch('<?= APP_URL ?>/shares/store', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(this)
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => { window.location.href = data.data?.redirect || '<?= APP_URL ?>/shares'; }, 1000);
        } else {
            window.showToast('error', data.message || 'Submission failed.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});

// Auto-populate member card if ?member=ID was in the URL
<?php if (!empty($prefillMember)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const query = '<?= addslashes($prefillMember['member_no']) ?>';
    fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(query)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(d => {
        if (d.data && d.data.length > 0) {
            // Find the exact member match and trigger the selection UI
            const match = d.data.find(m => m.id == <?= (int)$prefillMember['id'] ?>) || d.data[0];
            selectMember(match);
        }
    });
});
<?php endif; ?>
</script>