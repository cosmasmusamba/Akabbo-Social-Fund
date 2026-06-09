<?php $pageTitle='New Fee Configuration'; $activePage='social-fund'; $breadcrumbs=['Social Fund Fees'=>APP_URL.'/social-fund','New'=>null]; ?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-5">
    <h1 class="text-xl font-bold text-slate-800">Configure New Fee</h1>
    <a href="<?= APP_URL ?>/social-fund" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
  </div>
  <div class="card"><div class="card-body">
    <form id="feeForm">
      <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
      <div class="space-y-4">
        <div><label class="form-label required">Fee Name</label><input type="text" name="name" class="form-control" value="Monthly Social Fund Fee" required></div>
        <div class="grid grid-cols-2 gap-4">
          <div><label class="form-label required">Amount (<?= $settings['currency_symbol']??'USh' ?>)</label><input type="number" name="amount" class="form-control" min="0" step="500" placeholder="5000" required></div>
          <div><label class="form-label required">Frequency</label>
            <select name="frequency" class="form-control form-select" required>
              <option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="annually">Annually</option><option value="once">One-time</option>
            </select>
          </div>
          <div><label class="form-label required">Due Day of Month</label><input type="number" name="due_day" class="form-control" min="1" max="28" value="1" required></div>
          <div><label class="form-label">Grace Period (days)</label><input type="number" name="grace_days" class="form-control" min="0" max="30" value="5"></div>
          <div><label class="form-label">Late Penalty Amount</label><input type="number" name="penalty_amount" class="form-control" min="0" step="100" value="1000"></div>
          <div><label class="form-label">Applies To</label>
            <select name="applies_to" class="form-control form-select">
              <option value="all">All Members</option><option value="shareholders">Shareholders Only</option><option value="non_shareholders">Non-Shareholders Only</option>
            </select>
          </div>
          <div><label class="form-label required">Effective From</label><input type="date" name="effective_from" class="form-control" value="<?= date('Y-m-01') ?>" required></div>
          <div><label class="form-label">Effective To (optional)</label><input type="date" name="effective_to" class="form-control"></div>
        </div>
        <div class="flex items-center gap-3">
          <input type="checkbox" name="is_mandatory" id="mandatory" value="1" checked class="w-4 h-4" style="accent-color:var(--green-mid)">
          <label for="mandatory" class="text-sm font-semibold text-slate-700 cursor-pointer">Mandatory fee (cannot be skipped)</label>
        </div>
        <div><label class="form-label required">Status</label>
          <select name="status" class="form-control form-select w-auto">
            <option value="active">Active</option><option value="inactive">Inactive (draft)</option>
          </select>
          <p class="text-xs text-slate-400 mt-1">Setting to Active will deactivate all other fee configurations.</p>
        </div>
      </div>
      <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-slate-100">
        <a href="<?= APP_URL ?>/social-fund" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Configuration</button>
      </div>
    </form>
  </div></div>
</div>
<script>
document.getElementById('feeForm').addEventListener('submit',async function(e){
  e.preventDefault();
  await window.submitForm(this,{loadingText:'Saving…',onSuccess:r=>{window.showToast('success',r.message);setTimeout(()=>{window.location.href=r.data?.redirect||'<?= APP_URL ?>/social-fund';},800);}});
});
</script>
