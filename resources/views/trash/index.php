<?php
use App\Helpers\Format;
$pageTitle   = 'Trash';
$activePage  = 'trash';
$breadcrumbs = ['Trash' => null];
$result      = $result ?? ['data' => [], 'total' => 0, 'page' => 1, 'last_page' => 1, 'from' => 0, 'to' => 0];
$type        = $type ?? '';
?>
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Trash</h1>
        <p class="text-sm text-slate-400 mt-0.5">Deleted records — recoverable until permanently removed</p>
    </div>
    <div class="flex gap-2 flex-wrap">
        <?php 
        $filterTypes = [
            ''                   => 'All', 
            'members'            => 'Members', 
            'loans'              => 'Loans', 
            'transactions'       => 'Txns', 
            'savings_groups'     => 'Groups', 
            'expenses'           => 'Expenses',
            'users'              => 'Users',
            'share_transactions' => 'Shares',
            'fund_transfers'     => 'Transfers'
        ];
        foreach ($filterTypes as $val => $label): 
        ?>
            <a href="?type=<?= $val ?>" class="btn <?= ($type === $val) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Record Details</th>
                    <th class="hidden md:table-cell">Deleted By</th>
                    <th class="hidden md:table-cell">Deleted At</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($result['data'])): ?>
                    <tr>
                        <td colspan="5" class="text-center py-12">
                            <i class="fa-solid fa-trash-can text-5xl text-slate-200 block mb-3"></i>
                            <p class="text-slate-400 font-semibold">Trash is empty</p>
                            <p class="text-xs text-slate-300 mt-1">Deleted records will appear here</p>
                        </td>
                    </tr>
                <?php else: foreach ($result['data'] as $item): 
                    $data = json_decode($item['record_data'] ?? '{}', true);
                    
                    $recordType = $item['record_type'] ?? '';
                    $label = match ($recordType) {
                        'member', 'members'                 => ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''),
                        'loan', 'loans'                     => $data['loan_no'] ?? '',
                        'transaction', 'transactions'       => $data['txn_ref'] ?? '',
                        'group', 'savings_groups'           => $data['name'] ?? '',
                        'expense', 'expenses'               => $data['expense_ref'] ?? '',
                        'user', 'users'                     => $data['email'] ?? ($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''),
                        'share_transaction', 'share_transactions' => $data['txn_ref'] ?? '',
                        'fund_transfer', 'fund_transfers'   => $data['transfer_ref'] ?? '',
                        default                             => '#' . ($item['record_id'] ?? '')
                    };

                    // Color coding for record types
                    $typeColor = match ($recordType) {
                        'member', 'members' => 'bg-blue-100 text-blue-700',
                        'loan', 'loans' => 'bg-purple-100 text-purple-700',
                        'transaction', 'transactions' => 'bg-emerald-100 text-emerald-700',
                        'expense', 'expenses' => 'bg-orange-100 text-orange-700',
                        'user', 'users' => 'bg-slate-100 text-slate-700',
                        'share_transaction', 'share_transactions' => 'bg-indigo-100 text-indigo-700',
                        'fund_transfer', 'fund_transfers' => 'bg-amber-100 text-amber-700',
                        default => 'bg-red-100 text-red-700'
                    };
                ?>
                    <tr>
                        <td>
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $typeColor ?>">
                                <?= ucfirst(str_replace('_', ' ', $recordType)) ?>
                            </span>
                        </td>
                        <td>
                            <div class="font-semibold text-sm text-slate-800"><?= htmlspecialchars(trim($label) ?: 'Unknown Record') ?></div>
                            <div class="text-xs text-slate-400">ID: <?= (int)($item['record_id'] ?? 0) ?></div>
                        </td>
                        <td class="hidden md:table-cell text-sm text-slate-600"><?= htmlspecialchars($item['deleted_by_name'] ?? 'System') ?></td>
                        <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::datetime($item['deleted_at'] ?? date('Y-m-d H:i:s')) ?></td>
                        <td class="text-right">
                            <div class="flex gap-2 justify-end">
                                <button onclick="restoreRecord('<?= htmlspecialchars($recordType) ?>', <?= (int)$item['record_id'] ?>, '<?= addslashes(trim($label)) ?>')" 
                                        class="btn btn-sm py-1 px-3" style="background:#dcfce7;color:#166534;border:1px solid #a7f3d0;font-size:0.78rem">
                                    <i class="fa-solid fa-rotate-left mr-1"></i> Restore
                                </button>
                                <button onclick="destroyRecord('<?= htmlspecialchars($recordType) ?>', <?= (int)$item['record_id'] ?>, '<?= addslashes(trim($label)) ?>')" 
                                        class="btn btn-danger btn-sm py-1 px-3" style="font-size:0.78rem" title="Permanently Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
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
        $base = APP_URL . '/trash?' . ($qs ? $qs . '&' : '');
    ?>
    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-xs text-slate-400">
            Showing <strong><?= $result['from'] ?? 1 ?></strong>–<strong><?= $result['to'] ?? count($result['data']) ?></strong> 
            of <strong><?= number_format($result['total']) ?></strong>
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

<script>
async function restoreRecord(type, id, label) {
    window.confirmAction({
        title: 'Restore Record',
        message: `Are you sure you want to restore <strong>${label || type + ' #' + id}</strong>? It will be immediately available in the system.`,
        confirmText: 'Restore',
        type: 'primary',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            
            try {
                const r = await fetch(`<?= APP_URL ?>/trash/${type}/${id}/restore`, {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: fd
                });
                const d = await r.json();
                if (d.success) {
                    window.showToast('success', d.message);
                    setTimeout(() => location.reload(), 900);
                } else {
                    window.showToast('error', d.message);
                }
            } catch (e) {
                window.showToast('error', 'Network error. Please try again.');
            }
        }
    });
}

function destroyRecord(type, id, label) {
    window.confirmAction({
        title: 'Permanently Delete',
        message: `Are you absolutely sure you want to PERMANENTLY delete <strong>${label || type + ' #' + id}</strong>?<br><span class="text-red-600 font-bold">This action CANNOT be undone. The record will be permanently removed from the database, though a permanent audit trail of its existence will remain.</span>`,
        confirmText: 'Delete Forever',
        type: 'danger',
        onConfirm: async () => {
            const fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
            
            try {
                const r = await fetch(`<?= APP_URL ?>/trash/${type}/${id}/destroy`, {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: fd
                });
                const d = await r.json();
                if (d.success) {
                    window.showToast('success', d.message);
                    setTimeout(() => location.reload(), 900);
                } else {
                    window.showToast('error', d.message);
                }
            } catch (e) {
                window.showToast('error', 'Network error.');
            }
        }
    });
}
</script>