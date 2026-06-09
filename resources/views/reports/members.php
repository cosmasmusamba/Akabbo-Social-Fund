<?php use App\Helpers\Format; $pageTitle='Members Report'; $activePage='reports'; $breadcrumbs=['Reports'=>APP_URL.'/reports','Members'=>null]; ?>
<div class="flex items-center justify-between mb-5">
  <div><h1 class="text-xl font-bold text-slate-800">Members Report</h1><p class="text-sm text-slate-400">Joined: <?= Format::date($filters['from']) ?> — <?= Format::date($filters['to']) ?></p></div>
  <a href="?<?= http_build_query(array_merge($_GET,['export'=>'1'])) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-file-csv text-green-600"></i> Export</a>
</div>
<div class="card mb-5"><div class="card-body py-3"><form method="GET" class="flex flex-wrap gap-3">
  <input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>" class="form-control py-2 text-sm w-auto">
  <input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>" class="form-control py-2 text-sm w-auto">
  <select name="status" class="form-control form-select py-2 text-sm w-auto" onchange="this.form.submit()">
    <option value="">All</option><?php foreach(['active','inactive','suspended'] as $s): ?><option value="<?= $s ?>" <?= ($status??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Apply</button>
</form></div></div>
<div class="card"><div class="overflow-x-auto"><table class="data-table">
  <thead><tr><th>No</th><th>Name</th><th class="hidden md:table-cell">Gender</th><th class="hidden md:table-cell">Phone</th><th class="hidden lg:table-cell">District</th><th>Savings</th><th class="hidden md:table-cell">Loans</th><th>Status</th><th class="hidden lg:table-cell">KYC</th><th class="hidden md:table-cell">Joined</th></tr></thead>
  <tbody>
    <?php if(empty($data)): ?><tr><td colspan="10" class="text-center py-10 text-slate-400">No members for this period</td></tr>
    <?php else: foreach($data as $row): ?>
    <tr>
      <td><span class="font-mono text-xs"><?= htmlspecialchars($row['member_no']) ?></span></td>
      <td><div class="font-semibold text-sm"><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></div></td>
      <td class="hidden md:table-cell text-xs capitalize"><?= $row['gender']??'—' ?></td>
      <td class="hidden md:table-cell text-sm"><?= htmlspecialchars($row['phone']) ?></td>
      <td class="hidden lg:table-cell text-sm"><?= htmlspecialchars($row['district']??'—') ?></td>
      <td class="font-bold text-emerald-700"><?= Format::currency((float)$row['total_savings']) ?></td>
      <td class="hidden md:table-cell text-center"><?= $row['total_loans'] ?></td>
      <td><?= Format::statusPill($row['status']) ?></td>
      <td class="hidden lg:table-cell"><?= $row['kyc_verified']?'<span class="text-emerald-500"><i class="fa-solid fa-check"></i></span>':'<span class="text-amber-400"><i class="fa-solid fa-clock"></i></span>' ?></td>
      <td class="hidden md:table-cell text-xs text-slate-400"><?= Format::date($row['membership_date']) ?></td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table></div></div>
