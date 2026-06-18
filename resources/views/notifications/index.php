<?php
use App\Helpers\Format;
$pageTitle   = 'Notifications';
$activePage  = 'notifications';
$breadcrumbs = ['Notifications' => null];
$unreadNotifications = $unreadNotifications ?? 0;
$result = $result ?? ['data' => []];
?>
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Notifications</h1>
        <p class="text-sm text-slate-400 mt-0.5"><?= $unreadNotifications ?> unread</p>
    </div>
    <?php if ($unreadNotifications > 0): ?>
        <button onclick="markAllRead()" class="btn btn-secondary btn-sm">
            <i class="fa-regular fa-check-double"></i> Mark All Read
        </button>
    <?php endif; ?>
</div>

<div class="card divide-y divide-slate-100">
    <?php if (empty($result['data'])): ?>
        <div class="px-6 py-12 text-center">
            <i class="fa-regular fa-bell text-5xl text-slate-200 block mb-3"></i>
            <p class="text-slate-400 font-semibold">No notifications yet</p>
        </div>
    <?php else: foreach ($result['data'] as $n): 
        $typeColors = [
            'loan_application' => 'bg-blue-100 text-blue-600',
            'loan_approved'    => 'bg-emerald-100 text-emerald-600',
            'loan_rejected'    => 'bg-red-100 text-red-600',
            'loan_disbursed'   => 'bg-purple-100 text-purple-600',
            'broadcast'        => 'bg-amber-100 text-amber-600'
        ];
        $tc = $typeColors[$n['type'] ?? ''] ?? 'bg-slate-100 text-slate-500';
        
        $typeIcons = [
            'loan_application' => 'fa-file-contract',
            'loan_approved'    => 'fa-check-circle',
            'loan_rejected'    => 'fa-times-circle',
            'loan_disbursed'   => 'fa-paper-plane',
            'broadcast'        => 'fa-bullhorn'
        ];
        $ti = $typeIcons[$n['type'] ?? ''] ?? 'fa-bell';
    ?>
        <div class="flex items-start gap-4 px-5 py-4 <?= !$n['is_read'] ? 'bg-blue-50/40' : '' ?> hover:bg-slate-50 transition-colors">
            <div class="w-10 h-10 rounded-full <?= $tc ?> flex items-center justify-center flex-shrink-0 mt-0.5">
                <i class="fa-solid <?= $ti ?> text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="font-semibold text-slate-800 text-sm <?= !$n['is_read'] ? 'font-bold' : '' ?>">
                            <?= htmlspecialchars($n['title'] ?? '') ?>
                        </div>
                        <div class="text-sm text-slate-500 mt-0.5">
                            <?= htmlspecialchars($n['message'] ?? '') ?>
                        </div>
                    </div>
                    <?php if (!$n['is_read']): ?>
                        <button onclick="markRead(<?= (int)$n['id'] ?>, this)" class="text-xs text-blue-600 hover:underline flex-shrink-0 mt-0.5">Mark read</button>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-slate-400 mt-1"><?= Format::timeAgo($n['created_at'] ?? date('Y-m-d H:i:s')) ?></div>
            </div>
            <?php if (!$n['is_read']): ?>
                <div class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0 mt-2"></div>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<script>
async function markRead(id, btn) {
    const fd = new FormData(); 
    fd.append('id', id); 
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
    const r = await fetch('<?= APP_URL ?>/notifications/mark-read', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: fd
    });
    const d = await r.json(); 
    if (d.success) {
        btn.closest('.flex').classList.remove('bg-blue-50/40');
        btn.remove();
        const dot = btn.parentElement?.querySelector('.rounded-full.bg-blue-500');
        dot?.remove();
    }
}

async function markAllRead() {
    const fd = new FormData(); 
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
    const r = await fetch('<?= APP_URL ?>/notifications/mark-all-read', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: fd
    });
    const d = await r.json(); 
    if (d.success) {
        window.showToast('success', d.message);
        setTimeout(() => location.reload(), 800);
    }
}
</script>