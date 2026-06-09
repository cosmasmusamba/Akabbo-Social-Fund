<?php use App\Helpers\Format; ?>

<div class="flex items-center justify-between mb-5">
  <div class="breadcrumb">
    <a href="<?= APP_URL ?>/groups">Groups</a>
    <span class="sep">/</span>
    <span class="current"><?= htmlspecialchars($group['name']) ?></span>
  </div>
  <div class="flex gap-2">
    <?php if ($auth->can('members.edit')): ?>
    <a href="<?= APP_URL ?>/groups/<?= $group['id'] ?>/edit" class="btn btn-secondary btn-sm">
      <i class="fa-solid fa-pen"></i> Edit Group
    </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/members?group=<?= $group['id'] ?>" class="btn btn-primary btn-sm">
      <i class="fa-solid fa-users"></i> All Members
    </a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

  <!-- Left: Info card -->
  <div class="space-y-4">
    <div class="card">
      <div class="card-body text-center py-7" style="background:linear-gradient(135deg,#f0fdf9,#ecfdf5);border-radius:14px;">
        <?php if (!empty($group['avatar'])): ?>
            <img src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($group['avatar']) ?>" 
                 class="w-20 h-20 rounded-2xl object-cover mx-auto mb-3 border-2 border-white shadow-md">
        <?php else: ?>
            <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-white font-black text-3xl mx-auto mb-3"
                 style="background:linear-gradient(135deg,#136b55,#1a8f6f)">
                <?= strtoupper(substr($group['name'], 0, 2)) ?>
            </div>
        <?php endif; ?>
        <h2 class="text-xl font-bold text-slate-800 mb-1"><?= htmlspecialchars($group['name']) ?></h2>
        <div class="text-xs text-slate-400 font-mono mb-2"><?= htmlspecialchars($group['group_code'] ?? '') ?></div>
        <?= Format::statusPill($group['status']) ?>
    </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="section-title">Group Details</h3>
      </div>
      <div class="card-body">
        <?php
        $details = [
            ['Description', $group['description'] ?? '—'],
            ['Meeting',     $group['meeting_schedule'] ?? '—'],
            ['Created',     Format::date($group['created_at'] ?? '')],
        ];
        foreach ($details as [$label, $value]):
        ?>
        <div class="flex justify-between py-2.5 border-b border-slate-50 last:border-0">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide"><?= $label ?></span>
          <span class="text-sm font-medium text-slate-700 text-right max-w-[170px] break-words">
            <?= htmlspecialchars($value) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Summary stats -->
    <div class="card">
      <div class="card-header"><h3 class="section-title">Financial Summary</h3></div>
      <div class="card-body">
        <?php
        $totalSavings = array_sum(array_column($members, 'total_savings'));
        $kpis = [
          ['Members',      count($members),                'text-slate-800'],
          ['Total Savings', Format::currency($totalSavings), 'text-emerald-700'],
        ];
        foreach ($kpis as [$label, $val, $color]):
        ?>
        <div class="flex justify-between py-2.5 border-b border-slate-50 last:border-0">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide"><?= $label ?></span>
          <span class="text-sm font-bold <?= $color ?>"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Right: Members table -->
  <div class="lg:col-span-2">
    <div class="card">
      <div class="card-header">
        <h3 class="section-title">
          <i class="fa-solid fa-users mr-1.5" style="color:var(--green-mid)"></i>
          Members (<?= count($members) ?>)
        </h3>
        <?php if ($auth->can('members.create')): ?>
        <a href="<?= APP_URL ?>/members/create" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-user-plus"></i> Add Member
        </a>
        <?php endif; ?>
      </div>
      <div class="overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>Member</th>
              <th class="hidden sm:table-cell">Phone</th>
              <th>Savings</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($members)): ?>
            <tr>
              <td colspan="5" class="text-center py-12">
                <i class="fa-regular fa-users text-4xl text-slate-200 block mb-2"></i>
                <p class="text-slate-400 text-sm">No members in this group yet</p>
                <?php if ($auth->can('members.create')): ?>
                <a href="<?= APP_URL ?>/members/create" class="btn btn-primary btn-sm mt-3">
                  <i class="fa-solid fa-user-plus"></i> Add Member
                </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php else: foreach ($members as $m): ?>
            <tr>
              <td>
                <div class="flex items-center gap-2.5">
                  <div class="avatar-circle w-8 h-8 text-xs">
                    <?php if ($m['avatar']): ?>
                      <img src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($m['avatar']) ?>"
                           class="w-9 h-9 rounded-full object-cover border-2 border-slate-100" alt="">
                    <?php else: ?>
                      <div class="avatar-circle w-9 h-9 text-xs">
                        <?= Format::initials($m['first_name'].' '.$m['last_name']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="font-semibold text-sm text-slate-800">
                      <?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?>
                    </div>
                    <div class="text-xs text-slate-400"><?= htmlspecialchars($m['member_no']) ?></div>
                  </div>
                </div>
              </td>
              <td class="hidden sm:table-cell text-sm"><?= htmlspecialchars($m['phone']) ?></td>
              <td class="font-semibold text-emerald-700">
                <?= Format::currency((float)($m['total_savings'] ?? 0)) ?>
              </td>
              <td><?= Format::statusPill($m['status']) ?></td>
              <td>
                <a href="<?= APP_URL ?>/members/<?= $m['id'] ?>"
                   class="btn btn-secondary btn-sm py-1 px-2.5">
                  <i class="fa-solid fa-eye text-xs"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>