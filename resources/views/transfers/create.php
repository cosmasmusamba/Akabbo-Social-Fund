<?php
$pageTitle   = 'New Fund Transfer';
$activePage  = 'transfers';
$breadcrumbs = ['Transfers' => APP_URL.'/transfers', 'New' => null];
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">New Fund Transfer</h1>
            <p class="text-sm text-slate-400 mt-0.5">Move funds between member accounts</p>
        </div>
        <a href="<?= APP_URL ?>/transfers" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="transferForm">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">

                <!-- From Member -->
                <div class="mb-5">
                    <label class="form-label required">From Member (Sender)</label>
                    <div class="relative" id="fromMemberWrap">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="fromMemberSearch" autocomplete="off" class="form-control pl-9" placeholder="Search source member by name, phone or number…" oninput="searchMember(this.value, 'from')">
                        <div id="fromMemberResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-10 max-h-60 overflow-y-auto"></div>
                    </div>
                    <input type="hidden" id="from_member_id" name="from_member_id" required>
                    
                    <div id="fromMemberInfo" class="hidden mt-3 p-4 rounded-xl bg-blue-50 border border-blue-200">
                        <div class="flex items-center gap-3">
                            <div class="avatar-circle w-10 h-10" id="fromAvatar"></div>
                            <div class="flex-1">
                                <div class="font-bold" id="fromName"></div>
                                <div class="text-sm text-slate-500" id="fromNo"></div>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-blue-200">
                            <div class="text-xs text-slate-500">Available Balance</div>
                            <div class="font-bold text-emerald-700 text-lg" id="fromBalance"></div>
                        </div>
                    </div>
                </div>

                <!-- To Member -->
                <div class="mb-5">
                    <label class="form-label required">To Member (Recipient)</label>
                    <div class="relative" id="toMemberWrap">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="toMemberSearch" autocomplete="off" class="form-control pl-9" placeholder="Search destination member by name, phone or number…" oninput="searchMember(this.value, 'to')">
                        <div id="toMemberResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-10 max-h-60 overflow-y-auto"></div>
                    </div>
                    <input type="hidden" id="to_member_id" name="to_member_id" required>
                    
                    <div id="toMemberInfo" class="hidden mt-3 p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                        <div class="flex items-center gap-3">
                            <div class="avatar-circle w-10 h-10" id="toAvatar"></div>
                            <div class="flex-1">
                                <div class="font-bold" id="toName"></div>
                                <div class="text-sm text-slate-500" id="toNo"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Amount & Details -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="col-span-2">
                        <label class="form-label required">Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                        <input type="number" name="amount" class="form-control text-lg font-bold" placeholder="0" min="1000" step="500" required>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label required">Transfer Date</label>
                        <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label required">Description / Reason</label>
                        <textarea name="description" rows="2" class="form-control" placeholder="State the reason for this transfer (min 10 characters)…" required minlength="10"></textarea>
                        <p class="text-xs text-slate-400 mt-1"><i class="fa-solid fa-circle-info mr-1"></i>This will be reviewed by the approving officer and recorded in the audit log.</p>
.                    </div>
                </div>

                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <a href="<?= APP_URL ?>/transfers" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fa-solid fa-paper-plane"></i> Submit for Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let searchDebounce;
let currentFromBalance = 0;

function searchMember(q, type) {
    clearTimeout(searchDebounce);
    if (q.length < 2) { 
        document.getElementById(type + 'MemberResults').classList.add('hidden'); 
        return; 
    }
    searchDebounce = setTimeout(async () => {
        try {
            const r = await fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            const box = document.getElementById(type + 'MemberResults');
            
            if (!d.data || !d.data.length) {
                box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">No members found</div>';
            } else {
                box.innerHTML = d.data.map(m => `
                    <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-0" onclick='selectMember(${JSON.stringify(m).replace(/"/g, "&quot;")}, "${type}")'>
                        <div class="avatar-circle w-8 h-8 text-xs">${m.initials}</div>
                        <div class="flex-1">
                            <div class="font-semibold text-sm">${m.full_name}</div>
                            <div class="text-xs text-slate-400">${m.member_no} · ${m.total_savings_fmt}</div>
                        </div>
                    </div>
                `).join('');
            }
            box.classList.remove('hidden');
        } catch (e) { console.error(e); }
    }, 300);
}

function selectMember(m, type) {
    document.getElementById(type + '_member_id').value = m.id;
    document.getElementById(type + 'MemberSearch').value = m.full_name;
    document.getElementById(type + 'MemberResults').classList.add('hidden');
    document.getElementById(type + 'Avatar').textContent = m.initials;
    document.getElementById(type + 'Name').textContent = m.full_name;
    document.getElementById(type + 'No').textContent = m.member_no;
    
    if (type === 'from') {
        document.getElementById('fromBalance').textContent = m.total_savings_fmt;
        currentFromBalance = parseFloat(String(m.total_savings).replace(/[^0-9.-]/g, ''));
    }
    document.getElementById(type + 'MemberInfo').classList.remove('hidden');
}

document.addEventListener('click', e => { 
    if (!document.getElementById('fromMemberWrap')?.contains(e.target)) document.getElementById('fromMemberResults')?.classList.add('hidden'); 
    if (!document.getElementById('toMemberWrap')?.contains(e.target)) document.getElementById('toMemberResults')?.classList.add('hidden'); 
});

document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const fromId = document.getElementById('from_member_id').value;
    const toId = document.getElementById('to_member_id').value;
    const amountInput = this.querySelector('input[name="amount"]');
    const amount = parseFloat(amountInput.value);

    if (!fromId || !toId) {
        window.showToast('error', 'Please select both sender and recipient members.');
        return;
    }
    if (fromId === toId) {
        window.showToast('error', 'Sender and recipient cannot be the same member.');
        return;
    }
    if (isNaN(amount) || amount <= 0) {
        window.showToast('error', 'Please enter a valid amount.');
        amountInput.focus();
        return;
    }
    if (amount > currentFromBalance) {
        window.showToast('error', `Transfer amount exceeds available balance of ${currentFromBalance.toLocaleString()}.`);
        amountInput.focus();
        return;
    }

    const btn = document.getElementById('submitBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

    const formData = new FormData(this);
    try {
        const response = await fetch('<?= APP_URL ?>/transfers/store', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => { window.location.href = data.data?.redirect || '<?= APP_URL ?>/transfers'; }, 1000);
        } else {
            window.showToast('error', data.message || 'Transfer failed.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        console.error(err);
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});
</script>