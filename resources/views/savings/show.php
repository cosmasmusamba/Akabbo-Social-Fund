<?php use App\Helpers\Format; ?>
<div class="flex items-center justify-between mb-5">
  <div class="breadcrumb"><a href="<?= APP_URL ?>/savings">Savings</a><span class="sep">/</span><span class="current"><?= htmlspecialchars($account['account_no']) ?></span></div>
  <div class="flex gap-2">
    <?php if($auth->can('savings.deposit')): ?>
    <a href="<?= APP_URL ?>/savings/deposit?member=<?= $account['member_id'] ?>&account=<?= $account['id'] ?>" class="btn btn-gold btn-sm"><i class="fa-solid fa-arrow-down-to-bracket"></i> Deposit</a>
    <a href="<?= APP_URL ?>/savings/withdraw?member=<?= $account['member_id'] ?>&account=<?= $account['id'] ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-up-from-bracket"></i> Withdraw</a>
    <?php endif; ?>
  </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
  <div>
    <div class="card mb-5">
      <div class="card-body text-center py-6" style="background:linear-gradient(135deg,#f0fdf9,#ecfdf5);border-radius:14px;">
        <div class="text-xs text-slate-400 mb-1 uppercase tracking-wider font-semibold">Account Balance</div>
        <div class="text-4xl font-black text-emerald-700"><?= Format::currency((float)$account['balance']) ?></div>
        <div class="text-sm text-slate-500 mt-1"><?= Format::titleCase($account['account_type']) ?> Account</div>
        <?= Format::statusPill($account['status']) ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h3 class="section-title">Account Info</h3></div>
      <div class="card-body">
        <?php $f=[['Account No',$account['account_no']],['Member',$account['member_name']],['Member No',$account['member_no']],['Phone',$account['phone']],['Type',Format::titleCase($account['account_type'])],['Interest Rate',Format::percentage((float)$account['interest_rate'])],['Opened',Format::date($account['opened_at'])]];
        foreach($f as [$l,$v]): ?>
        <div class="flex justify-between py-2 border-b border-slate-50 last:border-0 text-sm"><span class="text-slate-400 text-xs font-semibold"><?= $l ?></span><span class="font-semibold text-slate-700"><?= htmlspecialchars($v) ?></span></div>
        <?php endforeach; ?>
        <a href="<?= APP_URL ?>/members/<?= $account['member_id'] ?>" class="btn btn-secondary btn-sm mt-4 w-full justify-center"><i class="fa-solid fa-user"></i> View Member</a>
      </div>
    </div>
  </div>
  <div class="lg:col-span-2">
    <div class="card">
      <div class="card-header"><h3 class="section-title">Transaction History</h3></div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead><tr><th>Ref</th><th>Type</th><th>Amount</th><th class="hidden md:table-cell">Balance After</th><th class="hidden md:table-cell">Method</th><th>Date</th></tr></thead>
          <tbody>
            <?php if(empty($transactions)): ?>
            <tr><td colspan="6" class="text-center py-8 text-slate-400">No transactions found</td></tr>
            <?php else: foreach($transactions as $t): ?>
            <tr>
              <td><span class="font-mono text-xs"><?= htmlspecialchars($t['txn_ref']) ?></span></td>
              <td class="text-xs capitalize"><?= str_replace('_',' ',$t['txn_type']) ?></td>
              <td class="font-bold <?= in_array($t['txn_type'],['deposit'])?'text-emerald-700':'text-orange-700' ?>"><?= Format::currency((float)$t['amount']) ?></td>
              <td class="hidden md:table-cell text-sm text-slate-600"><?= $t['balance_after']!==null?Format::currency((float)$t['balance_after']):'—' ?></td>
              <td class="hidden md:table-cell text-xs capitalize"><?= str_replace('_',' ',$t['payment_method']??'—') ?></td>
              <td class="text-xs text-slate-400"><?= Format::date($t['transaction_date']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
