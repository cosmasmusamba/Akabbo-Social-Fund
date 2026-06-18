<?php
use App\Helpers\Format;
use App\Helpers\Avatar;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Statement — <?= htmlspecialchars(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: #fff; color: #1e293b; font-size: 13px; padding: 20px; }
@media print { body { padding: 0; } .no-print { display: none !important; } @page { margin: 15mm; } }
.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #136b55; padding-bottom: 16px; margin-bottom: 20px; }
.org-info { display: flex; align-items: center; gap: 12px; }
.org-name { font-size: 22px; font-weight: 800; color: #136b55; }
.org-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
.doc-title { text-align: right; }
.doc-title h2 { font-size: 18px; font-weight: 700; color: #0a4033; }
.doc-title p { font-size: 11px; color: #64748b; margin-top: 2px; }
.section { margin-bottom: 20px; }
.section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #136b55; border-bottom: 1px solid #a7f3d0; padding-bottom: 4px; margin-bottom: 10px; }
.info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px 20px; }
.info-item label { font-size: 10px; font-weight: 700; color: #94a3b8; display: block; text-transform: uppercase; }
.info-item span { font-weight: 600; color: #1e293b; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
th { background: #f0fdf9; color: #136b55; font-weight: 700; text-align: left; padding: 7px 10px; border-bottom: 2px solid #a7f3d0; font-size: 10px; text-transform: uppercase; }
td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; }
tr:last-child td { border-bottom: none; }
.text-right { text-align: right; }
.credit { color: #059669; font-weight: 700; }
.debit { color: #dc2626; font-weight: 700; }
.totals-row td { background: #f8fafc; font-weight: 700; border-top: 2px solid #e2e8f0; }
.kpi-row { display: flex; gap: 12px; margin-bottom: 20px; }
.kpi { flex: 1; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
.kpi-val { font-size: 18px; font-weight: 800; color: #136b55; }
.kpi-lbl { font-size: 10px; color: #94a3b8; font-weight: 600; margin-top: 2px; text-transform: uppercase; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; }
.badge-active { background: #dcfce7; color: #166534; }
.badge-inactive { background: #f1f5f9; color: #64748b; }
.footer { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 12px; display: flex; justify-content: space-between; font-size: 10px; color: #94a3b8; }
.btn-print { background: #136b55; color: #fff; border: none; padding: 8px 18px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; margin-bottom: 16px; }
.pending-fees-box { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 12px; margin-top: 10px; }
.pending-fees-box h4 { font-size: 11px; font-weight: 700; color: #c2410c; margin-bottom: 8px; text-transform: uppercase; }
</style>
</head>
<body>
<div class="no-print" style="margin-bottom:16px">
<button class="btn-print" onclick="window.print()"><i>🖨</i> Print Statement</button>
<a href="<?= APP_URL ?>/members/<?= $member['id'] ?? 0 ?>" style="margin-left:8px;font-size:13px;color:#136b55">← Back to Profile</a>
</div>

<!-- Header -->
<div class="header">
<div class="org-info">
<?= Avatar::orgLogo($settings ?? []) ?>
<div>
<div class="org-name"><?= htmlspecialchars($settings['org_name'] ?? 'Akabbo Social Fund') ?></div>
<div class="org-sub"><?= htmlspecialchars($settings['org_address'] ?? '') ?> · <?= htmlspecialchars($settings['org_phone'] ?? '') ?></div>
<div class="org-sub"><?= htmlspecialchars($settings['org_email'] ?? '') ?></div>
</div>
</div>
<div class="doc-title">
<h2>Member Account Statement</h2>
<p>Period: <?= Format::date($from ?? '') ?> — <?= Format::date($to ?? '') ?></p>
<p>Generated: <?= Format::datetime(date('Y-m-d H:i:s')) ?></p>
</div>
</div>

<!-- Member Information -->
<div class="section">
<div class="section-title">Member Information</div>
<div class="flex items-center gap-4 mb-4" style="display:flex;align-items:center;gap:16px;margin-bottom:16px;">
<?= Avatar::medium($member ?? []) ?>
<div>
<div class="font-bold text-lg" style="font-weight:700;font-size:1.125rem;"><?= htmlspecialchars(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?></div>
<div class="text-sm text-slate-500" style="font-size:0.875rem;color:#64748b;"><?= htmlspecialchars($member['member_no'] ?? '') ?></div>
</div>
</div>
<div class="info-grid">
<div class="info-item"><label>Member No</label><span><?= htmlspecialchars($member['member_no'] ?? '—') ?></span></div>
<div class="info-item"><label>Full Name</label><span><?= htmlspecialchars(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?: '—' ?></span></div>
<div class="info-item"><label>Phone</label><span><?= htmlspecialchars($member['phone'] ?? '—') ?></span></div>
<div class="info-item"><label>National ID</label><span><?= htmlspecialchars($member['national_id'] ?? '—') ?></span></div>
<div class="info-item"><label>Joined</label><span><?= Format::date($member['membership_date'] ?? '') ?></span></div>
<div class="info-item"><label>Status</label><span class="badge <?= ($member['status'] ?? '') === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= ucfirst($member['status'] ?? '—') ?></span></div>
</div>
</div>

<!-- KPIs -->
<div class="kpi-row">
<div class="kpi"><div class="kpi-val"><?= Format::currency((float)($member['total_savings'] ?? 0)) ?></div><div class="kpi-lbl">Total Savings</div></div>
<div class="kpi"><div class="kpi-val"><?= (int)($member['total_loans'] ?? 0) ?></div><div class="kpi-lbl">Total Loans</div></div>
<div class="kpi"><div class="kpi-val" style="color:#dc2626"><?= Format::currency((float)($member['active_loan_balance'] ?? 0)) ?></div><div class="kpi-lbl">Loan Balance</div></div>
<?php
$totalIn  = array_sum(array_column(array_filter($transactions ?? [], fn($t) => in_array($t['txn_type'] ?? '', ['deposit', 'loan_repayment', 'interest', 'transfer_in'])), 'amount'));
$totalOut = array_sum(array_column(array_filter($transactions ?? [], fn($t) => in_array($t['txn_type'] ?? '', ['withdrawal', 'loan_disbursement', 'transfer_out'])), 'amount'));
?>
<div class="kpi"><div class="kpi-val"><?= Format::currency((float)$totalIn) ?></div><div class="kpi-lbl">Total Inflow</div></div>
<div class="kpi"><div class="kpi-val" style="color:#ea580c"><?= Format::currency((float)$totalOut) ?></div><div class="kpi-lbl">Total Outflow</div></div>
</div>

<!-- Savings Accounts -->
<?php if (!empty($savingsAccounts)): ?>
<div class="section">
<div class="section-title">Savings Accounts</div>
<table>
<thead><tr><th>Account No</th><th>Type</th><th class="text-right">Balance</th><th>Status</th><th>Opened</th></tr></thead>
<tbody>
<?php foreach ($savingsAccounts as $sa): ?>
<tr>
<td><?= htmlspecialchars($sa['account_no'] ?? '') ?></td>
<td><?= ucwords(str_replace('_', ' ', $sa['account_type'] ?? '')) ?></td>
<td class="text-right credit"><?= Format::currency((float)($sa['balance'] ?? 0)) ?></td>
<td><?= ucfirst($sa['status'] ?? '') ?></td>
<td><?= Format::date($sa['opened_at'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- Loans -->
<?php if (!empty($loans)): ?>
<div class="section">
<div class="section-title">Loan History</div>
<table>
<thead><tr><th>Loan No</th><th>Product</th><th class="text-right">Principal</th><th class="text-right">Repaid</th><th class="text-right">Outstanding</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($loans as $l): ?>
<tr>
<td><?= htmlspecialchars($l['loan_no'] ?? '') ?></td>
<td><?= htmlspecialchars($l['product_name'] ?? '') ?></td>
<td class="text-right"><?= Format::currency((float)($l['principal_amount'] ?? 0)) ?></td>
<td class="text-right credit"><?= Format::currency((float)($l['amount_paid'] ?? 0)) ?></td>
<td class="text-right <?= ((float)($l['balance_outstanding'] ?? 0)) > 0 ? 'debit' : '' ?>"><?= Format::currency((float)($l['balance_outstanding'] ?? 0)) ?></td>
<td><?= ucfirst($l['status'] ?? '') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<!-- Transactions -->
<div class="section">
<div class="section-title">Transactions — <?= Format::date($from ?? '') ?> to <?= Format::date($to ?? '') ?></div>
<?php if (empty($transactions)): ?>
<p style="color:#94a3b8;text-align:center;padding:16px">No transactions in this period.</p>
<?php else: ?>
<table>
<thead><tr><th>Date</th><th>Reference</th><th>Description</th><th>Method</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th></tr></thead>
<tbody>
<?php
$balance = 0;
foreach ($transactions as $t):
$isCredit = in_array($t['txn_type'] ?? '', ['deposit', 'loan_repayment', 'membership_fee', 'interest', 'transfer_in']);
if ($isCredit) $balance += (float)($t['amount'] ?? 0);
else           $balance -= (float)($t['amount'] ?? 0);
?>
<tr>
<td><?= Format::date($t['transaction_date'] ?? '') ?></td>
<td style="font-family:monospace;font-size:11px"><?= htmlspecialchars($t['txn_ref'] ?? '') ?></td>
<td><?= htmlspecialchars(Format::truncate($t['description'] ?? ucwords(str_replace('_', ' ', $t['txn_type'] ?? '')), 40)) ?></td>
<td><?= ucwords(str_replace('_', ' ', $t['payment_method'] ?? '')) ?></td>
<td class="text-right debit"><?= !$isCredit ? Format::currency((float)($t['amount'] ?? 0)) : '' ?></td>
<td class="text-right credit"><?= $isCredit ? Format::currency((float)($t['amount'] ?? 0)) : '' ?></td>
<td class="text-right"><?= Format::currency((float)$balance) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr class="totals-row">
<td colspan="4">TOTALS</td>
<td class="text-right debit"><?= Format::currency((float)$totalOut) ?></td>
<td class="text-right credit"><?= Format::currency((float)$totalIn) ?></td>
<td class="text-right"><?= Format::currency((float)$balance) ?></td>
</tr>
</tfoot>
</table>
<?php endif; ?>
</div>

<!-- ✅ COMPLIANCE FIX: Pending Service Fees Section (Audit Sec 15) -->
<?php if (!empty($pendingDebits)): ?>
<div class="section">
<div class="section-title">Pending Service Fees</div>
<div class="pending-fees-box">
<h4><i class="fa-solid fa-circle-info"></i> Upcoming Deductions</h4>
<p style="font-size:11px;color:#9a3412;margin-bottom:8px;">
The following service fees could not be collected due to insufficient available balance at the time of request. 
They will be automatically deducted from your next deposit.
</p>
<table>
<thead><tr><th>Date Requested</th><th>Service Type</th><th class="text-right">Amount Due</th></tr></thead>
<tbody>
<?php 
$totalPending = 0;
foreach ($pendingDebits as $debit): 
    $totalPending += (float)$debit['amount'];
?>
<tr>
<td><?= Format::date($debit['created_at'] ?? '') ?></td>
<td><?= ucwords(str_replace('_', ' ', str_replace('_fee', '', $debit['reason']))) ?> Fee</td>
<td class="text-right debit"><?= Format::currency((float)$debit['amount']) ?></td>
</tr>
<?php endforeach; ?>
<tr class="totals-row">
<td colspan="2"><strong>Total Pending</strong></td>
<td class="text-right debit"><strong><?= Format::currency((float)$totalPending) ?></strong></td>
</tr>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<!-- Footer -->
<div class="footer">
<div>This statement was generated by <?= htmlspecialchars($settings['org_name'] ?? 'Akabbo Social Fund') ?> on <?= date('d M Y H:i') ?></div>
<div>Confidential — For member use only</div>
</div>
</body>
</html>