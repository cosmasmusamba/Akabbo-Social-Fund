<?php
use App\Helpers\Format;

$pageTitle   = 'Transfer Savings';
$activePage  = 'savings';
$breadcrumbs = ['Savings' => APP_URL.'/savings', 'Transfer' => null];
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Transfer Savings</h1>
            <p class="text-sm text-slate-400 mt-0.5">Move funds between member accounts</p>
        </div>
        <a href="<?= APP_URL ?>/savings" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="transferForm">
                <?= $csrfField ?>
                
                <!-- From Member -->
                <div class="mb-5">
                    <label class="form-label required">From Member (Source)</label>
                    <div class="relative" id="fromMemberWrap">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="fromMemberSearch" autocomplete="off" class="form-control pl-9" placeholder="Search source member…" oninput="searchMember(this.value, 'from')">
                        <div id="fromMemberResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-10 max-h-60 overflow-y-auto"></div>
                    </div>
                    <input type="hidden" id="from_member_id" name="from_member_id" required>
                </div>

                <div id="fromMemberInfo" class="hidden mb-5 p-4 rounded-xl bg-blue-50 border border-blue-200">
                    <div class="flex items-center gap-3">
                        <div class="avatar-circle w-10 h-10" id="fromAvatar"></div>
                        <div>
                            <div class="font-bold text-slate-800" id="fromName"></div>
                            <div class="text-sm text-slate-500" id="fromNo"></div>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-blue-200">
                        <div class="text-xs text-slate-500">Available Balance</div>
                        <div class="font-bold text-emerald-700 text-lg" id="fromBalance"></div>
                    </div>
                </div>

                <!-- To Member -->
                <div class="mb-5">
                    <label class="form-label required">To Member (Destination)</label>
                    <div class="relative" id="toMemberWrap">
                        <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="text" id="toMemberSearch" autocomplete="off" class="form-control pl-9" placeholder="Search destination member…" oninput="searchMember(this.value, 'to')">
                        <div id="toMemberResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-10 max-h-60 overflow-y-auto"></div>
                    </div>
                    <input type="hidden" id="to_member_id" name="to_member_id" required>
                </div>

                <div id="toMemberInfo" class="hidden mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                    <div class="flex items-center gap-3">
                        <div class="avatar-circle w-10 h-10" id="toAvatar"></div>
                        <div>
                            <div class="font-bold text-slate-800" id="toName"></div>
                            <div class="text-sm text-slate-500" id="toNo"></div>
                        </div>
                    </div>
                </div>

                <!-- Amount & Description -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="col-span-2">
                        <label class="form-label required">Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                        <input type="number" name="amount" class="form-control text-lg font-bold" placeholder="0" min="1000" step="500" required>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label required">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Reason for transfer (min 10 chars)" required minlength="10">
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-700 mb-4 flex items-start gap-2">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
                    <span>Transfers require approval. Minimum savings and loan collateral rules apply to the sender's account.</span>
                </div>

                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <a href="<?= APP_URL ?>/savings" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-arrow-right-arrow-left"></i> Submit Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function searchMember(q, type) {
    if (q.length < 2) {
        const box = document.getElementById(type + 'MemberResults');
        if (box) box.classList.add('hidden');
        return;
    }
    fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(d => {
            const box = document.getElementById(type + 'MemberResults');
            if (!d.data || !d.data.length) {
                box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400">No members found</div>';
                box.classList.remove('hidden');
                return;
            }
            box.innerHTML = d.data.map(m => `
                <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 cursor-pointer" onclick='selectMember(${JSON.stringify(m).replace(/"/g, "&quot;")}, "${type}")'>
                    <div class="avatar-circle w-8 h-8 text-xs">${m.initials}</div>
                    <div>
                        <div class="font-semibold text-sm">${m.full_name}</div>
                        <div class="text-xs text-slate-400">${m.member_no} · ${m.total_savings_fmt}</div>
                    </div>
                </div>
            `).join('');
            box.classList.remove('hidden');
        });
}

function selectMember(m, type) {
    document.getElementById(type + '_member_id').value = m.id;
    document.getElementById(type + 'MemberSearch').value = m.full_name;
    document.getElementById(type + 'MemberResults').classList.add('hidden');
    document.getElementById(type + 'Avatar').textContent = m.initials;
    document.getElementById(type + 'Name').textContent = m.full_name;
    document.getElementById(type + 'No').textContent = m.member_no;
    document.getElementById(type + 'MemberInfo').classList.remove('hidden');
    
    if (type === 'from') {
        document.getElementById('fromBalance').textContent = m.total_savings_fmt;
    }
}

document.addEventListener('click', e => {
    if (!e.target.closest('#fromMemberWrap')) {
        const fromBox = document.getElementById('fromMemberResults');
        if (fromBox) fromBox.classList.add('hidden');
    }
    if (!e.target.closest('#toMemberWrap')) {
        const toBox = document.getElementById('toMemberResults');
        if (toBox) toBox.classList.add('hidden');
    }
});

// Transfer form submission
document.getElementById('transferForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const desc = this.querySelector('input[name="description"]').value.trim();
    if (desc.length < 10) {
        window.showToast('error', 'Transfer description must be at least 10 characters long.');
        return;
    }

    const btn = this.querySelector('button[type="submit"]');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

    const formData = new FormData(this);
    try {
        const response = await fetch('<?= APP_URL ?>/savings/transfer', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => { window.location.href = '<?= APP_URL ?>/savings'; }, 1500);
        } else {
            window.showToast('error', data.message || 'Transfer failed.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});
<?php if (!empty($fromMember)): ?>
document.addEventListener('DOMContentLoaded', function() {
    // Automatically trigger the search API to populate the member card with real-time financial data
    const query = '<?= addslashes($fromMember['member_no']) ?>';
    fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(query)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(d => {
        if (d.data && d.data.length > 0) {
            const match = d.data.find(m => m.id == <?= (int)$fromMember['id'] ?>) || d.data[0];
            selectMember(match, 'from');
        }
    });
});
<?php endif; ?>
</script>