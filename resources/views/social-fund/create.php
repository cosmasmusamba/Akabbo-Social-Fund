<?php
$isEdit      = isset($fee);
$pageTitle   = $isEdit ? 'Edit Fee Configuration' : 'New Fee Configuration';
$activePage  = 'social-fund';
$breadcrumbs = ['Social Fund Fees' => APP_URL . '/social-fund', $isEdit ? 'Edit' : 'New' => null];
$action      = $isEdit
    ? APP_URL . '/social-fund/' . $fee['id'] . '/update'
    : APP_URL . '/social-fund/store';
?>
<div class="max-w-2xl mx-auto">
  <div class="flex items-center justify-between mb-5">
    <div>
      <h1 class="text-xl font-bold text-slate-800"><?= $isEdit ? 'Edit Fee Configuration' : 'Configure New Fee' ?></h1>
      <p class="text-sm text-slate-400 mt-0.5">
        <?= $isEdit ? htmlspecialchars($fee['name'] ?? '') : 'Set up a periodic fee for members' ?>
      </p>
    </div>
    <a href="<?= APP_URL ?>/social-fund" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-arrow-left"></i> Back
    </a>
  </div>

  <div class="card">
    <div class="card-body">
      <form id="feeForm">
        <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">

        <div class="space-y-4">

          <!-- Name -->
          <div>
            <label class="form-label required">Fee Name</label>
            <input type="text" name="name" class="form-control"
                   value="<?= htmlspecialchars($fee['name'] ?? 'Monthly Social Fund Fee') ?>" required>
          </div>

          <!-- Amount & Frequency -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label required">Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
              <input type="number" name="amount" class="form-control"
                     value="<?= $fee['amount'] ?? '' ?>"
                     min="0" step="500" placeholder="5000" required>
            </div>
            <div>
              <label class="form-label required">Frequency</label>
              <select name="frequency" class="form-control form-select" required>
                <?php foreach (['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annually' => 'Annually', 'once' => 'One-time'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($fee['frequency'] ?? 'monthly') === $v ? 'selected' : '' ?>>
                  <?= $l ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Due day & grace period -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label required">Due Day of Month</label>
              <input type="number" name="due_day" class="form-control"
                     value="<?= $fee['due_day'] ?? 1 ?>"
                     min="1" max="28" required>
              <p class="text-xs text-slate-400 mt-1">Day 1–28 (avoids month-end issues)</p>
            </div>
            <div>
              <label class="form-label">Grace Period (days)</label>
              <input type="number" name="grace_days" class="form-control"
                     value="<?= $fee['grace_days'] ?? 5 ?>"
                     min="0" max="30">
              <p class="text-xs text-slate-400 mt-1">Days before penalty applies</p>
            </div>
          </div>

          <!-- Penalty & Applies to -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label">Late Penalty Amount (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
              <input type="number" name="penalty_amount" class="form-control"
                     value="<?= $fee['penalty_amount'] ?? 1000 ?>"
                     min="0" step="100">
              <p class="text-xs text-slate-400 mt-1">0 = no penalty</p>
            </div>
            <div>
              <label class="form-label">Applies To</label>
              <select name="applies_to" class="form-control form-select">
                <?php foreach (['all' => 'All Members', 'shareholders' => 'Shareholders Only', 'non_shareholders' => 'Non-Shareholders Only'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($fee['applies_to'] ?? 'all') === $v ? 'selected' : '' ?>>
                  <?= $l ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Effective dates -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label required">Effective From</label>
              <input type="date" name="effective_from" class="form-control"
                     value="<?= $fee['effective_from'] ?? date('Y-m-01') ?>" required>
            </div>
            <div>
              <label class="form-label">Effective To <span class="text-slate-400 font-normal">(optional)</span></label>
              <input type="date" name="effective_to" class="form-control"
                     value="<?= $fee['effective_to'] ?? '' ?>">
              <p class="text-xs text-slate-400 mt-1">Leave blank for open-ended</p>
            </div>
          </div>

          <!-- Mandatory + Status -->
          <div class="grid grid-cols-2 gap-4 items-start">
            <div class="pt-2">
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="is_mandatory" value="1" class="w-4 h-4"
                       style="accent-color:var(--green-mid)"
                       <?= ($fee['is_mandatory'] ?? 1) ? 'checked' : '' ?>>
                <div>
                  <div class="text-sm font-semibold text-slate-700">Mandatory Fee</div>
                  <div class="text-xs text-slate-400">Cannot be skipped without waiver</div>
                </div>
              </label>
            </div>
            <div>
              <label class="form-label required">Status</label>
              <select name="status" class="form-control form-select">
                <option value="active"   <?= ($fee['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($fee['status'] ?? '')        === 'inactive' ? 'selected' : '' ?>>Inactive</option>
              </select>
              <p class="text-xs text-amber-600 mt-1">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Activating will deactivate all other fee configurations.
              </p>
            </div>
          </div>

        </div>

        <div class="flex gap-3 justify-end mt-5 pt-4 border-t border-slate-100">
          <a href="<?= APP_URL ?>/social-fund" class="btn btn-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-save"></i> <?= $isEdit ? 'Save Changes' : 'Create Fee' ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('feeForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  await window.submitForm(this, {
    loadingText: 'Saving…',
    onSuccess: r => {
      window.showToast('success', r.message);
      setTimeout(() => { window.location.href = r.data?.redirect || '<?= APP_URL ?>/social-fund'; }, 800);
    }
  });
});
// Override action for edit vs create
document.getElementById('feeForm').action = '<?= $action ?>';
</script>