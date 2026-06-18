<?php
use App\Helpers\Format;
$pageTitle   = 'Access Control';
$activePage  = 'access-control';
$breadcrumbs = ['Administration' => APP_URL.'/users', 'Access Control' => null];
?>
<style>
.ac-tab-btn { padding: 10px 20px; font-weight: 600; font-size: 0.9rem; border-bottom: 2px solid transparent; color: #64748b; transition: all 0.2s; }
.ac-tab-btn.active { color: var(--green-mid, #10b981); border-bottom-color: var(--green-mid, #10b981); }
.ac-tab-btn:hover { color: var(--green-mid, #10b981); }
.perm-action-btn { padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; transition: all 0.2s; }
.perm-action-btn:hover { background: #f1f5f9; }
/* Custom scrollbar for permission lists */
.custom-scroll::-webkit-scrollbar { width: 6px; }
.custom-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 3px; }
.custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
.custom-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Access Control</h1>
        <p class="text-sm text-slate-400 mt-0.5">Manage permissions, roles, and user overrides</p>
    </div>
    <div class="flex gap-2">
        <button onclick="openPermissionModal()" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> New Permission
        </button>
        <button onclick="openRoleModal()" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-user-shield"></i> New Role
        </button>
    </div>
</div>

<!-- Top Level Tabs -->
<div class="card mb-5">
    <div class="card-body pb-0">
        <div class="flex border-b border-slate-200">
            <button onclick="switchMainTab('permissions')" class="ac-tab-btn active" id="tab-btn-permissions">
                <i class="fa-solid fa-key mr-1.5"></i> Permissions
            </button>
            <button onclick="switchMainTab('roles')" class="ac-tab-btn" id="tab-btn-roles">
                <i class="fa-solid fa-users-gear mr-1.5"></i> Roles
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     PERMISSIONS TAB
     ═══════════════════════════════════════════════════════════════ -->
<div id="tab-content-permissions">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-5">
        <!-- Left: User Selector -->
        <div class="lg:col-span-1">
            <div class="card">
                <div class="card-header"><h3 class="section-title">Select User</h3></div>
                <div class="p-2 max-h-[600px] overflow-y-auto custom-scroll">
                    <input type="text" id="userSearch" placeholder="Search users..." class="form-control py-2 text-sm mb-2">
                    <div id="userList" class="space-y-1">
                        <?php foreach ($users as $u): ?>
                        <button type="button"
                                onclick="loadUserPermissions(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['role_name'], ENT_QUOTES) ?>')"
                                class="user-btn w-full text-left px-3 py-2 rounded-lg text-sm hover:bg-slate-50 transition-colors flex items-center gap-3"
                                data-name="<?= strtolower($u['name']) ?>">
                            <div class="avatar-circle w-8 h-8 text-xs flex items-center justify-center bg-slate-200 text-slate-600 rounded-full font-bold"><?= Format::initials($u['name']) ?></div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-slate-800 truncate"><?= htmlspecialchars($u['name']) ?></div>
                                <div class="text-xs text-slate-400 truncate"><?= htmlspecialchars($u['role_name']) ?></div>
                            </div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Permission Matrix (Enhanced Grid) -->
        <div class="lg:col-span-3">
            <div class="card" id="permissionPanel" style="display: none;">
                <div class="card-header flex items-center justify-between">
                    <div>
                        <h3 class="section-title" id="selectedUserName">User Permissions</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Role: <span id="selectedUserRole" class="font-semibold text-slate-600"></span></p>
                    </div>
                    <button type="button" onclick="saveUserPermissions()" class="btn btn-primary btn-sm" id="saveBtn">
                        <i class="fa-solid fa-save"></i> Save Changes
                    </button>
                </div>
                <div class="card-body">
                    <!-- Search and Filter Controls -->
                    <div class="mb-4 flex flex-col sm:flex-row gap-3">
                        <div class="flex-1">
                            <div class="relative">
                                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" id="permSearchInput" placeholder="Search permissions (e.g., 'loans view', 'members')..." 
                                       class="form-control pl-10 py-2" oninput="filterPermissionsGrid()">
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="bulkSetPermission('allow')" class="btn btn-success btn-sm">
                                <i class="fa-solid fa-check"></i> Allow All
                            </button>
                            <button onclick="bulkSetPermission('deny')" class="btn btn-danger btn-sm">
                                <i class="fa-solid fa-ban"></i> Deny All
                            </button>
                            <button onclick="bulkSetPermission('inherit')" class="btn btn-secondary btn-sm">
                                <i class="fa-solid fa-rotate-left"></i> Reset
                            </button>
                        </div>
                    </div>

                    <!-- Permissions Grid -->
                    <div id="permissionsGrid" class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-[600px] overflow-y-auto custom-scroll p-1">
                        <!-- Populated by JavaScript -->
                    </div>

                    <!-- Stats Footer -->
                    <div class="mt-4 pt-3 border-t border-slate-200 flex items-center justify-between text-sm flex-wrap gap-2">
                        <div class="flex gap-4">
                            <span class="text-slate-600"><i class="fa-solid fa-check-circle text-emerald-500 mr-1"></i> <span id="countAllowed">0</span> Allowed</span>
                            <span class="text-slate-600"><i class="fa-solid fa-ban text-red-500 mr-1"></i> <span id="countDenied">0</span> Denied</span>
                            <span class="text-slate-600"><i class="fa-solid fa-circle text-slate-300 mr-1"></i> <span id="countInherit">0</span> Inherit</span>
                        </div>
                        <div class="text-slate-400">
                            Showing <span id="countVisible">0</span> of <span id="countTotal">0</span> permissions
                        </div>
                    </div>
                </div>
            </div>

            <div class="card text-center py-12" id="emptyState">
                <i class="fa-solid fa-shield-halved text-5xl text-slate-200 block mb-3"></i>
                <p class="text-slate-500 font-semibold">Select a user from the left to manage their access control</p>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     ROLES TAB
     ═══════════════════════════════════════════════════════════════ -->
<div id="tab-content-roles" style="display: none;">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($roles as $role): ?>
        <div class="card hover:shadow-md transition-all">
            <div class="card-body">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <div class="font-bold text-slate-800 text-base"><?= htmlspecialchars($role['name']) ?></div>
                        <div class="text-xs font-mono text-slate-400 mt-0.5"><?= htmlspecialchars($role['slug']) ?></div>
                    </div>
                    <?php if ($role['is_system']): ?>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">System</span>
                    <?php endif; ?>
                </div>
                <?php if ($role['description']): ?>
                <p class="text-xs text-slate-500 mb-4 leading-relaxed"><?= htmlspecialchars($role['description']) ?></p>
                <?php endif; ?>
                <div class="flex items-center gap-2 text-xs text-slate-400 mb-4">
                    <i class="fa-solid fa-key"></i> <?= $role['perm_count'] ?> permissions assigned
                </div>
                <div class="flex gap-2">
                    <button onclick="openRoleModal(<?= $role['id'] ?>, '<?= htmlspecialchars($role['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($role['slug'], ENT_QUOTES) ?>', '<?= htmlspecialchars($role['description'], ENT_QUOTES) ?>')"
                            class="btn btn-secondary btn-sm flex-1 justify-center">
                        <i class="fa-solid fa-pen text-xs"></i> Edit
                    </button>
                    <?php if (!$role['is_system']): ?>
                    <button onclick="deleteRole(<?= $role['id'] ?>, '<?= htmlspecialchars($role['name'], ENT_QUOTES) ?>')"
                            class="btn btn-danger btn-sm px-3">
                        <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODALS
     ═══════════════════════════════════════════════════════════════ -->

<!-- Permission Modal (Create/Edit) -->
<div id="permissionModal" class="hidden modal-overlay">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h3 class="font-bold text-slate-800" id="permModalTitle">Create Permission</h3>
            <button onclick="closePermissionModal()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="modal-body space-y-4">
            <form id="permissionForm">
                <input type="hidden" name="permission_id" id="perm_id" value="0">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label required">Module</label>
                        <input type="text" name="module" id="perm_module" class="form-control" placeholder="e.g. members" required>
                    </div>
                    <div>
                        <label class="form-label required">Action</label>
                        <input type="text" name="action" id="perm_action" class="form-control" placeholder="e.g. view" required>
                    </div>
                </div>
                <div>
                    <label class="form-label required">Slug</label>
                    <input type="text" name="slug" id="perm_slug" class="form-control" placeholder="e.g. members.view" required>
                </div>
                <div>
                    <label class="form-label required">Description</label>
                    <textarea name="description" id="perm_description" rows="2" class="form-control" placeholder="What does this permission do?" required></textarea>
                </div>
                <div>
                    <label class="form-label">Scope</label>
                    <select name="scope" id="perm_scope" class="form-control form-select">
                        <option value="global">Global - Apply everywhere</option>
                        <option value="own">Own - Only own records</option>
                        <option value="group">Group - Group members only</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closePermissionModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="savePermission()" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Permission</button>
        </div>
    </div>
</div>

<!-- Role Modal (Create/Edit) - ENHANCED WITH PERMISSIONS GRID -->
<div id="roleModal" class="hidden modal-overlay">
    <div class="modal" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="font-bold text-slate-800" id="roleModalTitle">Create Role</h3>
            <button onclick="closeRoleModal()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="modal-body space-y-4">
            <form id="roleForm">
                <input type="hidden" name="role_id" id="role_id" value="0">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                <input type="hidden" name="permission_ids" id="role_permission_ids" value="[]">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label required">Role Name</label>
                        <input type="text" name="name" id="role_name" class="form-control" placeholder="e.g. Finance Manager" required>
                    </div>
                    <div>
                        <label class="form-label required">Slug</label>
                        <input type="text" name="slug" id="role_slug" class="form-control" placeholder="e.g. finance_manager" required>
                        <p class="text-xs text-slate-400 mt-1">Lowercase, underscores only</p>
                    </div>
                </div>
                <div>
                    <label class="form-label">Description</label>
                    <textarea name="description" id="role_description" rows="2" class="form-control" placeholder="Brief description of this role…"></textarea>
                </div>

                <!-- Permissions Selection Section -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="form-label mb-0">Permissions</label>
                        <div class="flex gap-3 text-xs font-semibold">
                            <button type="button" onclick="toggleAllRolePerms(true)" class="text-blue-600 hover:underline">Select All</button>
                            <button type="button" onclick="toggleAllRolePerms(false)" class="text-red-600 hover:underline">Deselect All</button>
                        </div>
                    </div>
                    <input type="text" id="rolePermSearch" placeholder="Search permissions (e.g., 'loans view')..." class="form-control py-2 text-sm mb-2">
                    <div id="rolePermissionsList" class="max-h-64 overflow-y-auto custom-scroll border border-slate-200 rounded-lg p-3 bg-slate-50 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1">
                        <!-- Populated dynamically by JS -->
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeRoleModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="saveRole()" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Role</button>
        </div>
    </div>
</div>

<script>
// ── Global State ────────────────────────────────────────────────
let CURRENT_USER_ID = null;
let CURRENT_ROLE_PERMS = [];
let CURRENT_USER_PERMS = {};
let CURRENT_ROLE_PERMS_TO_EDIT = [];
const ALL_PERMISSIONS = <?= json_encode($permissions) ?>;

// ── Tab Switching ───────────────────────────────────────────────
function switchMainTab(tab) {
    document.getElementById('tab-content-permissions').style.display = tab === 'permissions' ? 'block' : 'none';
    document.getElementById('tab-content-roles').style.display = tab === 'roles' ? 'block' : 'none';
    document.getElementById('tab-btn-permissions').classList.toggle('active', tab === 'permissions');
    document.getElementById('tab-btn-roles').classList.toggle('active', tab === 'roles');
}

// ═══════════════════════════════════════════════════════════════
// USER PERMISSIONS LOGIC
// ═══════════════════════════════════════════════════════════════
document.getElementById('userSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('.user-btn').forEach(btn => {
        btn.style.display = btn.dataset.name.includes(query) ? 'flex' : 'none';
    });
});

async function loadUserPermissions(userId, userName, roleName) {
    CURRENT_USER_ID = userId;
    document.getElementById('emptyState').style.display = 'none';
    document.getElementById('permissionPanel').style.display = 'block';
    document.getElementById('selectedUserName').textContent = userName;
    document.getElementById('selectedUserRole').textContent = roleName;
    
    document.querySelectorAll('.user-btn').forEach(btn => btn.classList.remove('bg-blue-50', 'border', 'border-blue-200'));
    const activeBtn = document.querySelector(`.user-btn[onclick*="${userId}"]`);
    if (activeBtn) activeBtn.classList.add('bg-blue-50', 'border', 'border-blue-200');
    
    document.getElementById('permissionsGrid').innerHTML = '<div class="col-span-full text-center py-8"><i class="fa-solid fa-spinner fa-spin text-slate-400 text-2xl"></i><p class="text-slate-500 mt-2">Loading permissions...</p></div>';
    
    try {
        const res = await fetch(`<?= APP_URL ?>/access-control/user/${userId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (data.success) {
            CURRENT_ROLE_PERMS = data.data.role_permissions || [];
            CURRENT_USER_PERMS = data.data.user_permissions || {};
            document.getElementById('permSearchInput').value = ''; // Clear search on new user load
            renderPermissionsGrid();
            updatePermissionStats();
        }
    } catch (e) { window.showToast('error', 'Failed to load permissions'); }
}

function getEffectivePermission(perm) {
    const userSetting = CURRENT_USER_PERMS[perm.slug];
    if (userSetting === true) return { status: 'allow', label: 'Allowed (Override)', class: 'bg-emerald-100 text-emerald-700 border-emerald-200' };
    if (userSetting === false) return { status: 'deny', label: 'Denied (Revoked)', class: 'bg-red-100 text-red-700 border-red-200' };
    const hasRolePerm = CURRENT_ROLE_PERMS.includes(perm.slug);
    if (hasRolePerm) return { status: 'inherit-allow', label: 'Inherit (Allowed)', class: 'bg-blue-50 text-blue-700 border-blue-200' };
    return { status: 'inherit-deny', label: 'Inherit (Denied)', class: 'bg-slate-100 text-slate-600 border-slate-200' };
}

function renderPermissionsGrid(searchQuery = '') {
    const grid = document.getElementById('permissionsGrid');
    grid.innerHTML = '';
    const query = searchQuery.toLowerCase();
    let visibleCount = 0;
    
    ALL_PERMISSIONS.forEach(perm => {
        const searchText = `${perm.module} ${perm.action} ${perm.slug} ${perm.description || ''}`.toLowerCase();
        if (query && !searchText.includes(query)) return;
        
        visibleCount++;
        const effective = getEffectivePermission(perm);
        const userSetting = CURRENT_USER_PERMS[perm.slug];
        
        const card = document.createElement('div');
        card.className = 'border rounded-lg p-3 hover:shadow-md transition-all bg-white';
        card.innerHTML = `
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="font-bold text-sm text-slate-800">${perm.module}</span>
                        <span class="text-xs text-slate-400">•</span>
                        <span class="font-semibold text-xs text-slate-600 uppercase">${perm.action.replace(/_/g, ' ')}</span>
                    </div>
                    <div class="text-[10px] font-mono text-slate-400 truncate">${perm.slug}</div>
                </div>
                <div class="flex gap-1">
                    <button onclick="openPermissionModal(${perm.id})" 
                            class="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition-colors" title="Edit Permission">
                        <i class="fa-solid fa-pen-to-square text-xs"></i>
                    </button>
                    <button onclick="deletePermission(${perm.id})" 
                            class="p-1.5 text-red-600 hover:bg-red-50 rounded transition-colors" title="Delete Permission">
                        <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                </div>
            </div>
            ${perm.description ? `<p class="text-xs text-slate-500 mb-2 line-clamp-2">${perm.description}</p>` : ''}
            <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">${perm.scope}</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded border ${effective.class}">${effective.label}</span>
                </div>
                <select onchange="updatePermission('${perm.slug}', this.value)" 
                        class="text-xs border border-slate-300 rounded px-2 py-1 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="inherit" ${userSetting === undefined ? 'selected' : ''}>Inherit</option>
                    <option value="allow" ${userSetting === true ? 'selected' : ''}>Allow</option>
                    <option value="deny" ${userSetting === false ? 'selected' : ''}>Deny</option>
                </select>
            </div>
        `;
        grid.appendChild(card);
    });
    
    document.getElementById('countVisible').textContent = visibleCount;
    document.getElementById('countTotal').textContent = ALL_PERMISSIONS.length;
    updatePermissionStats();
}

function filterPermissionsGrid() {
    const query = document.getElementById('permSearchInput').value;
    renderPermissionsGrid(query);
}

function updatePermissionStats() {
    let allowed = 0, denied = 0, inherit = 0;
    ALL_PERMISSIONS.forEach(perm => {
        const userSetting = CURRENT_USER_PERMS[perm.slug];
        if (userSetting === true) allowed++;
        else if (userSetting === false) denied++;
        else inherit++;
    });
    document.getElementById('countAllowed').textContent = allowed;
    document.getElementById('countDenied').textContent = denied;
    document.getElementById('countInherit').textContent = inherit;
}

function updatePermission(slug, value) {
    if (value === 'inherit') delete CURRENT_USER_PERMS[slug];
    else CURRENT_USER_PERMS[slug] = (value === 'allow');
    renderPermissionsGrid(document.getElementById('permSearchInput').value);
}

function bulkSetPermission(action) {
    if (!confirm(`Are you sure you want to ${action} all visible permissions? This will override role defaults.`)) return;
    
    const query = document.getElementById('permSearchInput').value.toLowerCase();
    ALL_PERMISSIONS.forEach(perm => {
        const searchText = `${perm.module} ${perm.action} ${perm.slug} ${perm.description || ''}`.toLowerCase();
        if (!query || searchText.includes(query)) {
            if (action === 'inherit') delete CURRENT_USER_PERMS[perm.slug];
            else CURRENT_USER_PERMS[perm.slug] = (action === 'allow');
        }
    });
    renderPermissionsGrid(query);
    window.showToast('success', `Permissions ${action === 'inherit' ? 'reset' : 'set to ' + action} successfully`);
}

async function saveUserPermissions() {
    if (!CURRENT_USER_ID) return;
    const btn = document.getElementById('saveBtn');
    const origText = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    
    const fd = new FormData();
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);
    fd.append('user_id', CURRENT_USER_ID);
    fd.append('permissions', JSON.stringify(CURRENT_USER_PERMS));
    
    try {
        const r = await fetch('<?= APP_URL ?>/access-control/update', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        const d = await r.json();
        window.showToast(d.success ? 'success' : 'error', d.message);
    } catch (e) { window.showToast('error', 'Network error'); }
    finally { btn.disabled = false; btn.innerHTML = origText; }
}

// ═══════════════════════════════════════════════════════════════
// ROLE CRUD LOGIC (ENHANCED)
// ═══════════════════════════════════════════════════════════════
function renderRolePermissionsList(searchQuery = '') {
    const container = document.getElementById('rolePermissionsList');
    if (!container) return;
    container.innerHTML = '';
    const query = searchQuery.toLowerCase();
    
    ALL_PERMISSIONS.forEach(perm => {
        const label = `${perm.module} - ${perm.action.replace(/_/g, ' ')}`;
        if (query && !label.toLowerCase().includes(query) && !perm.slug.toLowerCase().includes(query)) {
            return;
        }
        
        const isChecked = CURRENT_ROLE_PERMS_TO_EDIT.includes(perm.id) ? 'checked' : '';
        const div = document.createElement('div');
        div.className = 'flex items-center gap-2 p-2 hover:bg-slate-100 rounded transition-colors';
        div.innerHTML = `
            <input type="checkbox" id="role_perm_${perm.id}" value="${perm.id}" class="role-perm-checkbox rounded text-blue-600 focus:ring-blue-500 border-slate-300" ${isChecked} onchange="updateRolePermIds()">
            <label for="role_perm_${perm.id}" class="text-sm text-slate-700 cursor-pointer flex-1 truncate" title="${perm.description || perm.slug}">
                <span class="font-semibold text-slate-800">${perm.module}</span>: ${perm.action.replace(/_/g, ' ')}
            </label>
        `;
        container.appendChild(div);
    });
}

function updateRolePermIds() {
    const checkboxes = document.querySelectorAll('.role-perm-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    document.getElementById('role_permission_ids').value = JSON.stringify(ids);
}

function toggleAllRolePerms(shouldCheck) {
    document.querySelectorAll('.role-perm-checkbox').forEach(cb => {
        cb.checked = shouldCheck;
    });
    updateRolePermIds();
}

document.getElementById('rolePermSearch').addEventListener('input', function() {
    renderRolePermissionsList(this.value);
});

function openRoleModal(id=0, name='', slug='', description='') {
    document.getElementById('roleModalTitle').textContent = id > 0 ? 'Edit Role' : 'Create Role';
    document.getElementById('role_id').value = id;
    document.getElementById('role_name').value = name;
    document.getElementById('role_slug').value = slug;
    document.getElementById('role_description').value = description;
    document.getElementById('rolePermSearch').value = ''; // Clear search
    
    if (id > 0) {
        // Fetch existing permissions for this role to pre-check them
        fetch(`<?= APP_URL ?>/access-control/role/${id}/permissions`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    CURRENT_ROLE_PERMS_TO_EDIT = data.data.permissions || [];
                    renderRolePermissionsList();
                    updateRolePermIds();
                }
            })
            .catch(() => {
                window.showToast('error', 'Failed to load role permissions');
            });
    } else {
        CURRENT_ROLE_PERMS_TO_EDIT = [];
        renderRolePermissionsList();
        updateRolePermIds();
    }
    
    document.getElementById('roleModal').classList.remove('hidden');
}

function closeRoleModal() { 
    document.getElementById('roleModal').classList.add('hidden'); 
}

async function saveRole() {
    const fd = new FormData(document.getElementById('roleForm'));
    const btn = document.querySelector('#roleModal .btn-primary');
    const origText = btn.innerHTML;
    btn.disabled = true; 
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    
    try {
        const r = await fetch('<?= APP_URL ?>/access-control/save-role', { 
            method: 'POST', 
            headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
            body: fd 
        });
        const d = await r.json();
        if (d.success) { 
            window.showToast('success', d.message); 
            closeRoleModal(); 
            setTimeout(() => location.reload(), 800); 
        } else {
            window.showToast('error', d.message);
        }
    } catch (e) { 
        window.showToast('error', 'Request failed'); 
    } finally {
        btn.disabled = false; 
        btn.innerHTML = origText;
    }
}

async function deleteRole(id, name) {
    window.confirmAction({
        title: 'Delete Role', message: `Delete role "${name}"? Users assigned to this role will need a new role.`, confirmText: 'Delete', type: 'danger',
        onConfirm: async () => {
            const fd = new FormData(); 
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content); 
            fd.append('role_id', id);
            const r = await fetch('<?= APP_URL ?>/access-control/delete-role', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            const d = await r.json();
            if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 800); }
            else window.showToast('error', d.message);
        }
    });
}

// ═══════════════════════════════════════════════════════════════
// PERMISSION CRUD LOGIC
// ═══════════════════════════════════════════════════════════════
function openPermissionModal(id) {
    const perm = ALL_PERMISSIONS.find(p => p.id == id);
    if (!perm && id > 0) return;
    
    document.getElementById('permModalTitle').textContent = id > 0 ? 'Edit Permission' : 'Create Permission';
    document.getElementById('perm_id').value = id;
    
    if (perm) {
        document.getElementById('perm_module').value = perm.module;
        document.getElementById('perm_action').value = perm.action;
        document.getElementById('perm_slug').value = perm.slug;
        document.getElementById('perm_description').value = perm.description || '';
        document.getElementById('perm_scope').value = perm.scope;
    } else {
        document.getElementById('perm_module').value = '';
        document.getElementById('perm_action').value = '';
        document.getElementById('perm_slug').value = '';
        document.getElementById('perm_description').value = '';
        document.getElementById('perm_scope').value = 'global';
    }
    
    document.getElementById('permissionModal').classList.remove('hidden');
}

function closePermissionModal() { document.getElementById('permissionModal').classList.add('hidden'); }

async function savePermission() {
    const fd = new FormData(document.getElementById('permissionForm'));
    try {
        const r = await fetch('<?= APP_URL ?>/access-control/save-permission', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
        const d = await r.json();
        if (d.success) { window.showToast('success', d.message); closePermissionModal(); setTimeout(() => location.reload(), 800); }
        else window.showToast('error', d.message);
    } catch (e) { window.showToast('error', 'Request failed'); }
}

function deletePermission(id) {
    const perm = ALL_PERMISSIONS.find(p => p.id == id);
    const slug = perm ? perm.slug : 'this permission';
    
    window.confirmAction({
        title: 'Delete Permission', message: `Are you sure you want to delete "${slug}"?`, confirmText: 'Delete', type: 'danger',
        onConfirm: async () => {
            const fd = new FormData(); 
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content); 
            fd.append('permission_id', id);
            const r = await fetch('<?= APP_URL ?>/access-control/delete-permission', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
            const d = await r.json();
            if (d.success) { window.showToast('success', d.message); setTimeout(() => location.reload(), 800); }
            else window.showToast('error', d.message);
        }
    });
}
</script>