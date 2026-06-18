<?php
$pageTitle   = $pageTitle ?? 'New Fee Configuration';
$activePage  = $activePage ?? 'social-fund';
$breadcrumbs = $breadcrumbs ?? ['Social Fund Fees' => APP_URL . '/social-fund', 'New' => null];
?>

<div class="max-w-2xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Configure New Fee</h1>
            <p class="text-sm text-slate-400 mt-0.5">Set up a periodic fee for members</p>
        </div>
        <a href="<?= APP_URL ?>/social-fund" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="feeForm">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                
                <div class="space-y-5">
                    <!-- Basic Info -->
                    <div>
                        <div class="form-section-label mb-3" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--green-mid)">
                            <i class="fa-solid fa-info-circle mr-1"></i> Fee Details
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="form-label required" for="name">Fee Name</label>
                                <input type="text" id="name" name="name" class="form-control"
                                       placeholder="e.g. Monthly Social Fund Fee" required>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label required" for="amount">Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                                    <input type="number" id="amount" name="amount" class="form-control"
                                           placeholder="5000" min="0" step="100" required>
                                </div>
                                <div>
                                    <label class="form-label required" for="frequency">Frequency</label>
                                    <select id="frequency" name="frequency" class="form-control form-select" required>
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Quarterly</option>
                                        <option value="annually">Annually</option>
                                        <option value="once">One-time</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rules -->
                    <div>
                        <div class="form-section-label mb-3" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--green-mid)">
                            <i class="fa-solid fa-scale-balanced mr-1"></i> Rules & Applicability
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="form-label required" for="due_day">Due Day of Month</label>
                                <input type="number" id="due_day" name="due_day" class="form-control"
                                       value="1" min="1" max="28" required>
                                <p class="text-xs text-slate-400 mt-1">Day 1–28 (avoids month-end issues)</p>
                            </div>
                            <div>
                                <label class="form-label" for="grace_days">Grace Period (days)</label>
                                <input type="number" id="grace_days" name="grace_days" class="form-control"
                                       value="5" min="0" max="30">
                            </div>
                            <div>
                                <label class="form-label" for="penalty_amount">Late Penalty (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                                <input type="number" id="penalty_amount" name="penalty_amount" class="form-control"
                                       value="0" min="0" step="100">
                                <p class="text-xs text-slate-400 mt-1">0 = no penalty</p>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label" for="applies_to">Applies To</label>
                                <select id="applies_to" name="applies_to" class="form-control form-select">
                                    <option value="all">All Members</option>
                                    <option value="shareholders">Shareholders Only</option>
                                    <option value="non_shareholders">Non-Shareholders Only</option>
                                </select>
                            </div>
                            <div class="flex items-end pb-2">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" name="is_mandatory" value="1" checked class="w-4 h-4 rounded" style="accent-color:var(--green-mid)">
                                    <div>
                                        <div class="text-sm font-semibold text-slate-700">Mandatory Fee</div>
                                        <div class="text-xs text-slate-400">Cannot be skipped without authorization</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Effective Dates -->
                    <div>
                        <div class="form-section-label mb-3" style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--green-mid)">
                            <i class="fa-solid fa-calendar mr-1"></i> Validity Period
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label required" for="effective_from">Effective From</label>
                                <input type="date" id="effective_from" name="effective_from" class="form-control"
                                       value="<?= date('Y-m-01') ?>" required>
                            </div>
                            <div>
                                <label class="form-label" for="effective_to">Effective To <span class="text-slate-400 font-normal">(optional)</span></label>
                                <input type="date" id="effective_to" name="effective_to" class="form-control">
                                <p class="text-xs text-slate-400 mt-1">Leave blank for open-ended</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-end pt-5 border-t border-slate-100 mt-5">
                    <a href="<?= APP_URL ?>/social-fund" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fa-solid fa-save"></i> Create Fee Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('feeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
    
    const formData = new FormData(this);
    try {
        const response = await fetch('<?= APP_URL ?>/social-fund/store', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            setTimeout(() => { window.location.href = data.data?.redirect || '<?= APP_URL ?>/social-fund'; }, 800);
        } else {
            window.showToast('error', data.message || 'Creation failed.');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        window.showToast('error', 'Network error. Check console.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});
</script>