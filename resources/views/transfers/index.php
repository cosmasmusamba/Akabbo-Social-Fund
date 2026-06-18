<?php
use App\Helpers\Format;

$pageTitle   = 'Fund Transfers';
$activePage  = 'transfers';
$breadcrumbs = ['Transfers' => null];
?>

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Fund Transfers</h1>
        <p class="text-sm text-slate-400 mt-0.5"><?= number_format($result['total'] ?? 0) ?> transfers recorded</p>
    </div>
    <?php if ($auth->can('transfers.create')): ?>
        <a href="<?= APP_URL ?>/transfers/create" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-right-left"></i> New Transfer
        </a>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <?php 
    $kpis = [
        ['Pending',    number_format($stats['pending'] ?? 0), 'text-amber-700', 'bg-amber-50'],
        ['Completed',  number_format($stats['completed'] ?? 0), 'text-emerald-700', 'bg-emerald-50'],
        ['Rejected',   number_format($stats['rejected'] ?? 0), 'text-red-700', 'bg-red-50'],
        ['Transferred', Format::currencyCompact((float)($stats['total_transferred'] ?? 0)), 'text-blue-700', 'bg-blue-50'],
    ];
    foreach ($kpis as [$l, $v, $cl, $bg]): 
    ?>
    <div class="stat-card text-center py-5 <?= $bg ?>">
        <div class="text-xl font-black <?= $cl ?>"><?= $v ?></div>
        <div class="text-xs text-slate-500 mt-1"><?= $l ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-[180px]">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search ref, member…" class="form-control pl-9 py-2 text-sm">
            </div>
            <select name="status" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['pending' => 'Pending', 'completed' => 'Completed', 'rejected' => 'Rejected', 'reversed' => 'Reversed'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= ($_GET['status'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <?php if (!empty($_GET['search']) || !empty($_GET['status'])): ?>
                <a href="<?= APP_URL ?>/transfers" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Amount</th>
                    <th class="hidden md:table-cell">Description</th>
                    <th class="hidden md:table-cell">Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr>
                        <td colspan="8" class="text-center py-12">
                            <i class="fa-solid fa-right-left text-5xl text-slate-200 block mb-3"></i>
                            <p class="text-slate-400">No transfers found</p>
                        </td>
                    </tr>
                <?php else: foreach ($result['data'] as $t): ?>
                    <tr>
                        <td>
                            <span class="font-mono text-xs bg-slate-100 px-2 py-0.5 rounded">
                                <?= htmlspecialchars($t['transfer_ref'] ?? '') ?>
                            </span>
                        </td>
                        <td>
                            <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($t['from_name'] ?? '—') ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($t['from_no'] ?? '') ?></div>
                        </td>
                        <td>
                            <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($t['to_name'] ?? '—') ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars($t['to_no'] ?? '') ?></div>
                        </td>
                        <td class="font-bold text-slate-800"><?= Format::currency((float)($t['amount'] ?? 0)) ?></td>
                        <td class="hidden md:table-cell text-xs text-slate-500">
                            <?= htmlspecialchars(Format::truncate($t['description'] ?? '', 40)) ?>
                        </td>
                        <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::date($t['transfer_date'] ?? '') ?></td>
                        <td><?= Format::statusPill($t['status'] ?? 'pending') ?></td>
                        <td>
                            <a href="<?= APP_URL ?>/transfers/<?= $t['id'] ?? 0 ?>" class="btn btn-secondary btn-sm py-1 px-2.5" title="View details">
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
        $lp = $result['last_page'] ?? 1;
        $qs = http_build_query(array_diff_key($_GET, ['page' => '']));
        $base = APP_URL . '/transfers?' . ($qs ? $qs . '&' : ''); 
    ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-400">
            Showing <?= $result['from'] ?? 1 ?>–<?= $result['to'] ?? 0 ?> of <?= number_format($result['total']) ?>
        </p>
        <div class="flex gap-1">
            <a href="<?= $base ?>page=<?= max(1, $page - 1) ?>" class="pager-btn <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">
                <i class="fa-solid fa-chevron-left text-xs"></i>
            </a>
            <?php for ($p = max(1, $page - 2); $p <= min($lp, $page + 2); $p++): ?>
                <a href="<?= $base ?>page=<?= $p ?>" class="pager-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="<?= $base ?>page=<?= min($lp, $page + 1) ?>" class="pager-btn <?= $page >= $lp ? 'opacity-40 pointer-events-none' : '' ?>">
                <i class="fa-solid fa-chevron-right text-xs"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>