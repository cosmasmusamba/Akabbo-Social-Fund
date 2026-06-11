<?php use App\Helpers\Format; ?>
<!DOCTYPE html>
<html>
<head>
  <title>Savings Statement - <?= $account['account_no'] ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .header { text-align: center; margin-bottom: 30px; }
    .title { font-size: 24px; font-weight: bold; }
    .sub { color: #666; margin-top: 5px; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background: #f2f2f2; }
    .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
    @media print { .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="header">
    <div class="title">Savings Account Statement</div>
    <div class="sub"><?= htmlspecialchars($account['account_no']) ?> · <?= htmlspecialchars($account['member_name']) ?></div>
    <div class="sub">Period: <?= Format::date($from) ?> to <?= Format::date($to) ?></div>
  </div>
  <table>
    <thead>
      <tr><th>Date</th><th>Reference</th><th>Description</th><th>Debit</th><th>Credit</th><th>Balance</th></tr>
    </thead>
    <tbody>
      <?php $running = 0; foreach($transactions as $t): $running = $t['balance_after']; ?>
      <tr>
        <td><?= Format::date($t['transaction_date']) ?></td>
        <td><?= htmlspecialchars($t['txn_ref']) ?></td>
        <td><?= htmlspecialchars($t['description']) ?></td>
        <td class="text-right"><?= in_array($t['txn_type'],['withdrawal','transfer_out']) ? Format::currency($t['amount']) : '' ?></td>
        <td class="text-right"><?= in_array($t['txn_type'],['deposit','transfer_in','interest']) ? Format::currency($t['amount']) : '' ?></td>
        <td class="text-right"><?= Format::currency($t['balance_after']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="footer">Generated on <?= date('Y-m-d H:i:s') ?> · Akabbo Social Fund</div>
  <div class="no-print" style="margin-top:20px; text-align:center;">
    <button onclick="window.print()">Print / Save PDF</button>
    <button onclick="window.close()">Close</button>
  </div>
</body>
</html>