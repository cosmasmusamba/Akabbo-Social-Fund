<?php
use App\Helpers\Format;
$pageTitle   = 'Audit Log';
$activePage  = 'audit';
$breadcrumbs = ['Audit Log' => null];
$filters     = $filters ?? ['from' => date('Y-m-01'), 'to' => date('Y-m-d'), 'search' => '', 'module' => ''];
$result      = $result ?? ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0];
$modules     = $modules ?? [];
?>
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">System Audit Log</h1>
        <p class="text-sm text-slate-400 mt-0.5">Complete trail of all system actions — <?= number_format($result['total'] ?? 0) ?> entries</p>
    </div>
    <a href="?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-file-csv text-green-600"></i> Export
    </a>
</div>

<div class="card mb-5">
    <div class="card-body py-3">
        <form method="GET" class="flex flex-wrap gap-3">
            <div class="relative flex-1 min-w-[160px]">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="User, action, IP…" class="form-control pl-9 py-2 text-sm">
            </div>
            <select name="module" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?>
                    <option value="<?= htmlspecialchars($m['module'] ?? '') ?>" <?= ($filters['module'] ?? '') === ($m['module'] ?? '') ? 'selected' : '' ?>>
                        <?= ucfirst($m['module'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? date('Y-m-01')) ?>" class="form-control py-2 text-sm w-auto">
            <input type="date" name="to" value="<?= htmlspecialchars($filters['to'] ?? date('Y-m-d')) ?>" class="form-control py-2 text-sm w-auto">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="<?= APP_URL ?>/audit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i></a>
        </form>
    </div>
</div>

<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Action</th>
                    <th class="hidden md:table-cell">Module</th>
                    <th class="hidden lg:table-cell">Description</th>
                    <th class="hidden md:table-cell">IP</th>
                    <th>Time</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr>
                        <td colspan="8" class="text-center py-12">
                            <i class="fa-solid fa-shield-halved text-5xl text-slate-200 block mb-3"></i>
                            <p class="text-slate-400">No audit entries found</p>
                        </td>
                    </tr>
                <?php else: foreach ($result['data'] as $log): 
                    $sev = $log['severity'] ?? 'info';
                    $sc = [
                        'info' => 'bg-slate-100 text-slate-600',
                        'warning' => 'bg-amber-100 text-amber-700',
                        'critical' => 'bg-red-100 text-red-700'
                    ];
                ?>
                    <tr>
                        <td class="text-xs text-slate-400"><?= (int)($log['id'] ?? 0) ?></td>
                        <td class="text-sm font-semibold"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></td>
                        <td>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $sc[$sev] ?? $sc['info'] ?>">
                                <?= htmlspecialchars(str_replace('_', ' ', $log['action'] ?? '')) ?>
                            </span>
                        </td>
                        <td class="hidden md:table-cell text-xs capitalize text-slate-500"><?= htmlspecialchars($log['module'] ?? '') ?></td>
                        <td class="hidden lg:table-cell text-xs text-slate-500"><?= Format::truncate($log['description'] ?? '', 60) ?></td>
                        <td class="hidden md:table-cell text-xs font-mono text-slate-400"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                        <td class="text-xs text-slate-400"><?= Format::timeAgo($log['created_at'] ?? date('Y-m-d H:i:s')) ?></td>
                        <td>
                            <a href="<?= APP_URL ?>/audit/<?= (int)$log['id'] ?>" class="btn btn-secondary btn-sm py-1 px-2">
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
        $base = APP_URL . '/audit?' . ($qs ? $qs . '&' : ''); 
    ?>
        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-400">
                Showing <strong><?= $result['from'] ?? 1 ?></strong>–<strong><?= $result['to'] ?? count($result['data']) ?></strong> of <strong><?= number_format($result['total']) ?></strong>
            </p>
            <div class="flex gap-1">
                <a href="<?= $base ?>page=<?= max(1, $page - 1) ?>" class="pager-btn <?= $page <= 1 ? 'opacity-40 pointer-events-none' : '' ?>">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </a>
                <?php for ($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++): ?>
                    <a href="<?= $base ?>page=<?= $p ?>" class="pager-btn <?= $p == $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <a href="<?= $base ?>page=<?= min($lastPage, $page + 1) ?>" class="pager-btn <?= $page >= $lastPage ? 'opacity-40 pointer-events-none' : '' ?>">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>