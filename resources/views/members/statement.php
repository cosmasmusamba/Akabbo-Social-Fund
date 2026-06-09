<?php
use App\Helpers\Format;
use App\Helpers\Avatar;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Statement — <?= htmlspecialchars($member['first_name'].' '.$member['last_name']) ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',Arial,sans-serif;background:#fff;color:#1e293b;font-size:13px;padding:20px}
    @media print{body{padding:0}.no-print{display:none!important}@page{margin:15mm}}
    .header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #136b55;padding-bottom:16px;margin-bottom:20px}
    .org-info{display:flex;align-items:center;gap:12px}
    .org-logo{width:48px;height:48px;object-fit:contain;border-radius:8px}
    .org-name{font-size:22px;font-weight:800;color:#136b55}
    .org-sub{font-size:11px;color:#64748b;margin-top:2px}
    .doc-title{text-align:right}.doc-title h2{font-size:18px;font-weight:700;color:#0a4033}
    .doc-title p{font-size:11px;color:#64748b;margin-top:2px}
    .section{margin-bottom:20px}
    .section-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#136b55;border-bottom:1px solid #a7f3d0;padding-bottom:4px;margin-bottom:10px}
    .info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px 20px}
    .info-item label{font-size:10px;font-weight:700;color:#94a3b8;display:block;text-transform:uppercase}
    .info-item span{font-weight:600;color:#1e293b}
    table{width:100%;border-collapse:collapse;font-size:12px}
    th{background:#f0fdf9;color:#136b55;font-weight:700;text-align:left;padding:7px 10px;border-bottom:2px solid #a7f3d0;font-size:10px;text-transform:uppercase}
    td{padding:6px 10px;border-bottom:1px solid #f1f5f9}
    tr:last-child td{border-bottom:none}
    .text-right{text-align:right}
    .credit{color:#059669;font-weight:700}
    .debit{color:#dc2626;font-weight:700}
    .totals-row td{background:#f8fafc;font-weight:700;border-top:2px solid #e2e8f0}
    .kpi-row{display:flex;gap:12px;margin-bottom:20px}
    .kpi{flex:1;border:1px solid #e2e8f0;border-radius:8px;padding:12px;text-align:center}
    .kpi-val{font-size:18px;font-weight:800;color:#136b55}
    .kpi-lbl{font-size:10px;color:#94a3b8;font-weight:600;margin-top:2px;text-transform:uppercase}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700}
    .badge-active{background:#dcfce7;color:#166534}
    .badge-inactive{background:#f1f5f9;color:#64748b}
    .footer{margin-top:24px;border-top:1px solid #e2e8f0;padding-top:12px;display:flex;justify-content:space-between;font-size:10px;color:#94a3b8}
    .btn-print{background:#136b55;color:#fff;border:none;padding:8px 18px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;margin-bottom:16px}
  </style>
</head>
<body>

<div class="no-print" style="margin-bottom:16px">
  <button class="btn-print" onclick="window.print()"><i>🖨</i> Print Statement</button>
  <a href="<?= APP_URL ?>/members/<?= $member['id'] ?>" style="margin-left:8px;font-size:13px;color:#136b55">← Back to Profile</a>
</div>

<!-- Header with organization logo using Avatar helper -->
<div class="header">
  <div class="org-info">
    <?= Avatar::orgLogo($settings) ?>
    <div>
      <div class="org-name"><?= htmlspecialchars($settings['org_name']??'Akabbo Social Fund') ?></div>
      <div class="org-sub"><?= htmlspecialchars($settings['org_address']??'') ?> · <?= htmlspecialchars($settings['org_phone']??'') ?></div>
      <div class="org-sub"><?= htmlspecialchars($settings['org_email']??'') ?></div>
    </div>
  </div>
  <div class="doc-title">
    <h2>Member Account Statement</h2>
    <p>Period: <?= Format::date($from) ?> — <?= Format::date($to) ?></p>
    <p>Generated: <?= Format::datetime(date('Y-m-d H:i:s')) ?></p>
  </div>
</div>

<!-- Member Information with avatar -->
<div class="section">
  <div class="section-title">Member Information</div>
  <div class="flex items-center gap-4 mb-4">
    <?= Avatar::medium($member) ?>
    <div>
      <div class="font-bold text-lg"><?= htmlspecialchars($member['first_name'].' '.$member['last_name']) ?></div>
      <div class="text-sm text-slate-500"><?= htmlspecialchars($member['member_no']) ?></div>
    </div>
  </div>
  <div class="info-grid">
    <div class="info-item"><label>Member No</label><span><?= htmlspecialchars($member['member_no']) ?></span></div>
    <div class="info-item"><label>Full Name</label><span><?= htmlspecialchars($member['first_name'].' '.$member['last_name']) ?></span></div>
    <div class="info-item"><label>Phone</label><span><?= htmlspecialchars($member['phone']) ?></span></div>
    <div class="info-item"><label>National ID</label><span><?= htmlspecialchars($member['national_id']??'—') ?></span></div>
    <div class="info-item"><label>Joined</label><span><?= Format::date($member['membership_date']) ?></span></div>
    <div class="info-item"><label>Status</label><span class="badge <?= $member['status']==='active'?'badge-active':'badge-inactive' ?>"><?= ucfirst($member['status']) ?></span></div>
  </div>
</div>

<!-- KPIs (unchanged) -->
<div class="kpi-row">
  <div class="kpi"><div class="kpi-val"><?= Format::currency((float)$member['total_savings']) ?></div><div class="kpi-lbl">Total Savings</div></div>
  <div class="kpi"><div class="kpi-val"><?= $member['total_loans'] ?></div><div class="kpi-lbl">Total Loans</div></div>
  <div class="kpi"><div class="kpi-val" style="color:#dc2626"><?= Format::currency((float)$member['active_loan_balance']) ?></div><div class="kpi-lbl">Loan Balance</div></div>
  <?php
  $totalIn  = array_sum(array_column(array_filter($transactions, fn($t) => in_array($t['txn_type'],['deposit','loan_repayment'])), 'amount'));
  $totalOut = array_sum(array_column(array_filter($transactions, fn($t) => in_array($t['txn_type'],['withdrawal','loan_disbursement'])), 'amount'));
  ?>
  <div class="kpi"><div class="kpi-val"><?= Format::currency($totalIn) ?></div><div class="kpi-lbl">Total Inflow</div></div>
  <div class="kpi"><div class="kpi-val" style="color:#ea580c"><?= Format::currency($totalOut) ?></div><div class="kpi-lbl">Total Outflow</div></div>
</div>

<!-- Savings Accounts (unchanged) -->
<?php if(!empty($savingsAccounts)): ?>
<div class="section">
  <div class="section-title">Savings Accounts</div>
  <table>
    <thead><tr><th>Account No</th><th>Type</th><th class="text-right">Balance</th><th>Status</th><th>Opened</th></tr></thead>
    <tbody>
      <?php foreach($savingsAccounts as $sa): ?>
      <tr>
        <td><?= htmlspecialchars($sa['account_no']) ?></td>
        <td><?= ucwords(str_replace('_',' ',$sa['account_type'])) ?></td>
        <td class="text-right credit"><?= Format::currency((float)$sa['balance']) ?></td>
        <td><?= ucfirst($sa['status']) ?></td>
        <td><?= Format::date($sa['opened_at']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Loans (unchanged) -->
<?php if(!empty($loans)): ?>
<div class="section">
  <div class="section-title">Loan History</div>
  <table>
    <thead><tr><th>Loan No</th><th>Product</th><th class="text-right">Principal</th><th class="text-right">Repaid</th><th class="text-right">Outstanding</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach($loans as $l): ?>
      <tr>
        <td><?= htmlspecialchars($l['loan_no']) ?></td>
        <td><?= htmlspecialchars($l['product_name']) ?></td>
        <td class="text-right"><?= Format::currency((float)$l['principal_amount']) ?></td>
        <td class="text-right credit"><?= Format::currency((float)$l['amount_paid']) ?></td>
        <td class="text-right <?= $l['balance_outstanding']>0?'debit':'' ?>"><?= Format::currency((float)$l['balance_outstanding']) ?></td>
        <td><?= ucfirst($l['status']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Transactions (unchanged) -->
<div class="section">
  <div class="section-title">Transactions — <?= Format::date($from) ?> to <?= Format::date($to) ?></div>
  <?php if(empty($transactions)): ?>
  <p style="color:#94a3b8;text-align:center;padding:16px">No transactions in this period.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Method</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr></thead>
    <tbody>
      <?php
      $balance = 0;
      foreach($transactions as $t):
        $isCredit = in_array($t['txn_type'],['deposit','loan_repayment','membership_fee']);
        if($isCredit) $balance += $t['amount'];
        else          $balance -= $t['amount'];
      ?>
      <tr>
        <td><?= Format::date($t['transaction_date']) ?></td>
        <td style="font-family:monospace;font-size:11px"><?= htmlspecialchars($t['txn_ref']) ?></td>
        <td><?= htmlspecialchars(Format::truncate($t['description']??ucwords(str_replace('_',' ',$t['txn_type'])),40)) ?></td>
        <td><?= ucwords(str_replace('_',' ',$t['payment_method']??'')) ?></td>
        <td class="text-right debit"><?= !$isCredit?Format::currency((float)$t['amount']):'' ?></td>
        <td class="text-right credit"><?= $isCredit?Format::currency((float)$t['amount']):'' ?></td>
        <td class="text-right"><?= Format::currency($balance) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="totals-row">
        <td colspan="4">TOTALS</td>
        <td class="text-right debit"><?= Format::currency($totalOut) ?></td>
        <td class="text-right credit"><?= Format::currency($totalIn) ?></td>
        <td class="text-right"><?= Format::currency($balance) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>
</div>

<!-- Footer (unchanged) -->
<div class="footer">
  <div>This statement was generated by <?= htmlspecialchars($settings['org_name']??'Akabbo Social Fund') ?> on <?= date('d M Y H:i') ?></div>
  <div>Confidential — For member use only</div>
</div>
</body>
</html>