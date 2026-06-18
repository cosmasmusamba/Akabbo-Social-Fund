<?php
use App\Helpers\Format;
$pageTitle   = $pageTitle ?? 'Approval Queue';
$activePage  = $activePage ?? 'approvals';
$breadcrumbs = $breadcrumbs ?? ['Approvals' => null];
$result      = $result ?? ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0];
$stats       = $stats ?? ['pending' => 0, 'overdue' => 0, 'approved' => 0, 'rejected' => 0];
$filters     = $filters ?? ['status' => 'pending', 'reference_type' => ''];
?>
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Approval Queue</h1>
        <p class="text-sm text-slate-400 mt-0.5">Review and process pending financial requests</p>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
    <div class="stat-card border-l-4 border-amber-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Pending</div>
        <div class="text-2xl font-black text-amber-600 mt-1"><?= number_format($stats['pending'] ?? 0) ?></div>
    </div>
    <div class="stat-card border-l-4 border-red-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Overdue</div>
        <div class="text-2xl font-black text-red-600 mt-1"><?= number_format($stats['overdue'] ?? 0) ?></div>
    </div>
    <div class="stat-card border-l-4 border-emerald-500">
        <div class="text-xs font-semibold text-slate-400 uppercase">Approved</div>
        <div class="text-2xl font-black text-emerald-600 mt-1"><?= number_format($stats['approved'] ?? 0) ?></div>
    </div>
    <div class="stat-card border-l-4 border-slate-400">
        <div class="text-xs font-semibold text-slate-400 uppercase">Rejected</div>
        <div class="text-2xl font-black text-slate-600 mt-1"><?= number_format($stats['rejected'] ?? 0) ?></div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-5">
    <div class="card-body py-3">
        <form method="GET" class="flex flex-wrap gap-3 items-center">
            <select name="status" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'approved', 'rejected', 'cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
                <option value="">All Types</option>
                <?php
                $types = ['withdrawal', 'transfer', 'disbursement', 'expense', 'share_transaction', 'reversal', 'social_fund_waiver'];
                foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= ($filters['reference_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $t)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Apply</button>
            <?php if (!empty($filters['status']) || !empty($filters['reference_type'])): ?>
                <a href="<?= APP_URL ?>/approvals" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Clear</a>
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
                <th>Type</th>
                <th>Requester</th>
                <th class="text-right">Amount</th>
                <th class="hidden md:table-cell">Requested</th>
                <th class="hidden lg:table-cell">Due By</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($result['data'])): ?>
                <tr>
                    <td colspan="8" class="text-center py-12">
                        <i class="fa-solid fa-clipboard-check text-5xl text-slate-200 block mb-3"></i>
                        <p class="text-slate-400">No approvals found matching your filters</p>
                    </td>
                </tr>
            <?php else: foreach ($result['data'] as $a):
                $isOverdue = !empty($a['is_overdue']) && $a['status'] === 'pending';
            ?>
                <tr class="<?= $isOverdue ? 'bg-red-50/50' : '' ?>">
                    <td>
                        <span class="font-mono text-xs bg-slate-100 px-2 py-1 rounded border border-slate-200 text-slate-700">
                            <?= htmlspecialchars($a['reference_ref'] ?? '—') ?>
                        </span>
                    </td>
                    <td>
                        <span class="text-xs capitalize font-semibold text-slate-600">
                            <?= ucwords(str_replace('_', ' ', $a['reference_type'] ?? '')) ?>
                        </span>
                    </td>
                    <td>
                        <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($a['requested_by_name'] ?? '—') ?></div>
                        <div class="text-xs text-slate-400"><?= htmlspecialchars($a['requested_by_email'] ?? '') ?></div>
                    </td>
                    <td class="text-right font-bold text-slate-700">
                        <?= $a['amount'] !== null ? Format::currency((float)$a['amount']) : '—' ?>
                    </td>
                    <td class="hidden md:table-cell text-xs text-slate-500">
                        <?= Format::date($a['requested_at'] ?? '') ?><br>
                        <span class="text-slate-400"><?= Format::timeAgo($a['requested_at'] ?? '') ?></span>
                    </td>
                    <td class="hidden lg:table-cell text-xs">
                        <?php if ($a['due_by']): ?>
                            <span class="<?= $isOverdue ? 'text-red-600 font-bold' : 'text-slate-500' ?>">
                                <?= Format::date($a['due_by'], 'd M Y, H:i') ?>
                                <?php if ($isOverdue): ?>
                                    <span class="badge bg-red-100 text-red-700 border-red-200 text-[10px] ml-1">Overdue</span>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="text-slate-400">—</span>
                        <?php endif; ?>
                        <?php if (!empty($a['hours_waiting'])): ?>
                            <div class="text-[10px] text-slate-400 mt-0.5"><?= floor($a['hours_waiting']) ?>h waiting</div>
                        <?php endif; ?>
                    </td>
                    <td><?= Format::statusPill($a['status'] ?? 'pending') ?></td>
                    <td class="text-right">
                        <div class="flex gap-1 justify-end">
                            <a href="<?= APP_URL ?>/approvals/<?= $a['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2" title="View Details">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </a>
                            <?php if ($a['status'] === 'pending' && $auth->can('approvals.process')): ?>
                                <a href="<?= APP_URL ?>/approvals/<?= $a['id'] ?>" class="btn btn-sm py-1 px-2" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:0.7rem;font-weight:700;" title="Process Request">
                                    <i class="fa-solid fa-gavel"></i> Process
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (!empty($result['data'])):
        $page = $result['page'] ?? 1;
        $lastPage = $result['last_page'] ?? 1;
        $qs = http_build_query(array_diff_key($_GET, ['page' => '']));
        $base = APP_URL . '/approvals?' . ($qs ? $qs . '&' : '');
    ?>
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-400">
                Showing <strong><?= $result['from'] ?? 1 ?></strong>–<strong><?= $result['to'] ?? count($result['data']) ?></strong> of <strong><?= number_format($result['total']) ?></strong>
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