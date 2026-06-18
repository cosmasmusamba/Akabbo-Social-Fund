<?php
use App\Helpers\Format;
use App\Helpers\Avatar;

$pageTitle = 'System Settings';
$activePage = 'settings';
$breadcrumbs = ['Settings' => null];

// Define tab order and icons
$tabConfig = [
    'general'      => ['label' => 'General',       'icon' => 'fa-building-columns'],
    'finance'      => ['label' => 'Finance',       'icon' => 'fa-coins'],
    'service_fees' => ['label' => 'Service Fees',  'icon' => 'fa-receipt'],
    'security'     => ['label' => 'Security',      'icon' => 'fa-shield-halved'],
    'notifications'=> ['label' => 'Notifications', 'icon' => 'fa-bell'],
    'backup'       => ['label' => 'Backup',        'icon' => 'fa-database'],
    'system'       => ['label' => 'System',        'icon' => 'fa-gear'],
];

$activeTab = $this->getQuery('tab', 'general');
if (!array_key_exists($activeTab, $tabConfig)) {
    $activeTab = 'general';
}
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">System Settings</h1>
        <p class="text-sm text-slate-400 mt-0.5">Manage organization details, financial rules, and system configuration</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Sidebar Tabs -->
    <div class="lg:col-span-1">
        <div class="card sticky top-20">
            <div class="card-body p-2">
                <nav class="flex flex-col gap-1">
                    <?php foreach ($tabConfig as $key => $cfg): ?>
                        <?php 
                        // Only show tab if there are settings for it, or if it's general/backup
                        $hasSettings = false;
                        foreach ($settings as $s) {
                            if (($s['group'] ?? '') === $key) {
                                $hasSettings = true;
                                break;
                            }
                        }
                        if (!$hasSettings && !in_array($key, ['general', 'backup'])) continue;
                        ?>
                        <a href="?tab=<?= $key ?>" 
                           class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors <?= $activeTab === $key ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'text-slate-600 hover:bg-slate-50' ?>">
                            <i class="fa-solid <?= $cfg['icon'] ?> w-5 text-center <?= $activeTab === $key ? 'text-emerald-600' : 'text-slate-400' ?>"></i>
                            <?= $cfg['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="lg:col-span-3 space-y-6">
        <form id="settingsForm" enctype="multipart/form-data">
            <?= $csrfField ?>
            <input type="hidden" name="tab" value="<?= $activeTab ?>">

            <?php if ($activeTab === 'general'): ?>
                <!-- GENERAL SETTINGS -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fa-solid fa-building-columns mr-2 text-emerald-600"></i> Organization Details</h3>
                    </div>
                    <div class="card-body space-y-5">
                        <!-- Logo Upload -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="form-label">Organization Logo</label>
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-16 rounded-lg border-2 border-dashed border-slate-200 flex items-center justify-center bg-slate-50 overflow-hidden">
                                        <?php if (!empty($settings['org_logo']['value'])): ?>
                                            <img src="<?= APP_URL ?>/storage/uploads/logos/<?= htmlspecialchars($settings['org_logo']['value']) ?>" class="w-full h-full object-contain" alt="Logo">
                                        <?php else: ?>
                                            <i class="fa-solid fa-image text-slate-300 text-xl"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <input type="file" name="logo_file" id="logoInput" accept="image/*" class="hidden">
                                        <button type="button" onclick="document.getElementById('logoInput').click()" class="btn btn-secondary btn-sm">
                                            <i class="fa-solid fa-upload"></i> Upload Logo
                                        </button>
                                        <p class="text-xs text-slate-400 mt-1">PNG, JPG up to 2MB</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Favicon</label>
                                <div class="flex items-center gap-4">
                                    <div class="w-10 h-10 rounded border-2 border-dashed border-slate-200 flex items-center justify-center bg-slate-50 overflow-hidden">
                                        <?php if (!empty($settings['org_favicon']['value'])): ?>
                                            <img src="<?= APP_URL ?>/storage/uploads/logos/<?= htmlspecialchars($settings['org_favicon']['value']) ?>" class="w-full h-full object-contain" alt="Favicon">
                                        <?php else: ?>
                                            <i class="fa-solid fa-image text-slate-300 text-xs"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <input type="file" name="favicon_file" id="faviconInput" accept="image/*" class="hidden">
                                        <button type="button" onclick="document.getElementById('faviconInput').click()" class="btn btn-secondary btn-sm">
                                            <i class="fa-solid fa-upload"></i> Upload Favicon
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="border-slate-100">

                        <?php foreach ($settings as $key => $s): if (($s['group'] ?? '') !== 'general') continue; ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></label>
                                    <input type="text" name="<?= $key ?>" value="<?= htmlspecialchars($s['value'] ?? '') ?>" class="form-control" placeholder="<?= htmlspecialchars($s['description'] ?? '') ?>">
                                    <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($s['description'] ?? '') ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($activeTab === 'finance'): ?>
                <!-- FINANCE SETTINGS -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fa-solid fa-coins mr-2 text-emerald-600"></i> Financial Rules & Limits</h3>
                    </div>
                    <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php foreach ($settings as $key => $s): if (($s['group'] ?? '') !== 'finance') continue; ?>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></label>
                                <?php if (($s['type'] ?? '') === 'boolean'): ?>
                                    <select name="<?= $key ?>" class="form-control form-select">
                                        <option value="true" <?= ($s['value'] ?? '') === 'true' ? 'selected' : '' ?>>Yes</option>
                                        <option value="false" <?= ($s['value'] ?? '') === 'false' ? 'selected' : '' ?>>No</option>
                                    </select>
                                <?php else: ?>
                                    <input type="number" step="any" name="<?= $key ?>" value="<?= htmlspecialchars($s['value'] ?? '') ?>" class="form-control">
                                <?php endif; ?>
                                <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($s['description'] ?? '') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($activeTab === 'service_fees'): ?>
                <!-- SERVICE FEES SETTINGS (COMPLIANCE SEC 15) -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fa-solid fa-receipt mr-2 text-emerald-600"></i> Member Service Charges</h3>
                        <p class="text-xs text-slate-400 mt-1">Configure fees for member-initiated enquiries. Admins and staff are automatically exempt.</p>
                    </div>
                    <div class="card-body space-y-8">
                        
                        <!-- Balance Inquiry -->
                        <div class="p-5 rounded-xl border border-slate-200 bg-slate-50/50">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-bold text-slate-800 flex items-center gap-2">
                                    <i class="fa-solid fa-wallet text-blue-500"></i> Balance Enquiry Fee
                                </h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <!-- Hidden input ensures 'false' is sent if checkbox is unchecked -->
                                    <input type="hidden" name="balance_inquiry_fee_required" value="false">
                                    <input type="checkbox" name="balance_inquiry_fee_required" value="true" class="sr-only peer" <?= ($settings['balance_inquiry_fee_required']['value'] ?? 'false') === 'true' ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="form-label">Fee Amount (USh)</label>
                                    <input type="number" name="balance_inquiry_fee_amount" value="<?= htmlspecialchars($settings['balance_inquiry_fee_amount']['value'] ?? '500') ?>" class="form-control">
                                </div>
                                <div>
                                    <label class="form-label">Free Limit</label>
                                    <input type="number" name="balance_inquiry_free_limit" value="<?= htmlspecialchars($settings['balance_inquiry_free_limit']['value'] ?? '3') ?>" class="form-control">
                                    <p class="text-xs text-slate-400 mt-1">Free enquiries per period</p>
                                </div>
                                <div>
                                    <label class="form-label">Reset Frequency</label>
                                    <select name="balance_inquiry_charge_frequency" class="form-control form-select">
                                        <option value="daily" <?= ($settings['balance_inquiry_charge_frequency']['value'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                                        <option value="monthly" <?= ($settings['balance_inquiry_charge_frequency']['value'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                        <option value="per_request" <?= ($settings['balance_inquiry_charge_frequency']['value'] ?? '') === 'per_request' ? 'selected' : '' ?>>Per Request</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Statement Request -->
                        <div class="p-5 rounded-xl border border-slate-200 bg-slate-50/50">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="font-bold text-slate-800 flex items-center gap-2">
                                    <i class="fa-solid fa-file-lines text-purple-500"></i> Statement Request Fee
                                </h4>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="statement_request_fee_required" value="false">
                                    <input type="checkbox" name="statement_request_fee_required" value="true" class="sr-only peer" <?= ($settings['statement_request_fee_required']['value'] ?? 'true') === 'true' ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                </label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="form-label">Fee Amount (USh)</label>
                                    <input type="number" name="statement_request_fee_amount" value="<?= htmlspecialchars($settings['statement_request_fee_amount']['value'] ?? '2000') ?>" class="form-control">
                                </div>
                                <div>
                                    <label class="form-label">Free Limit</label>
                                    <input type="number" name="statement_request_free_limit" value="<?= htmlspecialchars($settings['statement_request_free_limit']['value'] ?? '1') ?>" class="form-control">
                                    <p class="text-xs text-slate-400 mt-1">Free statements per period</p>
                                </div>
                                <div>
                                    <label class="form-label">Reset Frequency</label>
                                    <select name="statement_request_charge_frequency" class="form-control form-select">
                                        <option value="daily" <?= ($settings['statement_request_charge_frequency']['value'] ?? '') === 'daily' ? 'selected' : '' ?>>Daily</option>
                                        <option value="monthly" <?= ($settings['statement_request_charge_frequency']['value'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                        <option value="per_request" <?= ($settings['statement_request_charge_frequency']['value'] ?? '') === 'per_request' ? 'selected' : '' ?>>Per Request</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Debits Info Box -->
                        <div class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-sm">
                            <div class="flex items-start gap-3">
                                <i class="fa-solid fa-circle-info mt-1 text-blue-500"></i>
                                <div>
                                    <strong>How Pending Debits Work:</strong> If a member requests a statement or balance enquiry but lacks sufficient available balance to cover the fee (due to minimum savings or loan collateral rules), the system will still grant the request. The fee is recorded as a <strong>pending debit</strong> and will be automatically deducted from their next deposit.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab === 'security'): ?>
                <!-- SECURITY SETTINGS -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fa-solid fa-shield-halved mr-2 text-emerald-600"></i> Security & Access</h3>
                    </div>
                    <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php foreach ($settings as $key => $s): if (($s['group'] ?? '') !== 'security') continue; ?>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></label>
                                <input type="number" name="<?= $key ?>" value="<?= htmlspecialchars($s['value'] ?? '') ?>" class="form-control">
                                <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($s['description'] ?? '') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($activeTab === 'notifications'): ?>
                <!-- NOTIFICATION SETTINGS -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fa-solid fa-bell mr-2 text-emerald-600"></i> Notification Channels</h3>
                    </div>
                    <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php foreach ($settings as $key => $s): if (($s['group'] ?? '') !== 'notifications') continue; ?>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></label>
                                <select name="<?= $key ?>" class="form-control form-select">
                                    <option value="true" <?= ($s['value'] ?? '') === 'true' ? 'selected' : '' ?>>Enabled</option>
                                    <option value="false" <?= ($s['value'] ?? '') === 'false' ? 'selected' : '' ?>>Disabled</option>
                                </select>
                                <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($s['description'] ?? '') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            <?php elseif ($activeTab === 'backup'): ?>
                <!-- BACKUP SETTINGS -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fa-solid fa-database mr-2 text-emerald-600"></i> Database Backup</h3>
                    </div>
                    <div class="card-body">
                        <div class="p-6 rounded-xl border border-amber-200 bg-amber-50 flex items-start gap-4">
                            <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                                <i class="fa-solid fa-triangle-exclamation text-amber-600 text-xl"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-amber-800">Manual Database Backup</h4>
                                <p class="text-sm text-amber-700 mt-1">
                                    Clicking the button below will generate a full SQL dump of your database and save it to the server's <code>storage/backups/</code> directory. 
                                    Ensure your server has write permissions for this folder.
                                </p>
                                <button type="button" id="backupBtn" class="btn btn-gold mt-4">
                                    <i class="fa-solid fa-download"></i> Generate Backup Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab === 'system'): ?>
                <!-- SYSTEM SETTINGS -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fa-solid fa-gear mr-2 text-emerald-600"></i> System Information</h3>
                    </div>
                    <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-5">
                        <?php foreach ($settings as $key => $s): if (($s['group'] ?? '') !== 'system') continue; ?>
                            <div>
                                <label class="form-label"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></label>
                                <input type="text" name="<?= $key ?>" value="<?= htmlspecialchars($s['value'] ?? '') ?>" class="form-control bg-slate-100" readonly>
                                <p class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($s['description'] ?? '') ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Save Button (Not for Backup tab) -->
            <?php if ($activeTab !== 'backup'): ?>
                <div class="flex justify-end gap-3">
                    <a href="<?= APP_URL ?>/dashboard" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-check"></i> Save Changes
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
// Handle Standard Settings Save
document.getElementById('settingsForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

    const formData = new FormData(this);

    try {
        const r = await fetch('<?= APP_URL ?>/settings/update', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const d = await r.json();
        if (d.success) {
            window.showToast('success', d.message || 'Settings saved successfully!');
            setTimeout(() => location.reload(), 800);
        } else {
            window.showToast('error', d.message || 'Failed to save settings.');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});

// Handle Logo Upload
document.getElementById('logoInput')?.addEventListener('change', async function() {
    if (!this.files[0]) return;
    const formData = new FormData();
    formData.append('logo', this.files[0]);
    formData.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');

    try {
        const r = await fetch('<?= APP_URL ?>/settings/logo', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        const d = await r.json();
        if (d.success) {
            window.showToast('success', 'Logo uploaded successfully!');
            setTimeout(() => location.reload(), 800);
        } else {
            window.showToast('error', d.message || 'Upload failed.');
        }
    } catch (err) {
        window.showToast('error', 'Network error.');
    }
});

// Handle Favicon Upload
document.getElementById('faviconInput')?.addEventListener('change', async function() {
    if (!this.files[0]) return;
    const formData = new FormData();
    formData.append('favicon', this.files[0]);
    formData.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');

    try {
        const r = await fetch('<?= APP_URL ?>/settings/favicon', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        const d = await r.json();
        if (d.success) {
            window.showToast('success', 'Favicon uploaded successfully!');
            setTimeout(() => location.reload(), 800);
        } else {
            window.showToast('error', d.message || 'Upload failed.');
        }
    } catch (err) {
        window.showToast('error', 'Network error.');
    }
});

// Handle Manual Backup
document.getElementById('backupBtn')?.addEventListener('click', async function() {
    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating Backup...';

    const formData = new FormData();
    formData.append('csrf_token', '<?= \App\Helpers\Security::generateCsrfToken() ?>');

    try {
        const r = await fetch('<?= APP_URL ?>/settings/backup', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
        const d = await r.json();
        if (d.success) {
            window.showToast('success', d.message || 'Backup created successfully!');
        } else {
            window.showToast('error', d.message || 'Backup failed.');
        }
    } catch (err) {
        window.showToast('error', 'Network error.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
});
</script>