<?php
// remove anything to do with editing from this page, just add an edit button to redirect to edit. Comprehensively display all user information as captured at registration and any linking.
use App\Helpers\Format;
use App\Helpers\Avatar;

$pageTitle   = $pageTitle ?? 'My Profile';
$activePage  = $activePage ?? 'profile';
$breadcrumbs = $breadcrumbs ?? ['Profile' => null];
$savingsAccounts = $savingsAccounts ?? [];
$loans = $loans ?? [];
$transactions = $transactions ?? [];
$shares = $shares ?? null;
$shareTxns = $shareTxns ?? [];
?>

<style>
/* Reused from members/show.php for consistent premium styling */
.profile-hero{background:linear-gradient(135deg,#0a4033 0%,#136b55 60%,#1a8f6f 100%);border-radius:16px;padding:28px;color:#fff;position:relative;overflow:hidden;}
.profile-hero::after{content:'';position:absolute;right:-40px;top:-40px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,0.06);}
.profile-avatar{width:80px;height:80px;border-radius:50%;border:3px solid rgba(255,255,255,0.4);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:700;background:rgba(255,255,255,0.15);color:#fff;flex-shrink:0;overflow:hidden;}
.profile-avatar img{width:100%;height:100%;object-fit:cover;border-radius:50%;}
.info-row{display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9;}
.info-row:last-child{border-bottom:none;}
.info-label{font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#94a3b8;width:130px;flex-shrink:0;padding-top:2px;}
.info-value{font-size:0.875rem;color:#1e293b;font-weight:500;flex:1;}
.kyc-doc-card{display:flex;align-items:center;gap:12px;padding:8px 12px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;}
.kyc-doc-card img{width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;cursor:pointer;}
.kyc-doc-card i{font-size:2rem;color:#64748b;}
.tab-btn{padding:9px 18px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;transition:all 0.18s;}
.tab-btn.active{background:var(--green-mid, #136b55);color:#fff;border-color:var(--green-mid, #136b55);}
.tab-btn:not(.active){background:#fff;color:#64748b;border-color:#e2e8f0;}
.tab-btn:not(.active):hover{border-color:#94a3b8;color:#374151;}
.tab-panel{display:none;}.tab-panel.active{display:block;}
</style>

<!-- Header Actions -->
<div class="flex items-center justify-between mb-5">
    <div class="breadcrumb">
        <a href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-house-chimney text-xs"></i></a>
        <span class="sep">/</span><span class="current">My Profile</span>
    </div>
    <div class="flex gap-2 flex-wrap">
        <!-- ✅ NEW: Edit Profile Button -->
        <a href="<?= APP_URL ?>/profile/edit" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-pen"></i> Edit Profile
        </a>
        <a href="<?= APP_URL ?>/profile/change-password" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-key"></i> Change Password
        </a>
    </div>
</div>

<!-- Profile Hero -->
<div class="profile-hero mb-5">
    <div class="flex items-center gap-5">
        <div class="profile-avatar"><?= Avatar::medium($user) ?></div>
        <div class="flex-1 min-w-0">
            <h1 class="text-2xl font-bold mb-1"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></h1>
            <div class="flex flex-wrap gap-3 text-sm text-green-200 mb-3">
                <span><i class="fa-solid fa-envelope mr-1"></i><?= htmlspecialchars($user['email'] ?? '') ?></span>
                <span><i class="fa-solid fa-phone mr-1"></i><?= htmlspecialchars($user['phone'] ?? '') ?></span>
                <?php if ($member): ?>
                    <span><i class="fa-solid fa-id-badge mr-1"></i><?= htmlspecialchars($member['member_no'] ?? '') ?></span>
                <?php endif; ?>
            </div>
            <div class="flex gap-2 flex-wrap">
                <?= Format::statusPill($user['status'] ?? 'active') ?>
                <span class="badge" style="background:rgba(59,130,246,0.2);color:#93c5fd;border-color:rgba(59,130,246,0.3)">
                    <i class="fa-solid fa-user-shield mr-1"></i><?= htmlspecialchars($user['role_name'] ?? 'User') ?>
                </span>
                <?php if ($member): ?>
                    <?php if (!empty($member['kyc_verified'])): ?>
                        <span class="badge" style="background:rgba(16,185,129,0.2);color:#6ee7b7;border-color:rgba(16,185,129,0.4)"><i class="fa-solid fa-shield-check mr-1"></i>KYC Verified</span>
                    <?php else: ?>
                        <span class="badge" style="background:rgba(245,158,11,0.2);color:#fcd34d;border-color:rgba(245,158,11,0.3)"><i class="fa-solid fa-clock mr-1"></i>KYC Pending</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Financial KPIs (Only if member exists) -->
    <?php if ($member): ?>
    <?php
    $totalSavings = array_sum(array_column($savingsAccounts, 'balance'));
    $totalLoans = count($loans);
    $activeLoanBalance = array_sum(array_map(fn($l) => (float)($l['balance_outstanding'] ?? 0), array_filter($loans, fn($l) => in_array($l['status'] ?? '', ['active', 'disbursed']))));
    ?>
    <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-white/10">
        <div class="text-center">
            <div class="text-2xl font-black text-white"><?= Format::currencyCompact((float)$totalSavings) ?></div>
            <div class="text-xs text-green-300 mt-0.5">Total Savings</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-black text-white"><?= $totalLoans ?></div>
            <div class="text-xs text-green-300 mt-0.5">Total Loans</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-black" style="color:#fcd34d"><?= Format::currencyCompact((float)$activeLoanBalance) ?></div>
            <div class="text-xs text-green-300 mt-0.5">Loan Balance</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tab navigation -->
<div class="flex gap-2 mb-5 flex-wrap">
    <button class="tab-btn active" onclick="switchTab('account',this)">Account Info</button>
    <?php if ($member): ?>
        <button class="tab-btn" onclick="switchTab('personal',this)">Personal Info</button>
        <button class="tab-btn" onclick="switchTab('savings',this)">Savings (<?= count($savingsAccounts) ?>)</button>
        <button class="tab-btn" onclick="switchTab('loans',this)">Loans (<?= count($loans) ?>)</button>
        <button class="tab-btn" onclick="switchTab('transactions',this)">Transactions</button>
        <button class="tab-btn" onclick="switchTab('shares',this)">Shares</button>
    <?php endif; ?>
    <button class="tab-btn" onclick="switchTab('sessions',this)">Login Sessions</button>
</div>

<!-- Account Info Tab -->
<div id="tab-account" class="tab-panel active">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="card">
            <div class="card-header flex items-center justify-between">
                <h3 class="section-title"><i class="fa-solid fa-user-gear mr-2" style="color:var(--green-mid, #136b55)"></i>System User Details</h3>
                <a href="<?= APP_URL ?>/profile/edit" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-pen text-xs"></i> Edit
                </a>
            </div>
            <div class="card-body">
                <?php
                $userFields = [
                    ['Email', $user['email'] ?? '—'],
                    ['Phone', $user['phone'] ?? '—'],
                    ['NIN', $user['nin'] ?? '—'],
                    ['Passport Number', $user['passport_number'] ?? '—'],
                    ['Role', $user['role_name'] ?? '—'],
                    ['Last Login', !empty($user['last_login']) ? Format::datetime($user['last_login']) : 'Never'],
                    ['Account Created', Format::datetime($user['created_at'] ?? '')],
                ];
                foreach ($userFields as [$label, $value]): ?>
                    <div class="info-row"><div class="info-label"><?= $label ?></div><div class="info-value"><?= htmlspecialchars($value) ?></div></div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- ✅ NEW: Member Linking Status Card -->
        <div class="card">
            <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-link mr-2" style="color:var(--green-mid, #136b55)"></i>Member Profile Linking</h3></div>
            <div class="card-body">
                <?php if ($member): ?>
                    <div class="info-row"><div class="info-label">Link Status</div><div class="info-value"><span class="badge bg-emerald-100 text-emerald-700 border-emerald-200">Linked</span></div></div>
                    <div class="info-row"><div class="info-label">Member ID</div><div class="info-value"><?= htmlspecialchars($member['member_no'] ?? '') ?></div></div>
                    <div class="info-row"><div class="info-label">Link Method</div><div class="info-value">
                        <?php 
                        if (!empty($user['nin']) && $user['nin'] === ($member['national_id'] ?? '')) echo 'National ID (NIN)';
                        elseif (!empty($user['passport_number']) && $user['passport_number'] === ($member['passport_no'] ?? '')) echo 'Passport Number';
                        elseif (!empty($user['email']) && strtolower($user['email']) === strtolower($member['email'] ?? '')) echo 'Email Address';
                        else echo 'Direct Assignment';
                        ?>
                    </div></div>
                    <div class="info-row"><div class="info-label">Member Since</div><div class="info-value"><?= Format::date($member['membership_date'] ?? '') ?></div></div>
                    <div class="info-row"><div class="info-label">View Profile</div><div class="info-value"><a href="<?= APP_URL ?>/members/<?= $member['id'] ?>" class="text-green-600 hover:underline font-semibold">View Full Member Profile <i class="fa-solid fa-arrow-right text-xs"></i></a></div></div>
                <?php else: ?>
                    <div class="text-center py-6">
                        <i class="fa-solid fa-link-slash text-3xl text-slate-300 mb-2"></i>
                        <p class="text-slate-500 text-sm">No member profile linked to this account.</p>
                        <p class="text-xs text-slate-400 mt-1">Contact an administrator to link your member profile using your NIN, Passport, or Email.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($member): ?>
<!-- Personal Info Tab -->
<div id="tab-personal" class="tab-panel">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="card">
            <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-user mr-2" style="color:var(--green-mid, #136b55)"></i>Personal Details</h3></div>
            <div class="card-body">
                <?php
                $personalFields = [
                    ['Gender', ucfirst($member['gender'] ?? '—')],
                    ['Date of Birth', Format::date($member['date_of_birth'] ?? '')],
                    ['National ID', $member['national_id'] ?? '—'],
                    ['Passport Number', $member['passport_no'] ?? '—'],
                    ['Occupation', $member['occupation'] ?? '—'],
                    ['Employer', $member['employer'] ?? '—'],
                    ['District', $member['district'] ?? '—'],
                    ['Address', $member['address'] ?? '—']
                ];
                foreach ($personalFields as [$label, $value]): ?>
                    <div class="info-row"><div class="info-label"><?= $label ?></div><div class="info-value"><?= htmlspecialchars($value) ?></div></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-people-group mr-2" style="color:var(--green-mid, #136b55)"></i>Next of Kin</h3></div>
            <div class="card-body">
                <?php
                $kinFields = [
                    ['Name', $member['next_of_kin_name'] ?? '—'],
                    ['Phone', $member['next_of_kin_phone'] ?? '—'],
                    ['Relationship', $member['next_of_kin_relationship'] ?? '—']
                ];
                foreach ($kinFields as [$label, $value]): ?>
                    <div class="info-row"><div class="info-label"><?= $label ?></div><div class="info-value"><?= htmlspecialchars($value) ?></div></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card lg:col-span-2">
            <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-id-card mr-2" style="color:var(--green-mid, #136b55)"></i>KYC Documents</h3></div>
            <div class="card-body">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="info-label mb-2">National ID – Front</div>
                        <?php if (!empty($member['id_front'])): ?>
                            <div class="kyc-doc-card">
                                <?php $frontExt = strtolower(pathinfo($member['id_front'], PATHINFO_EXTENSION)); ?>
                                <?php if (in_array($frontExt, ['jpg','jpeg','png','webp'])): ?>
                                    <img src="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_front']) ?>" onclick="window.open(this.src)" class="cursor-pointer">
                                <?php else: ?>
                                    <i class="fa-regular fa-file-pdf"></i>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <div class="text-sm font-medium truncate"><?= htmlspecialchars(basename($member['id_front'])) ?></div>
                                    <a href="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_front']) ?>" target="_blank" class="text-xs text-green-600">View full</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-slate-400 text-sm bg-slate-50 rounded-lg p-3 text-center">Not uploaded</div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="info-label mb-2">National ID – Back</div>
                        <?php if (!empty($member['id_back'])): ?>
                            <div class="kyc-doc-card">
                                <?php $backExt = strtolower(pathinfo($member['id_back'], PATHINFO_EXTENSION)); ?>
                                <?php if (in_array($backExt, ['jpg','jpeg','png','webp'])): ?>
                                    <img src="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_back']) ?>" onclick="window.open(this.src)" class="cursor-pointer">
                                <?php else: ?>
                                    <i class="fa-regular fa-file-pdf"></i>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <div class="text-sm font-medium truncate"><?= htmlspecialchars(basename($member['id_back'])) ?></div>
                                    <a href="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_back']) ?>" target="_blank" class="text-xs text-green-600">View full</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-slate-400 text-sm bg-slate-50 rounded-lg p-3 text-center">Not uploaded</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Savings Tab -->
<div id="tab-savings" class="tab-panel">
    <div class="card">
        <div class="card-header"><h3 class="section-title">Savings Accounts</h3></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Account No</th><th>Type</th><th>Balance</th><th>Status</th><th>Opened</th></tr></thead>
                <tbody>
                <?php if (empty($savingsAccounts)): ?>
                    <tr><td colspan="5" class="text-center py-8 text-slate-400">No savings accounts found</td></tr>
                <?php else: foreach ($savingsAccounts as $sa): ?>
                    <tr>
                        <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($sa['account_no'] ?? '') ?></span></td>
                        <td><?= Format::titleCase($sa['account_type'] ?? '') ?></td>
                        <td class="font-bold text-emerald-700"><?= Format::currency((float)($sa['balance'] ?? 0)) ?></td>
                        <td><?= Format::statusPill($sa['status'] ?? '') ?></td>
                        <td class="text-xs text-slate-400"><?= Format::date($sa['opened_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Loans Tab -->
<div id="tab-loans" class="tab-panel">
    <div class="card">
        <div class="card-header"><h3 class="section-title">Loan History</h3></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Loan No</th><th>Product</th><th>Principal</th><th>Outstanding</th><th>Status</th><th>Applied</th></tr></thead>
                <tbody>
                <?php if (empty($loans)): ?>
                    <tr><td colspan="6" class="text-center py-8 text-slate-400"><i class="fa-solid fa-file-contract text-3xl block mb-2 text-slate-200"></i>No loans on record</td></tr>
                <?php else: foreach ($loans as $l): ?>
                    <tr>
                        <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($l['loan_no'] ?? '') ?></span></td>
                        <td class="text-sm"><?= htmlspecialchars($l['product_name'] ?? '') ?></td>
                        <td class="font-semibold"><?= Format::currency((float)($l['principal_amount'] ?? 0)) ?></td>
                        <td class="font-bold <?= ($l['balance_outstanding'] ?? 0) > 0 ? 'text-amber-600' : 'text-emerald-600' ?>"><?= Format::currency((float)($l['balance_outstanding'] ?? 0)) ?></td>
                        <td><?= Format::statusPill($l['status'] ?? '') ?></td>
                        <td class="text-xs text-slate-400"><?= Format::date($l['application_date'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Transactions Tab -->
<div id="tab-transactions" class="tab-panel">
    <div class="card">
        <div class="card-header"><h3 class="section-title">Recent Transactions</h3></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Ref</th><th>Type</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (empty($transactions)): ?>
                    <tr><td colspan="6" class="text-center py-8 text-slate-400">No transactions found</td></tr>
                <?php else: foreach ($transactions as $t): ?>
                    <tr>
                        <td><span class="font-mono text-xs"><?= htmlspecialchars($t['txn_ref'] ?? '') ?></span></td>
                        <td class="text-xs capitalize"><?= str_replace('_', ' ', $t['txn_type'] ?? '') ?></td>
                        <td class="font-bold <?= in_array($t['txn_type'] ?? '', ['deposit','loan_repayment']) ? 'text-emerald-700' : 'text-slate-700' ?>"><?= Format::currency((float)($t['amount'] ?? 0)) ?></td>
                        <td class="text-xs capitalize"><?= str_replace('_', ' ', $t['payment_method'] ?? '') ?></td>
                        <td class="text-xs text-slate-400"><?= Format::date($t['transaction_date'] ?? '') ?></td>
                        <td><?= Format::statusPill($t['status'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Shares Tab -->
<div id="tab-shares" class="tab-panel">
    <div class="card mb-5">
        <div class="card-header">
            <h3 class="section-title"><i class="fa-solid fa-chart-pie mr-2" style="color:var(--green-mid, #136b55)"></i>Share Holdings</h3>
        </div>
        <div class="card-body">
            <?php if ($shares): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                    <div class="stat-card text-center py-5 bg-emerald-50">
                        <div class="text-2xl font-black text-emerald-700"><?= number_format($shares['shares_held'] ?? 0) ?></div>
                        <div class="text-xs text-slate-400 mt-1">Shares Held</div>
                    </div>
                    <div class="stat-card text-center py-5 bg-blue-50">
                        <div class="text-2xl font-black text-blue-700"><?= Format::currency((float)($shares['total_invested'] ?? 0)) ?></div>
                        <div class="text-xs text-slate-400 mt-1">Total Invested</div>
                    </div>
                    <div class="stat-card text-center py-5 bg-amber-50">
                        <div class="text-2xl font-black text-amber-700"><?= ($shares['is_shareholder'] ?? 0) ? 'Yes' : 'No' ?></div>
                        <div class="text-xs text-slate-400 mt-1">Active Shareholder</div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-center text-slate-400 py-8">You do not hold any shares yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="section-title">Share Transaction History</h3></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Reference</th>
                    <th>Type</th>
                    <th>Shares</th>
                    <th>Amount</th>
                    <th class="hidden md:table-cell">Date</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($shareTxns)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-10">
                            <i class="fa-solid fa-chart-pie text-4xl text-slate-200 block mb-2"></i>
                            <p class="text-slate-400 text-sm">No share transactions yet</p>
                        </td>
                    </tr>
                <?php else: foreach ($shareTxns as $t):
                    $typeColors = [
                        'purchase'     => 'bg-emerald-100 text-emerald-700',
                        'sale'         => 'bg-red-100 text-red-700',
                        'transfer_in'  => 'bg-blue-100 text-blue-700',
                        'transfer_out' => 'bg-orange-100 text-orange-700',
                        'dividend'     => 'bg-amber-100 text-amber-700',
                    ];
                    $tc = $typeColors[$t['txn_type']] ?? 'bg-slate-100 text-slate-600';
                    ?>
                    <tr>
                        <td><span class="font-mono text-xs"><?= htmlspecialchars($t['txn_ref'] ?? '') ?></span></td>
                        <td>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $tc ?>">
                            <?= ucwords(str_replace('_', ' ', $t['txn_type'] ?? '')) ?>
                        </span>
                        </td>
                        <td class="font-black text-blue-700 text-base"><?= number_format($t['shares_qty'] ?? 0) ?></td>
                        <td class="font-semibold"><?= Format::currency((float)($t['total_amount'] ?? 0)) ?></td>
                        <td class="hidden md:table-cell text-xs text-slate-400">
                            <?= Format::date($t['transaction_date'] ?? '') ?>
                        </td>
                        <td><?= Format::statusPill($t['status'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Login Sessions Tab -->
<div id="tab-sessions" class="tab-panel">
    <div class="card">
        <div class="card-header"><h3 class="section-title">Recent Login Sessions</h3></div>
        <div class="divide-y divide-slate-100">
            <?php if (empty($sessions)): ?>
                <div class="px-5 py-8 text-center text-slate-400 text-sm">No recent login sessions</div>
            <?php else: foreach ($sessions as $s): ?>
                <div class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($s['ip_address'] ?? 'Unknown') ?></div>
                        <div class="text-xs text-slate-400"><?= Format::datetime($s['login_at'] ?? '') ?></div>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= ($s['status'] ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                        <?= ucfirst($s['status'] ?? 'unknown') ?>
                    </span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<script>
// ✅ Tab Switching Logic
function switchTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}
</script>