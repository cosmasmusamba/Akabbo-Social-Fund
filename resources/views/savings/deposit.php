<?php
use App\Helpers\Avatar;
use App\Helpers\Format;

$pageTitle   = 'Record Deposit';
$activePage  = 'savings';
$breadcrumbs = ['Savings' => APP_URL.'/savings', 'Deposit' => null];
$member      = $member ?? null;
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Record Deposit</h1>
            <p class="text-sm text-slate-400 mt-0.5">Record a member savings deposit</p>
        </div>
        <a href="<?= APP_URL ?>/savings" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="depositForm">
                <?= $csrfField ?>
                <input type="hidden" name="savings_account_id" id="savings_account_id" value="<?= htmlspecialchars($_GET['account'] ?? '') ?>">
                
                <div class="mb-5">
                    <label class="form-label required">Member</label>
                    <div class="relative" id="depMemberWrap">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="depMemberSearch" autocomplete="off" class="form-control pl-9" placeholder="Search member by name, phone or number…" oninput="depSearch(this.value)" value="<?= !empty($member) ? htmlspecialchars($member['first_name'].' '.$member['last_name']) : '' ?>">
                        <div id="depMemberResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-10 max-h-60 overflow-y-auto"></div>
                    </div>
                    <input type="hidden" id="dep_member_id" name="member_id" value="<?= !empty($member) ? $member['id'] : '' ?>" required>
                </div>

                <div id="depMemberInfo" class="<?= !empty($member) ? '' : 'hidden' ?> mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                    <div class="flex items-center gap-3">
                        <div class="avatar-circle w-10 h-10" id="depAvatar"><?= !empty($member) ? Avatar::medium($member) : '?' ?></div>
                        <div>
                            <div class="font-bold text-slate-800" id="depName"><?= !empty($member) ? htmlspecialchars($member['first_name'].' '.$member['last_name']) : '' ?></div>
                            <div class="text-sm text-slate-500" id="depNo"><?= !empty($member) ? htmlspecialchars($member['member_no']) : '' ?></div>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-emerald-200">
                        <div class="text-xs text-slate-500">Current Savings</div>
                        <div class="font-bold text-emerald-700 text-lg" id="depBalance">—</div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="col-span-2">
                        <label class="form-label required">Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                        <input type="number" name="amount" class="form-control text-lg font-bold" placeholder="0" min="1000" step="500" required>
                    </div>
                    <div>
                        <label class="form-label required">Payment Method</label>
                        <select name="payment_method" class="form-control form-select" required>
                            <option value="cash">Cash</option>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">External Reference</label>
                        <input type="text" name="external_ref" class="form-control" placeholder="e.g. Mobile Money ref">
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Monthly contribution" value="Member savings deposit">
                    </div>
                </div>

                <!-- Pending Debit Info Box -->
                <div class="p-3 rounded-xl bg-blue-50 border border-blue-200 text-sm text-blue-700 mb-4 flex items-start gap-2">
                    <i class="fa-solid fa-circle-info mt-0.5 text-blue-500"></i>
                    <span>If the member has any <strong>pending service fees</strong> (e.g., statement request fees), the system will automatically deduct them from this deposit before crediting the net balance.</span>
                </div>

                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <a href="<?= APP_URL ?>/savings" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-gold"><i class="fa-solid fa-arrow-down-to-bracket"></i> Record Deposit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let depDebounce;
function depSearch(v) {
    clearTimeout(depDebounce);
    if (v.length < 2) { document.getElementById('depMemberResults').classList.add('hidden'); return; }
    depDebounce = setTimeout(async () => {
        const r = await fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(v)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        const box = document.getElementById('depMemberResults');
        if (!d.data || !d.data.length) {
            box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">No members found</div>';
            box.classList.remove('hidden');
            return;
        }
        box.innerHTML = d.data.map(m => `<div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 cursor-pointer" onclick='selectDepMember(${JSON.stringify(m).replace(/"/g, "&quot;")})'><div class="avatar-circle w-8 h-8 text-xs">${m.initials}</div><div><div class="font-semibold text-sm">${m.full_name}</div><div class="text-xs text-slate-400">${m.member_no} · ${m.total_savings_fmt}</div></div></div>`).join('');
        box.classList.remove('hidden');
    }, 300);
}

function selectDepMember(m) {
    document.getElementById('dep_member_id').value = m.id;
    document.getElementById('depMemberSearch').value = m.full_name;
    document.getElementById('depMemberResults').classList.add('hidden');
    document.getElementById('depAvatar').textContent = m.initials;
    document.getElementById('depName').textContent = m.full_name;
    document.getElementById('depNo').textContent = m.member_no;
    document.getElementById('depBalance').textContent = m.total_savings_fmt;
    document.getElementById('depMemberInfo').classList.remove('hidden');
}

document.addEventListener('click', e => {
    if (!document.getElementById('depMemberWrap').contains(e.target)) document.getElementById('depMemberResults').classList.add('hidden');
});

document.getElementById('depositForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Recording...';

    const formData = new FormData(this);
    try {
        const response = await fetch('<?= APP_URL ?>/savings/deposit', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            if (data.data && data.data.redirect) {
                setTimeout(() => { window.location.href = data.data.redirect; }, 1200);
            } else {
                setTimeout(() => { location.reload(); }, 1200);
            }
        } else {
            window.showToast('error', data.message || 'Deposit failed.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});
<?php if (!empty($member)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const query = '<?= addslashes($member['member_no']) ?>';
    fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(query)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(d => {
        if (d.data && d.data.length > 0) {
            const match = d.data.find(m => m.id == <?= (int)$member['id'] ?>) || d.data[0];
            selectDepMember(match);
        }
    });
});
<?php endif; ?>
</script>