<?php
use App\Helpers\Format;
use App\Helpers\Avatar;

$account = $account ?? [];
$transactions = $transactions ?? [];
$memberName = trim(($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? ''));
?>

<style>
/* Reused premium styling */
.profile-hero { background: linear-gradient(135deg, #0a4033 0%, #136b55 60%, #1a8f6f 100%); border-radius: 16px; padding: 28px; color: #fff; position: relative; overflow: hidden; }
.profile-hero::after { content: ''; position: absolute; right: -40px; top: -40px; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,0.06); }
.profile-avatar { width: 64px; height: 64px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.4); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; background: rgba(255,255,255,0.15); color: #fff; flex-shrink: 0; overflow: hidden; }
.profile-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.info-row { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
.info-row:last-child { border-bottom: none; }
.info-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; width: 130px; flex-shrink: 0; padding-top: 2px; }
.info-value { font-size: 0.875rem; color: #1e293b; font-weight: 500; flex: 1; }
.tab-btn { padding: 9px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: 1.5px solid transparent; transition: all 0.18s; }
.tab-btn.active { background: var(--green-mid, #136b55); color: #fff; border-color: var(--green-mid, #136b55); }
.tab-btn:not(.active) { background: #fff; color: #64748b; border-color: #e2e8f0; }
.tab-btn:not(.active):hover { border-color: #94a3b8; color: #374151; }
.tab-panel { display: none; }
.tab-panel.active { display: block; }
.txn-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; }
</style>

<!-- Header & Actions -->
<div class="flex items-center justify-between mb-5">
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-house-chimney text-xs"></i></a>
        <span class="sep">/</span>
        <a href="<?= APP_URL ?>/savings">Savings</a>
        <span class="sep">/</span>
        <span class="current"><?= htmlspecialchars($account['account_no'] ?? '') ?></span>
    </div>
    <div class="flex gap-2 flex-wrap">
        <a href="<?= APP_URL ?>/members/<?= $account['member_id'] ?>/statement" class="btn btn-secondary btn-sm" target="_blank">
            <i class="fa-solid fa-file-pdf text-red-500"></i> Statement
        </a>
        <?php if ($auth->can('savings.deposit')): ?>
            <a href="<?= APP_URL ?>/savings/deposit?account=<?= $account['id'] ?>" class="btn btn-gold btn-sm">
                <i class="fa-solid fa-arrow-down-to-bracket"></i> Deposit
            </a>
        <?php endif; ?>
        <?php if ($auth->can('savings.withdraw')): ?>
            <a href="<?= APP_URL ?>/savings/withdraw?account=<?= $account['id'] ?>" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-up-from-bracket"></i> Withdraw
            </a>
        <?php endif; ?>
        <?php if ($auth->can('transfers.create')): ?>
            <a href="<?= APP_URL ?>/savings/transfer?member=<?= $account['member_id'] ?>" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-arrow-right-arrow-left"></i> Transfer
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Account Hero Section -->
<div class="profile-hero mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 relative z-10">
        <div class="flex items-center gap-4">
            <div class="profile-avatar">
                <?php if (!empty($account['avatar'])): ?>
                    <img src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($account['avatar']) ?>" alt="">
                <?php else: ?>
                    <?= Format::initials($memberName) ?>
                <?php endif; ?>
            </div>
            <div>
                <h1 class="text-xl md:text-2xl font-bold mb-1"><?= htmlspecialchars($memberName) ?></h1>
                <div class="flex flex-wrap gap-3 text-sm text-green-200">
                    <span><i class="fa-solid fa-id-badge mr-1"></i><?= htmlspecialchars($account['member_no'] ?? '') ?></span>
                    <span><i class="fa-solid fa-phone mr-1"></i><?= htmlspecialchars($account['phone'] ?? '') ?></span>
                </div>
            </div>
        </div>
        <div class="text-left md:text-right">
            <div class="text-xs text-green-300/70 uppercase tracking-wider font-bold">Account No.</div>
            <div class="text-lg font-mono font-bold"><?= htmlspecialchars($account['account_no'] ?? '') ?></div>
            <div class="mt-2 flex md:justify-end gap-2">
                <?= Format::statusPill($account['status'] ?? 'active') ?>
                <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-white/10 text-white border border-white/20">
                    <?= ucfirst(str_replace('_', ' ', $account['account_type'] ?? 'regular')) ?>
                </span>
            </div>
        </div>
    </div>
    <!-- Balance -->
    <div class="mt-5 pt-5 border-t border-white/10 text-center md:text-left">
        <div class="text-xs text-green-300/70 uppercase tracking-wider font-bold mb-1">Available Balance</div>
        <div class="text-3xl md:text-4xl font-black text-white"><?= Format::currency((float)($account['balance'] ?? 0)) ?></div>
    </div>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Interest Rate</div>
        <div class="text-xl font-bold text-slate-800"><?= number_format((float)($account['interest_rate'] ?? 0), 2) ?>% <span class="text-xs text-slate-400 font-normal">p.a.</span></div>
    </div>
    <div class="stat-card">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Interest Accrued</div>
        <div class="text-xl font-bold text-emerald-700"><?= Format::currency((float)($account['interest_accrued'] ?? 0)) ?></div>
    </div>
    <div class="stat-card">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Opened On</div>
        <div class="text-xl font-bold text-slate-800"><?= Format::date($account['opened_at'] ?? '') ?></div>
    </div>
    <div class="stat-card">
        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Last Interest</div>
        <div class="text-xl font-bold text-slate-800"><?= Format::date($account['last_interest_posted'] ?? '') ?: '—' ?></div>
    </div>
</div>

<!-- Tab navigation -->
<div class="flex gap-2 mb-5 flex-wrap overflow-x-auto pb-2">
    <button class="tab-btn active" onclick="switchTab('transactions',this)">Transactions (<?= count($transactions) ?>)</button>
    <button class="tab-btn" onclick="switchTab('details',this)">Account Details</button>
    <button class="tab-btn" onclick="switchTab('member',this)">Member Profile</button>
</div>

<!-- Transactions Tab -->
<div id="tab-transactions" class="tab-panel active">
    <div class="card">
        <div class="card-header">
            <h3 class="section-title"><i class="fa-solid fa-clock-rotate-left mr-2" style="color:var(--green-mid, #136b55)"></i> Recent Transactions</h3>
            <span class="text-xs text-slate-400">Showing last <?= count($transactions) ?> records</span>
        </div>
        <div class="overflow-x-auto">
            <?php if (empty($transactions)): ?>
                <div class="text-center py-12">
                    <i class="fa-regular fa-folder-open text-5xl text-slate-200 block mb-3"></i>
                    <p class="text-slate-400 font-semibold">No transactions found for this account</p>
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Type</th>
                        <th class="hidden md:table-cell">Method</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right hidden sm:table-cell">Balance</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $t):
                        $isCredit = in_array($t['txn_type'] ?? '', ['deposit', 'loan_repayment', 'interest', 'transfer_in', 'transfer_reversal_in']);
                        $amount = (float)($t['amount'] ?? 0);
                        
                        // Comprehensive Icon Map (Including new Service Fees)
                        $txnIcons = [
                            'deposit'               => ['bg' => '#dcfce7', 'color' => '#16a34a', 'icon' => 'fa-arrow-down'],
                            'withdrawal'            => ['bg' => '#fff7ed', 'color' => '#ea580c', 'icon' => 'fa-arrow-up'],
                            'loan_disbursement'     => ['bg' => '#eff6ff', 'color' => '#2563eb', 'icon' => 'fa-hand-holding-dollar'],
                            'loan_repayment'        => ['bg' => '#fdf4ff', 'color' => '#9333ea', 'icon' => 'fa-circle-dollar-to-slot'],
                            'interest'              => ['bg' => '#ecfdf5', 'color' => '#059669', 'icon' => 'fa-percent'],
                            'transfer_out'          => ['bg' => '#f0f9ff', 'color' => '#0284c7', 'icon' => 'fa-arrow-right-from-bracket'],
                            'transfer_in'           => ['bg' => '#f0f9ff', 'color' => '#0284c7', 'icon' => 'fa-arrow-right-to-bracket'],
                            'balance_inquiry_fee'   => ['bg' => '#dbeafe', 'color' => '#2563eb', 'icon' => 'fa-receipt'],
                            'statement_request_fee' => ['bg' => '#f3e8ff', 'color' => '#9333ea', 'icon' => 'fa-file-invoice'],
                            'reversal'              => ['bg' => '#fef2f2', 'color' => '#dc2626', 'icon' => 'fa-rotate-left'],
                        ];
                        $ic = $txnIcons[$t['txn_type']] ?? ['bg' => '#f1f5f9', 'color' => '#64748b', 'icon' => 'fa-arrow-right-arrow-left'];
                    ?>
                        <tr>
                            <td>
                                <div class="text-sm font-medium text-slate-800"><?= Format::date($t['transaction_date'] ?? '') ?></div>
                                <div class="text-xs text-slate-400"><?= date('H:i', strtotime($t['created_at'] ?? 'now')) ?></div>
                            </td>
                            <td>
                                <span class="font-mono text-xs bg-slate-100 px-2 py-1 rounded border border-slate-200 text-slate-600">
                                    <?= htmlspecialchars($t['txn_ref'] ?? '') ?>
                                </span>
                            </td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="txn-icon" style="background:<?= $ic['bg'] ?>;color:<?= $ic['color'] ?>">
                                        <i class="fa-solid <?= $ic['icon'] ?>"></i>
                                    </div>
                                    <div class="text-sm font-semibold text-slate-700 capitalize hidden sm:block">
                                        <?= str_replace('_', ' ', $t['txn_type'] ?? '') ?>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden md:table-cell text-sm text-slate-500 capitalize"><?= str_replace('_', ' ', $t['payment_method'] ?? '—') ?></td>
                            <td class="text-right">
                                <div class="text-sm font-bold <?= $isCredit ? 'text-emerald-700' : 'text-slate-800' ?>">
                                    <?= $isCredit ? '+' : '-' ?><?= Format::currency($amount) ?>
                                </div>
                            </td>
                            <td class="text-right text-sm font-semibold text-slate-600 hidden sm:table-cell">
                                <?= Format::currency((float)($t['balance_after'] ?? 0)) ?>
                            </td>
                            <td><?= Format::statusPill($t['status'] ?? 'completed') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Account Details Tab -->
<div id="tab-details" class="tab-panel">
    <div class="card">
        <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-circle-info mr-2" style="color:var(--green-mid, #136b55)"></i> Account Information</h3></div>
        <div class="card-body">
            <?php
            $details = [
                ['Account Number', $account['account_no'] ?? '—'],
                ['Account Type', ucfirst(str_replace('_', ' ', $account['account_type'] ?? 'regular'))],
                ['Status', Format::statusPill($account['status'] ?? 'active')],
                ['Current Balance', Format::currency((float)($account['balance'] ?? 0))],
                ['Interest Rate', number_format((float)($account['interest_rate'] ?? 0), 2) . '% p.a.'],
                ['Total Interest Accrued', Format::currency((float)($account['interest_accrued'] ?? 0))],
                ['Opened On', Format::date($account['opened_at'] ?? '')],
                ['Last Interest Posted', Format::date($account['last_interest_posted'] ?? '') ?: 'Never'],
            ];
            foreach ($details as [$label, $value]): ?>
                <div class="info-row"><div class="info-label"><?= $label ?></div><div class="info-value"><?= $value ?></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Member Profile Tab -->
<div id="tab-member" class="tab-panel">
    <div class="card">
        <div class="card-header">
            <h3 class="section-title"><i class="fa-solid fa-user mr-2" style="color:var(--green-mid, #136b55)"></i> Account Owner</h3>
            <a href="<?= APP_URL ?>/members/<?= $account['member_id'] ?>" class="btn btn-secondary btn-sm">View Full Profile <i class="fa-solid fa-arrow-right ml-1 text-xs"></i></a>
        </div>
        <div class="card-body">
            <div class="flex items-center gap-4 mb-5 pb-5 border-b border-slate-100">
                <?= Avatar::medium(['first_name' => $account['first_name'], 'last_name' => $account['last_name'], 'avatar' => $account['avatar']]) ?>
                <div>
                    <div class="text-lg font-bold text-slate-800"><?= htmlspecialchars($memberName) ?></div>
                    <div class="text-sm text-slate-400"><?= htmlspecialchars($account['member_no'] ?? '') ?></div>
                </div>
            </div>
            <?php
            $memberDetails = [
                ['Phone', $account['phone'] ?? '—'],
                ['Email', $account['email'] ?? '—'],
            ];
            foreach ($memberDetails as [$label, $value]): ?>
                <div class="info-row"><div class="info-label"><?= $label ?></div><div class="info-value"><?= htmlspecialchars($value) ?></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>