<?php
use App\Helpers\Format;
use App\Helpers\Avatar;
$pageTitle   = $pageTitle ?? ($group['name'] ?? 'Group');
$activePage  = $activePage ?? 'groups';
$breadcrumbs = $breadcrumbs ?? ['Groups' => APP_URL . '/groups', $group['name'] ?? '' => null];
$group       = $group ?? [];
$members     = $members ?? [];
?>
<style>
.profile-hero { background: linear-gradient(135deg, #0a4033 0%, #136b55 60%, #1a8f6f 100%); border-radius: 16px; padding: 28px; color: #fff; position: relative; overflow: hidden; }
.profile-hero::after { content: ''; position: absolute; right: -40px; top: -40px; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,0.06); }
.profile-avatar { width: 80px; height: 80px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.4); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 700; background: rgba(255,255,255,0.15); color: #fff; flex-shrink: 0; overflow: hidden; }
.profile-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.info-row { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; width: 130px; flex-shrink: 0; padding-top: 2px; }
.info-value { font-size: 0.875rem; color: #1e293b; font-weight: 500; flex: 1; word-break: break-word; overflow-wrap: break-word; white-space: normal; }
@media (max-width: 640px) { .info-row { flex-direction: column; gap: 4px; } .info-label { width: auto; } }
</style>

<div class="flex items-center justify-between mb-5">
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-house-chimney text-xs"></i></a>
        <span class="sep">/</span><a href="<?= APP_URL ?>/groups">Groups</a>
        <span class="sep">/</span><span class="current"><?= htmlspecialchars($group['name'] ?? '') ?></span>
    </div>
    <div class="flex gap-2">
        <?php if ($auth->can('groups.edit')): ?>
            <a href="<?= APP_URL ?>/groups/<?= $group['id'] ?? 0 ?>/edit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-pen"></i> Edit Group</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/members?group=<?= $group['id'] ?? 0 ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-users"></i> All Members</a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Left Column: Details -->
    <div class="space-y-4">
        <div class="card">
            <div class="card-body text-center py-7" style="background:linear-gradient(135deg,#f0fdf9,#ecfdf5);border-radius:14px;">
                <?php if (!empty($group['avatar'])): ?>
                    <img src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($group['avatar']) ?>" class="w-20 h-20 rounded-2xl object-cover mx-auto mb-3 border-2 border-white shadow-md" alt="">
                <?php else: ?>
                    <div class="profile-avatar mx-auto mb-3" style="background:linear-gradient(135deg,#136b55,#1a8f6f)">
                        <?= strtoupper(substr($group['name'] ?? 'G', 0, 2)) ?>
                    </div>
                <?php endif; ?>
                <h2 class="text-xl font-bold text-slate-800 mb-1"><?= htmlspecialchars($group['name'] ?? '') ?></h2>
                <div class="text-xs text-slate-400 font-mono mb-2"><?= htmlspecialchars($group['group_code'] ?? '') ?></div>
                <?= Format::statusPill($group['status'] ?? 'active') ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="section-title">Group Details</h3></div>
            <div class="card-body">
                <?php
                $details = [
                    ['Description', $group['description'] ?? '—'],
                    ['Meeting',     $group['meeting_schedule'] ?? '—'],
                    ['Created',     Format::date($group['created_at'] ?? '')],
                ];
                foreach ($details as [$label, $value]): ?>
                    <div class="info-row">
                        <span class="info-label"><?= $label ?></span>
                        <span class="info-value"><?= htmlspecialchars($value) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="section-title">Financial Summary</h3></div>
            <div class="card-body">
                <?php
                $totalSavings = array_sum(array_column($members, 'total_savings'));
                $kpis = [
                    ['Members',      count($members),                'text-slate-800'],
                    ['Total Savings', Format::currency($totalSavings), 'text-emerald-700'],
                ];
                foreach ($kpis as [$label, $val, $color]): ?>
                    <div class="info-row">
                        <span class="info-label"><?= $label ?></span>
                        <span class="info-value font-bold <?= $color ?>"><?= $val ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Members List -->
    <div class="lg:col-span-2">
        <div class="card">
            <div class="card-header">
                <h3 class="section-title"><i class="fa-solid fa-users mr-1.5" style="color:var(--green-mid, #136b55)"></i> Members (<?= count($members) ?>)</h3>
                <?php if ($auth->can('groups.edit')): ?>
                    <button onclick="openAddMemberModal()" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus"></i> Add Existing Member</button>
                <?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th class="hidden sm:table-cell">Phone</th>
                            <th>Savings</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-12">
                                    <i class="fa-regular fa-users text-4xl text-slate-200 block mb-2"></i>
                                    <p class="text-slate-400 text-sm">No members in this group yet</p>
                                    <?php if ($auth->can('members.create')): ?>
                                        <a href="<?= APP_URL ?>/members/create" class="btn btn-primary btn-sm mt-3"><i class="fa-solid fa-user-plus"></i> Register New Member</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: foreach ($members as $m): ?>
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <?= Avatar::small($m) ?>
                                        <div>
                                            <div class="font-semibold text-sm text-slate-800"><?= htmlspecialchars(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?></div>
                                            <div class="text-xs text-slate-400"><?= htmlspecialchars($m['member_no'] ?? '') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell text-sm"><?= htmlspecialchars($m['phone'] ?? '') ?></td>
                                <td class="font-semibold text-emerald-700"><?= Format::currency((float)($m['total_savings'] ?? 0)) ?></td>
                                <td><?= Format::statusPill($m['status'] ?? 'active') ?></td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <a href="<?= APP_URL ?>/members/<?= $m['id'] ?? 0 ?>" class="btn btn-secondary btn-sm py-1 px-2.5" title="View Member">
                                            <i class="fa-solid fa-eye text-xs"></i>
                                        </a>
                                        <?php if ($auth->can('groups.edit')): ?>
                                            <button onclick="removeMemberFromGroup(<?= $m['id'] ?>, '<?= addslashes(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?>')" 
                                                    class="btn btn-danger btn-sm py-1 px-2.5" title="Remove from Group">
                                                <i class="fa-solid fa-user-minus text-xs"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div id="addMemberModal" class="hidden modal-overlay">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-user-plus text-green-500 mr-2"></i>Add Member to Group</h3>
            <button onclick="closeAddMemberModal()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="modal-body">
            <div class="mb-4">
                <label class="form-label required">Search Existing Member</label>
                <div class="relative">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="text" id="memberSearch" autocomplete="off" class="form-control pl-9" placeholder="Search by name, phone, or member number…" oninput="searchAvailableMembers(this.value)">
                    <div id="memberSearchResults" class="hidden absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-lg z-20 max-h-60 overflow-y-auto"></div>
                </div>
            </div>
            <div id="selectedMemberInfo" class="hidden p-4 rounded-xl bg-slate-50 border border-slate-200 mb-4">
                <div class="flex items-center gap-3">
                    <div class="avatar-circle w-10 h-10 flex-shrink-0" id="selectedAvatar">?</div>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-slate-800 truncate" id="selectedName">—</div>
                        <div class="text-xs text-slate-500 truncate" id="selectedDetails">—</div>
                    </div>
                    <button type="button" onclick="clearSelectedMember()" class="text-slate-400 hover:text-red-500 flex-shrink-0"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <input type="hidden" id="selectedMemberId">
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeAddMemberModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="addMemberToGroup()" class="btn btn-primary" id="addMemberBtn" disabled><i class="fa-solid fa-plus"></i> Add to Group</button>
        </div>
    </div>
</div>

<script>
let searchDebounce;
const currentGroupId = <?= $group['id'] ?>;

function openAddMemberModal() { 
    document.getElementById('addMemberModal').classList.remove('hidden'); 
    clearSelectedMember(); 
    setTimeout(() => document.getElementById('memberSearch').focus(), 100);
}

function closeAddMemberModal() { 
    document.getElementById('addMemberModal').classList.add('hidden'); 
}

function searchAvailableMembers(query) {
    clearTimeout(searchDebounce);
    if (query.length < 2) { 
        document.getElementById('memberSearchResults').classList.add('hidden'); 
        return; 
    }
    searchDebounce = setTimeout(async () => {
        try {
            const res = await fetch(`<?= APP_URL ?>/members/search?q=${encodeURIComponent(query)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            const box = document.getElementById('memberSearchResults');
            
            if (!data.data?.length) { 
                box.innerHTML = '<div class="px-4 py-3 text-sm text-slate-400 text-center">No members found</div>'; 
            } else {
                box.innerHTML = data.data.map(m => {
                    const isInThisGroup = m.group_id == currentGroupId;
                    const isDisabled = isInThisGroup ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-slate-50';
                    const statusBadge = isInThisGroup 
                        ? '<span class="text-xs text-amber-600 font-semibold bg-amber-50 px-2 py-0.5 rounded">Already in this group</span>' 
                        : (m.group_id ? '<span class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded">In another group</span>' : '<span class="text-xs text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded">Available</span>');
                    
                    return `
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-100 last:border-0 ${isDisabled}" 
                         ${!isInThisGroup ? `onclick="selectMember(${JSON.stringify(m).replace(/"/g,'&quot;')})"` : ''}>
                        <div class="avatar-circle w-8 h-8 text-xs flex-shrink-0">${m.initials}</div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-slate-800 truncate">${m.full_name}</div>
                            <div class="text-xs text-slate-400 truncate">${m.member_no} · ${m.phone}</div>
                        </div>
                        <div class="flex-shrink-0">${statusBadge}</div>
                    </div>`;
                }).join('');
            }
            box.classList.remove('hidden');
        } catch(e) { console.error(e); }
    }, 300);
}

function selectMember(m) {
    document.getElementById('selectedMemberId').value = m.id;
    document.getElementById('selectedName').textContent = m.full_name;
    document.getElementById('selectedDetails').textContent = `${m.member_no} · ${m.phone}`;
    document.getElementById('selectedAvatar').textContent = m.initials;
    document.getElementById('selectedMemberInfo').classList.remove('hidden');
    document.getElementById('memberSearchResults').classList.add('hidden');
    document.getElementById('addMemberBtn').disabled = false;
}

function clearSelectedMember() {
    document.getElementById('selectedMemberId').value = '';
    document.getElementById('selectedMemberInfo').classList.add('hidden');
    document.getElementById('addMemberBtn').disabled = true;
    document.getElementById('memberSearch').value = '';
    document.getElementById('memberSearchResults').classList.add('hidden');
}

async function addMemberToGroup() {
    const memberId = document.getElementById('selectedMemberId').value;
    if (!memberId) return;
    
    const btn = document.getElementById('addMemberBtn');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Adding...';
    
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('member_id', memberId);
    
    try {
        const r = await fetch(`<?= APP_URL ?>/groups/${currentGroupId}/add-member`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        const d = await r.json();
        if (d.success) { 
            window.showToast('success', d.message); 
            setTimeout(() => location.reload(), 900); 
        } else { 
            window.showToast('error', d.message); 
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    } catch(e) { 
        window.showToast('error', 'Request failed'); 
        btn.disabled = false;
        btn.innerHTML = origText;
    }
}

async function removeMemberFromGroup(memberId, memberName) {
    window.confirmAction({
        title: 'Remove Member',
        message: `Are you sure you want to remove "${memberName}" from this group? They will no longer be associated with this group's financial summaries.`,
        confirmText: 'Remove',
        type: 'danger',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
            fd.append('member_id', memberId);
            
            try {
                const r = await fetch(`<?= APP_URL ?>/groups/${currentGroupId}/remove-member`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
                const d = await r.json();
                if (d.success) { 
                    window.showToast('success', d.message); 
                    setTimeout(() => location.reload(), 900); 
                } else { 
                    window.showToast('error', d.message); 
                }
            } catch(e) { 
                window.showToast('error', 'Request failed'); 
            }
        }
    });
}

// Close search results when clicking outside
document.addEventListener('click', function(e) {
    const searchContainer = document.querySelector('.relative');
    if (searchContainer && !searchContainer.contains(e.target)) {
        document.getElementById('memberSearchResults').classList.add('hidden');
    }
});
</script>