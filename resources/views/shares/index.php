<?php
use App\Helpers\Format;

$pageTitle   = 'Shares & Shareholders';
$activePage  = 'shares';
$breadcrumbs = ['Shares' => null];

$stats  = $stats ?? [];
$config = $config ?? ['par_value' => 1000, 'loan_rate_discount' => 2, 'loan_multiplier_bonus' => 1, 'dividend_rate' => 5];
$result = $result ?? ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0];
?>

<style>
.share-hero { background: linear-gradient(135deg, #0a4033, #136b55); border-radius: 16px; padding: 22px 26px; color: #fff; margin-bottom: 20px; }
.privilege-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; }
</style>

<!-- Header -->
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Shares & Shareholders</h1>
        <p class="text-sm text-slate-400 mt-0.5">
            <?= number_format($stats['total_shareholders'] ?? 0) ?> shareholders · 
            <?= number_format($stats['total_shares_issued'] ?? 0) ?> total shares issued
        </p>
    </div>
    <div class="flex gap-2">
        <a href="<?= APP_URL ?>/shares/report" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-chart-pie"></i> Analytics
        </a>
        <?php if ($auth->can('shares.manage')): ?>
            <a href="<?= APP_URL ?>/shares/create" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Issue Shares
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- KPI strip -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <?php
    $kpis = [
        ['Shareholders',    number_format($stats['total_shareholders'] ?? 0), 'fa-users', 'text-emerald-700'],
        ['Shares Issued',   number_format($stats['total_shares_issued'] ?? 0), 'fa-certificate', 'text-blue-700'],
        ['Share Capital',   Format::currencyCompact((float)($stats['total_share_capital'] ?? 0)), 'fa-coins', 'text-amber-700'],
        ['Par Value/Share', Format::currency((float)($config['par_value'] ?? 1000)), 'fa-tag', 'text-purple-700'],
    ];
    foreach ($kpis as [$label, $val, $icon, $color]): ?>
        <div class="stat-card text-center py-5">
            <i class="fa-solid <?= $icon ?> text-2xl <?= $color ?> mb-2 block"></i>
            <div class="text-xl font-black <?= $color ?>"><?= $val ?></div>
            <div class="text-xs text-slate-400 mt-1 font-semibold"><?= $label ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Shareholder privileges info -->
<div class="card mb-5">
    <div class="card-header">
        <h3 class="section-title"><i class="fa-solid fa-star mr-2" style="color:var(--gold)"></i>Shareholder Loan Privileges</h3>
        <?php if ($auth->can('shares.manage')): ?>
            <button onclick="openConfigModal()" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-sliders"></i> Configure
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div class="flex items-start gap-3 p-4 rounded-xl" style="background:#f0fdf9;border:1px solid #a7f3d0">
                <i class="fa-solid fa-percent text-emerald-600 text-xl mt-0.5"></i>
                <div>
                    <div class="font-bold text-slate-800"><?= Format::percentage((float)($config['loan_rate_discount'] ?? 2)) ?> Rate Discount</div>
                    <div class="text-xs text-slate-500 mt-0.5">Reduced off standard loan interest rate for all shareholders</div>
                </div>
            </div>
            <div class="flex items-start gap-3 p-4 rounded-xl" style="background:#eff6ff;border:1px solid #bfdbfe">
                <i class="fa-solid fa-arrow-trend-up text-blue-600 text-xl mt-0.5"></i>
                <div>
                    <div class="font-bold text-slate-800">+<?= (int)($config['loan_multiplier_bonus'] ?? 1) ?>x Loan Multiplier</div>
                    <div class="text-xs text-slate-500 mt-0.5">Higher maximum loan relative to savings balance</div>
                </div>
            </div>
            <div class="flex items-start gap-3 p-4 rounded-xl" style="background:#fdf4ff;border:1px solid #e9d5ff">
                <i class="fa-solid fa-hand-holding-dollar text-purple-600 text-xl mt-0.5"></i>
                <div>
                    <div class="font-bold text-slate-800"><?= Format::percentage((float)($config['dividend_rate'] ?? 5)) ?> Dividend Rate</div>
                    <div class="text-xs text-slate-500 mt-0.5">Annual dividend on total share capital invested</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="flex gap-3">
            <div class="relative flex-1">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                       placeholder="Search shareholder name or member number…"
                       class="form-control pl-9 py-2 text-sm">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if (!empty($_GET['search'])): ?>
                <a href="<?= APP_URL ?>/shares" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Shareholders table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Member No</th>
                    <th class="hidden md:table-cell">Shares Held</th>
                    <th class="hidden md:table-cell">Total Invested</th>
                    <th class="hidden lg:table-cell">Share Date</th>
                    <th>Privilege</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr>
                        <td colspan="8" class="text-center py-12">
                            <i class="fa-solid fa-certificate text-5xl text-slate-200 block mb-3"></i>
                            <p class="text-slate-400 font-semibold">No shareholders yet</p>
                            <?php if ($auth->can('shares.manage')): ?>
                                <a href="<?= APP_URL ?>/shares/create" class="btn btn-primary btn-sm mt-3">
                                    <i class="fa-solid fa-plus"></i> Issue First Shares
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: foreach ($result['data'] as $s): ?>
                    <tr>
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="avatar-circle w-8 h-8 text-xs">
                                    <?= Format::initials($s['full_name'] ?? 'U') ?>
                                </div>
                                <div class="font-semibold text-sm text-slate-800"><?= htmlspecialchars($s['full_name'] ?? 'Unknown') ?></div>
                            </div>
                        </td>
                        <td><span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded"><?= htmlspecialchars($s['member_no'] ?? '') ?></span></td>
                        <td class="hidden md:table-cell font-bold text-slate-800"><?= number_format($s['shares_held'] ?? 0) ?></td>
                        <td class="hidden md:table-cell font-semibold text-emerald-700"><?= Format::currency((float)($s['total_invested'] ?? 0)) ?></td>
                        <td class="hidden lg:table-cell text-xs text-slate-400"><?= Format::date($s['share_date'] ?? '') ?></td>
                        <td>
                            <span class="privilege-badge" style="background:#fef9c3;color:#854d0e;border:1px solid #fde047">
                                <i class="fa-solid fa-star text-xs"></i> Shareholder
                            </span>
                        </td>
                        <td><?= Format::statusPill($s['status'] ?? 'active') ?></td>
                        <td>
                            <a href="<?= APP_URL ?>/shares/member/<?= $s['member_id'] ?? 0 ?>" class="btn btn-secondary btn-sm py-1 px-2.5">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (!empty($result['data'])): 
        $page = $result['page'] ?? 1; 
        $lastPage = $result['last_page'] ?? 1; 
        $qs = http_build_query(array_diff_key($_GET, ['page' => ''])); 
        $base = APP_URL . '/shares?' . ($qs ? $qs . '&' : ''); 
    ?>
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-400">
                Showing <strong><?= $result['from'] ?? 1 ?></strong>–<strong><?= $result['to'] ?? count($result['data']) ?></strong>
                of <strong><?= number_format($result['total']) ?></strong>
            </p>
            <div class="flex gap-1">
                <a href="<?= $base ?>page=<?= max(1, $page - 1) ?>" class="pager-btn <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>"><i class="fa-solid fa-chevron-left text-xs"></i></a>
                <?php for ($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++): ?>
                    <a href="<?= $base ?>page=<?= $p ?>" class="pager-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="<?= $base ?>page=<?= min($lastPage, $page + 1) ?>" class="pager-btn <?= $page >= $lastPage ? 'opacity-40 pointer-events-none' : '' ?>"><i class="fa-solid fa-chevron-right text-xs"></i></a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Config modal -->
<?php if ($auth->can('shares.manage')): ?>
<div id="configModal" class="hidden modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3 class="font-bold text-slate-800"><i class="fa-solid fa-sliders mr-2"></i>Share Configuration</h3>
            <button onclick="closeConfigModal()" class="text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="modal-body">
            <form id="configForm">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label required">Par Value per Share (<?= $settings['currency_symbol'] ?? 'USh' ?>)</label>
                        <input type="number" name="par_value" value="<?= $config['par_value'] ?? 1000 ?>" class="form-control" required min="100" step="100">
                    </div>
                    <div>
                        <label class="form-label required">Interest Rate Discount (%)</label>
                        <input type="number" name="loan_rate_discount" value="<?= $config['loan_rate_discount'] ?? 2 ?>" class="form-control" required step="0.5" min="0" max="20">
                    </div>
                    <div>
                        <label class="form-label required">Loan Multiplier Bonus</label>
                        <input type="number" name="loan_multiplier_bonus" value="<?= $config['loan_multiplier_bonus'] ?? 1 ?>" class="form-control" required min="0" max="5">
                    </div>
                    <div>
                        <label class="form-label required">Annual Dividend Rate (%)</label>
                        <input type="number" name="dividend_rate" value="<?= $config['dividend_rate'] ?? 5 ?>" class="form-control" required step="0.5" min="0" max="50">
                    </div>
                    <div>
                        <label class="form-label">Min Shares</label>
                        <input type="number" name="min_shares" value="<?= $config['min_shares'] ?? 1 ?>" class="form-control" min="1">
                    </div>
                    <div>
                        <label class="form-label">Max Shares per Member</label>
                        <input type="number" name="max_shares_per_member" value="<?= $config['max_shares_per_member'] ?? 1000 ?>" class="form-control" min="1">
                    </div>
                </div>
                <div class="flex items-center gap-2 mt-3">
                    <input type="checkbox" name="is_transferable" value="1" id="isTransferable"
                           <?= ($config['is_transferable'] ?? 0) ? 'checked' : '' ?>
                           style="accent-color:var(--green-mid);width:16px;height:16px">
                    <label for="isTransferable" class="text-sm text-slate-700">Allow share transfers between members</label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button onclick="closeConfigModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="saveConfig()" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Config</button>
        </div>
    </div>
</div>

<script>
function openConfigModal()  { document.getElementById('configModal').classList.remove('hidden'); }
function closeConfigModal() { document.getElementById('configModal').classList.add('hidden'); }

async function saveConfig() {
    const fd = new FormData(document.getElementById('configForm'));
    try {
        const r = await fetch('<?= APP_URL ?>/shares/config', {
            method: 'POST', headers: {'X-Requested-With':'XMLHttpRequest'}, body: fd
        });
        const d = await r.json();
        if (d.success) { 
            window.showToast('success', d.message); 
            closeConfigModal(); 
            setTimeout(() => location.reload(), 900); 
        } else {
            window.showToast('error', d.message);
        }
    } catch(e) { 
        window.showToast('error', 'Request failed.'); 
    }
}
</script>
<?php endif; ?>