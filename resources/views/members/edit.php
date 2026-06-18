<?php
use App\Helpers\Format;
use App\Helpers\Avatar;

$pageTitle   = 'Edit: ' . ($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '');
$activePage  = 'members';
$breadcrumbs = [
    'Members' => APP_URL . '/members',
    ($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '') => APP_URL . '/members/' . ($member['id'] ?? 0),
    'Edit' => null
];
$savings_groups = $savings_groups ?? [];
?>

<style>
.step-indicator { display:flex; align-items:center; gap:0; }
.step { display:flex; align-items:center; gap:8px; }
.step-num { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:700; border:2px solid; flex-shrink:0; transition:all 0.3s; }
.step.active .step-num  { background:var(--green-mid, #136b55); border-color:var(--green-mid, #136b55); color:#fff; }
.step.done .step-num    { background:#10b981; border-color:#10b981; color:#fff; }
.step.pending .step-num { background:#fff; border-color:#d1d5db; color:#9ca3af; }
.step-label { font-size:0.78rem; font-weight:600; color:#94a3b8; }
.step.active .step-label { color:var(--green-mid, #136b55); }
.step.done .step-label  { color:#10b981; }
.step-line { flex:1; height:2px; background:#e8edf2; margin:0 6px; min-width:24px; }
.step-line.done { background:#10b981; }
.form-panel { display:none; }
.form-panel.active { display:block; }
.photo-zone { width:110px; height:110px; border-radius:50%; border:2px dashed #d1d5db; display:flex; flex-direction:column; align-items:center; justify-content:center; cursor:pointer; transition:all 0.2s; overflow:hidden; background:#f9fafb; position:relative; }
.photo-zone:hover { border-color:var(--green-mid, #136b55); background:#f0fdf9; }
.photo-zone img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
.photo-overlay { position:absolute; inset:0; border-radius:50%; background:rgba(0,0,0,0.45); display:flex; align-items:center; justify-content:center; color:#fff; opacity:0; transition:opacity 0.2s; }
.photo-zone:hover .photo-overlay { opacity:1; }
.form-section-title { font-size:0.8rem; font-weight:700; text-transform:uppercase; letter-spacing:0.07em; color:var(--green-mid, #136b55); margin-bottom:14px; padding-bottom:6px; border-bottom:2px solid #ecfdf5; display:flex; align-items:center; gap:8px; }
.grid-2 { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
.grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
@media(max-width:640px) { .grid-2,.grid-3 { grid-template-columns:1fr; } }
@media(min-width:641px) and (max-width:900px) { .grid-3 { grid-template-columns:repeat(2,1fr); } }
.field-hint { font-size:0.72rem; color:#94a3b8; margin-top:3px; }
label.required::after { content:' *'; color:#ef4444; }
.summary-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; }
.summary-card { background:#f8fafc; border-radius:12px; padding:16px; border:1px solid #e8edf2; }
.summary-card h4 { font-size:0.8rem; font-weight:700; color:var(--green-mid, #136b55); margin-bottom:12px; border-bottom:1px solid #e2e8f0; padding-bottom:6px; }
.summary-row { display:flex; justify-content:space-between; padding:6px 0; font-size:0.85rem; border-bottom:1px dashed #e2e8f0; }
.summary-row:last-child { border-bottom:none; }
.summary-label { color:#64748b; font-weight:500; }
.summary-value { color:#1e293b; font-weight:500; text-align:right; }
@media(max-width:640px) { .summary-grid { grid-template-columns:1fr; } }
</style>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Member</h1>
            <p class="text-sm text-slate-400 mt-0.5"><?= htmlspecialchars($member['member_no'] ?? '') ?> · <?= Format::statusPill($member['status'] ?? 'active') ?></p>
        </div>
        <a href="<?= APP_URL ?>/members/<?= $member['id'] ?? 0 ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
    </div>

    <div class="card mb-5 px-6 py-4">
        <div class="step-indicator">
            <div class="step active" id="step-ind-1"><div class="step-num">1</div><div class="step-label hidden sm:block">Personal Info</div></div>
            <div class="step-line" id="line-1"></div>
            <div class="step pending" id="step-ind-2"><div class="step-num">2</div><div class="step-label hidden sm:block">Contact & KYC</div></div>
            <div class="step-line" id="line-2"></div>
            <div class="step pending" id="step-ind-3"><div class="step-num">3</div><div class="step-label hidden sm:block">Next of Kin</div></div>
            <div class="step-line" id="line-3"></div>
            <div class="step pending" id="step-ind-4"><div class="step-num">4</div><div class="step-label hidden sm:block">Membership</div></div>
            <div class="step-line" id="line-4"></div>
            <div class="step pending" id="step-ind-5"><div class="step-num">5</div><div class="step-label hidden sm:block">Review & Submit</div></div>
        </div>
    </div>

    <form id="memberForm" method="POST" action="<?= APP_URL ?>/members/<?= $member['id'] ?? 0 ?>/update" enctype="multipart/form-data" novalidate>
        <?= $csrfField ?>
        <input type="hidden" name="member_id" value="<?= $member['id'] ?? 0 ?>">

        <!-- STEP 1: Personal Info -->
        <div class="form-panel active" id="panel-1">
            <div class="card">
                <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-user" style="color:var(--green-mid, #136b55)"></i> Personal Information</h3></div>
                <div class="card-body space-y-5">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5 pb-4 border-b border-slate-100">
                        <div>
                            <div class="photo-zone" id="photoZone" onclick="document.getElementById('avatarInput').click()">
                                <?php if (!empty($member['avatar'])): ?>
                                    <img id="photoPreview" src="<?= APP_URL ?>/storage/uploads/avatars/<?= htmlspecialchars($member['avatar']) ?>" alt="Preview">
                                    <div id="photoPlaceholder" class="text-center hidden"><i class="fa-solid fa-camera text-2xl text-slate-300 mb-1"></i><span class="text-[10px] text-slate-400 block">Upload Photo</span></div>
                                <?php else: ?>
                                    <img id="photoPreview" src="" class="hidden" alt="Preview">
                                    <div id="photoPlaceholder" class="text-center"><i class="fa-solid fa-camera text-2xl text-slate-300 mb-1"></i><span class="text-[10px] text-slate-400 block">Upload Photo</span></div>
                                <?php endif; ?>
                                <div class="photo-overlay"><i class="fa-solid fa-camera"></i></div>
                            </div>
                            <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden">
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-slate-700 mb-1">Member Photo <span class="text-slate-400 font-normal">(optional)</span></p>
                            <p class="text-xs text-slate-400">Upload a clear passport-size photo. JPG or PNG, max 2MB.</p>
                            <button type="button" onclick="document.getElementById('avatarInput').click()" class="mt-2 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors"><i class="fa-solid fa-upload mr-1"></i> Change Photo</button>
                        </div>
                    </div>
                    <div>
                        <div class="form-section-title"><i class="fa-solid fa-id-card"></i> Full Name</div>
                        <div class="grid-3">
                            <div><label class="form-label required" for="first_name">First Name</label><input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($member['first_name'] ?? '') ?>" class="form-control" placeholder="e.g. Amara" required></div>
                            <div><label class="form-label" for="middle_name">Middle Name</label><input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($member['middle_name'] ?? '') ?>" class="form-control" placeholder="Optional"></div>
                            <div><label class="form-label required" for="last_name">Last Name / Surname</label><input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($member['last_name'] ?? '') ?>" class="form-control" placeholder="e.g. Nakato" required></div>
                        </div>
                    </div>
                    <div>
                        <div class="form-section-title"><i class="fa-solid fa-circle-info"></i> Demographics</div>
                        <div class="grid-3">
                            <div><label class="form-label required" for="gender">Gender</label><select id="gender" name="gender" class="form-control form-select" required><option value="">Select gender</option><option value="male" <?= ($member['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option><option value="female" <?= ($member['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option><option value="other" <?= ($member['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option></select></div>
                            <div><label class="form-label" for="date_of_birth">Date of Birth</label><input type="date" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($member['date_of_birth'] ?? '') ?>" max="<?= date('Y-m-d', strtotime('-16 years')) ?>" class="form-control"><p class="field-hint">Must be at least 16 years old</p></div>
                            <div><label class="form-label" for="occupation">Occupation</label><input type="text" id="occupation" name="occupation" value="<?= htmlspecialchars($member['occupation'] ?? '') ?>" class="form-control" placeholder="e.g. Teacher, Farmer"></div>
                            <div><label class="form-label" for="employer">Employer / Business</label><input type="text" id="employer" name="employer" value="<?= htmlspecialchars($member['employer'] ?? '') ?>" class="form-control" placeholder="Employer or business name"></div>
                            <div><label class="form-label" for="district">District</label><input type="text" id="district" name="district" value="<?= htmlspecialchars($member['district'] ?? '') ?>" class="form-control" placeholder="e.g. Kampala, Wakiso"></div>
                        </div>
                    </div>
                    <div><label class="form-label" for="address">Physical Address</label><textarea id="address" name="address" rows="2" class="form-control" placeholder="Village, Parish, Sub-county, District…"><?= htmlspecialchars($member['address'] ?? '') ?></textarea></div>
                </div>
                <div class="px-6 pb-5 flex justify-end"><button type="button" onclick="goToStep(2)" class="btn btn-primary">Next: Contact & KYC <i class="fa-solid fa-arrow-right"></i></button></div>
            </div>
        </div>

        <!-- STEP 2: Contact & KYC -->
        <div class="form-panel" id="panel-2">
            <div class="card">
                <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-phone" style="color:var(--green-mid, #136b55)"></i> Contact & Identity Verification</h3></div>
                <div class="card-body space-y-5">
                    <div>
                        <div class="form-section-title"><i class="fa-solid fa-address-book"></i> Contact Details</div>
                        <div class="grid-3">
                            <div><label class="form-label required" for="phone">Primary Phone</label><input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($member['phone'] ?? '') ?>" class="form-control" placeholder="+256 700 000000" required><p class="field-hint">Include country code</p></div>
                            <div><label class="form-label" for="phone_alt">Alternative Phone</label><input type="tel" id="phone_alt" name="phone_alt" value="<?= htmlspecialchars($member['phone_alt'] ?? '') ?>" class="form-control" placeholder="+256 700 000000"></div>
                            <div><label class="form-label" for="email">Email Address</label><input type="email" id="email" name="email" value="<?= htmlspecialchars($member['email'] ?? '') ?>" class="form-control" placeholder="email@example.com"><p class="field-hint">For notifications (optional)</p></div>
                        </div>
                    </div>
                    <div>
                        <div class="form-section-title"><i class="fa-solid fa-id-badge"></i> Identity Documents (KYC)</div>
                        <div class="grid-2">
                            <div><label class="form-label required" for="national_id">National ID Number</label><input type="text" id="national_id" name="national_id" value="<?= htmlspecialchars($member['national_id'] ?? '') ?>" class="form-control" placeholder="CM90000000XYZW" maxlength="20" required><p class="field-hint">Ugandan National ID (NIN)</p></div>
                            <div><label class="form-label" for="passport_no">Passport Number</label><input type="text" id="passport_no" name="passport_no" value="<?= htmlspecialchars($member['passport_no'] ?? '') ?>" class="form-control" placeholder="A00000000"><p class="field-hint">If applicable</p></div>
                        </div>
                        <div class="mt-4 grid-2">
                            <!-- ID Front -->
                            <div>
                                <label class="form-label">National ID Front</label>
                                <?php if (!empty($member['id_front'])): ?>
                                    <div class="mb-2 flex items-center gap-2 bg-slate-50 rounded-lg p-2" id="existing_front_block">
                                        <?php $frontExt = strtolower(pathinfo($member['id_front'], PATHINFO_EXTENSION)); ?>
                                        <?php if (in_array($frontExt, ['jpg','jpeg','png','webp'])): ?>
                                            <img src="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_front']) ?>" class="w-12 h-12 object-cover rounded border">
                                        <?php else: ?>
                                            <i class="fa-regular fa-file-pdf text-red-500 text-2xl"></i>
                                        <?php endif; ?>
                                        <span class="text-sm flex-1 truncate"><?= htmlspecialchars(basename($member['id_front'])) ?></span>
                                        <button type="button" class="text-red-500 text-xs" onclick="removeFile('id_front')"><i class="fa-regular fa-trash-can"></i> Remove</button>
                                    </div>
                                    <input type="hidden" name="existing_id_front" value="<?= htmlspecialchars($member['id_front']) ?>">
                                <?php endif; ?>
                                <div class="border-2 border-dashed border-slate-200 rounded-xl p-4 text-center hover:border-green-400 hover:bg-green-50 transition-all cursor-pointer" onclick="document.getElementById('id_front_input').click()">
                                    <i class="fa-regular fa-image text-2xl text-slate-300 mb-1 block"></i>
                                    <p class="text-xs text-slate-400">Click to upload new ID front</p>
                                    <p class="text-[10px] text-slate-300 mt-0.5">JPG, PNG or PDF · Max 2MB</p>
                                    <input type="file" id="id_front_input" name="id_front" class="hidden" accept="image/jpeg,image/png,image/webp,application/pdf">
                                </div>
                                <div id="id_front_preview" class="mt-2 hidden">
                                    <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
                                        <img id="id_front_thumb" src="" class="w-12 h-12 object-cover rounded border hidden">
                                        <i id="id_front_icon" class="fa-regular fa-file-image text-green-600 text-2xl"></i>
                                        <span class="text-sm flex-1" id="id_front_filename"></span>
                                        <button type="button" onclick="clearFile('id_front_input')" class="text-red-500 text-xs"><i class="fa-regular fa-trash-can"></i> Remove</button>
                                    </div>
                                </div>
                            </div>
                            <!-- ID Back -->
                            <div>
                                <label class="form-label">National ID Back</label>
                                <?php if (!empty($member['id_back'])): ?>
                                    <div class="mb-2 flex items-center gap-2 bg-slate-50 rounded-lg p-2" id="existing_back_block">
                                        <?php $backExt = strtolower(pathinfo($member['id_back'], PATHINFO_EXTENSION)); ?>
                                        <?php if (in_array($backExt, ['jpg','jpeg','png','webp'])): ?>
                                            <img src="<?= APP_URL ?>/storage/uploads/kyc/<?= htmlspecialchars($member['id_back']) ?>" class="w-12 h-12 object-cover rounded border">
                                        <?php else: ?>
                                            <i class="fa-regular fa-file-pdf text-red-500 text-2xl"></i>
                                        <?php endif; ?>
                                        <span class="text-sm flex-1 truncate"><?= htmlspecialchars(basename($member['id_back'])) ?></span>
                                        <button type="button" class="text-red-500 text-xs" onclick="removeFile('id_back')"><i class="fa-regular fa-trash-can"></i> Remove</button>
                                    </div>
                                    <input type="hidden" name="existing_id_back" value="<?= htmlspecialchars($member['id_back']) ?>">
                                <?php endif; ?>
                                <div class="border-2 border-dashed border-slate-200 rounded-xl p-4 text-center hover:border-green-400 hover:bg-green-50 transition-all cursor-pointer" onclick="document.getElementById('id_back_input').click()">
                                    <i class="fa-regular fa-image text-2xl text-slate-300 mb-1 block"></i>
                                    <p class="text-xs text-slate-400">Click to upload new ID back</p>
                                    <p class="text-[10px] text-slate-300 mt-0.5">JPG, PNG or PDF · Max 2MB</p>
                                    <input type="file" id="id_back_input" name="id_back" class="hidden" accept="image/jpeg,image/png,image/webp,application/pdf">
                                </div>
                                <div id="id_back_preview" class="mt-2 hidden">
                                    <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg">
                                        <img id="id_back_thumb" src="" class="w-12 h-12 object-cover rounded border hidden">
                                        <i id="id_back_icon" class="fa-regular fa-file-image text-green-600 text-2xl"></i>
                                        <span class="text-sm flex-1" id="id_back_filename"></span>
                                        <button type="button" onclick="clearFile('id_back_input')" class="text-red-500 text-xs"><i class="fa-regular fa-trash-can"></i> Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-6 pb-5 flex justify-between">
                    <button type="button" onclick="goToStep(1)" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Previous</button>
                    <button type="button" onclick="goToStep(3)" class="btn btn-primary">Next: Next of Kin <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>
        </div>

        <!-- STEP 3: Next of Kin -->
        <div class="form-panel" id="panel-3">
            <div class="card">
                <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-people-group" style="color:var(--green-mid, #136b55)"></i> Next of Kin / Emergency Contact</h3></div>
                <div class="card-body">
                    <div class="p-4 rounded-xl mb-5" style="background:#fffbeb;border:1px solid #fde68a;"><p class="text-sm text-amber-700"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Next of kin information is used in emergency situations. Please ensure accuracy.</p></div>
                    <div class="grid-3">
                        <div><label class="form-label required" for="next_of_kin_name">Full Name</label><input type="text" id="next_of_kin_name" name="next_of_kin_name" value="<?= htmlspecialchars($member['next_of_kin_name'] ?? '') ?>" class="form-control" placeholder="Next of kin's full name" required></div>
                        <div><label class="form-label required" for="next_of_kin_phone">Phone Number</label><input type="tel" id="next_of_kin_phone" name="next_of_kin_phone" value="<?= htmlspecialchars($member['next_of_kin_phone'] ?? '') ?>" class="form-control" placeholder="+256 700 000000" required></div>
                        <div><label class="form-label required" for="next_of_kin_relationship">Relationship</label><select id="next_of_kin_relationship" name="next_of_kin_relationship" class="form-control form-select" required><option value="">Select relationship</option><?php foreach(['Spouse','Parent','Child','Sibling','Guardian','Relative','Friend','Colleague','Other'] as $r): ?><option value="<?= $r ?>" <?= ($member['next_of_kin_relationship'] ?? '') === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></div>
                    </div>
                </div>
                <div class="px-6 pb-5 flex justify-between">
                    <button type="button" onclick="goToStep(2)" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Previous</button>
                    <button type="button" onclick="goToStep(4)" class="btn btn-primary">Next: Membership <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>
        </div>

        <!-- STEP 4: Membership Details -->
        <div class="form-panel" id="panel-4">
            <div class="card">
                <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-star" style="color:var(--green-mid, #136b55)"></i> Membership Details</h3></div>
                <div class="card-body space-y-5">
                    <div class="grid-2">
                        <div><label class="form-label required" for="membership_date">Membership Date</label><input type="date" id="membership_date" name="membership_date" value="<?= htmlspecialchars($member['membership_date'] ?? date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" class="form-control" required></div>
                        <div><label class="form-label" for="group_id">Assign to Group <span class="text-slate-400 font-normal">(optional)</span></label><select id="group_id" name="group_id" class="form-control form-select"><option value="">— No group —</option><?php foreach ($savings_groups as $g): ?><option value="<?= $g['id'] ?>" <?= ($member['group_id'] ?? '') == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name'] ?? '') ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="p-5 rounded-xl" style="background:#f0fdf9;border:1px solid #a7f3d0;">
                        <div class="flex items-center justify-between mb-3"><div><p class="font-semibold text-emerald-800 text-sm">Membership Registration Fee</p><p class="text-xs text-emerald-600">Default: <?= Format::currency((float)($settings['membership_fee'] ?? 10000)) ?></p></div><label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="membership_fee_paid" id="feePaid" value="1" <?= !empty($member['membership_fee_paid']) ? 'checked' : '' ?> class="w-5 h-5" style="accent-color:var(--green-mid, #136b55)"><span class="text-sm font-semibold text-emerald-800">Fee Paid</span></label></div>
                        <div id="feeAmountRow" class="<?= empty($member['membership_fee_paid']) ? 'hidden' : '' ?>"><label class="form-label" for="membership_fee_amount">Amount Paid</label><div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-500"><?= htmlspecialchars($settings['currency_symbol'] ?? 'USh') ?></span><input type="number" id="membership_fee_amount" name="membership_fee_amount" value="<?= htmlspecialchars($member['membership_fee_amount'] ?? ($settings['membership_fee'] ?? 10000)) ?>" class="form-control pl-12" min="0" step="500"></div></div>
                    </div>
                </div>
                <div class="px-6 pb-5 flex justify-between">
                    <button type="button" onclick="goToStep(3)" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Previous</button>
                    <button type="button" onclick="goToStep(5)" class="btn btn-primary">Next: Review & Submit <i class="fa-solid fa-arrow-right"></i></button>
                </div>
            </div>
        </div>

        <!-- STEP 5: Review & Submit -->
        <div class="form-panel" id="panel-5">
            <div class="card">
                <div class="card-header"><h3 class="section-title"><i class="fa-solid fa-clipboard-list" style="color:var(--green-mid, #136b55)"></i> Review & Submit</h3></div>
                <div class="card-body">
                    <div class="summary-grid">
                        <div class="summary-card">
                            <h4><i class="fa-solid fa-user mr-1"></i> Personal Information</h4>
                            <div class="summary-row"><span class="summary-label">Full Name:</span><span class="summary-value" id="rev_name">—</span></div>
                            <div class="summary-row"><span class="summary-label">Gender:</span><span class="summary-value" id="rev_gender">—</span></div>
                            <div class="summary-row"><span class="summary-label">Date of Birth:</span><span class="summary-value" id="rev_dob">—</span></div>
                            <div class="summary-row"><span class="summary-label">Occupation:</span><span class="summary-value" id="rev_occupation">—</span></div>
                            <div class="summary-row"><span class="summary-label">Employer:</span><span class="summary-value" id="rev_employer">—</span></div>
                            <div class="summary-row"><span class="summary-label">District:</span><span class="summary-value" id="rev_district">—</span></div>
                            <div class="summary-row"><span class="summary-label">Address:</span><span class="summary-value" id="rev_address">—</span></div>
                        </div>
                        <div class="summary-card">
                            <h4><i class="fa-solid fa-phone mr-1"></i> Contact & KYC</h4>
                            <div class="summary-row"><span class="summary-label">Primary Phone:</span><span class="summary-value" id="rev_phone">—</span></div>
                            <div class="summary-row"><span class="summary-label">Alt Phone:</span><span class="summary-value" id="rev_phone_alt">—</span></div>
                            <div class="summary-row"><span class="summary-label">Email:</span><span class="summary-value" id="rev_email">—</span></div>
                            <div class="summary-row"><span class="summary-label">National ID:</span><span class="summary-value" id="rev_nid">—</span></div>
                            <div class="summary-row"><span class="summary-label">Passport:</span><span class="summary-value" id="rev_passport">—</span></div>
                            <div class="summary-row"><span class="summary-label">ID Front:</span><span class="summary-value" id="rev_id_front">Not uploaded</span></div>
                            <div class="summary-row"><span class="summary-label">ID Back:</span><span class="summary-value" id="rev_id_back">Not uploaded</span></div>
                        </div>
                        <div class="summary-card">
                            <h4><i class="fa-solid fa-people-group mr-1"></i> Next of Kin</h4>
                            <div class="summary-row"><span class="summary-label">Name:</span><span class="summary-value" id="rev_kin_name">—</span></div>
                            <div class="summary-row"><span class="summary-label">Phone:</span><span class="summary-value" id="rev_kin_phone">—</span></div>
                            <div class="summary-row"><span class="summary-label">Relationship:</span><span class="summary-value" id="rev_kin_rel">—</span></div>
                        </div>
                        <div class="summary-card">
                            <h4><i class="fa-solid fa-star mr-1"></i> Membership</h4>
                            <div class="summary-row"><span class="summary-label">Membership Date:</span><span class="summary-value" id="rev_membership_date">—</span></div>
                            <div class="summary-row"><span class="summary-label">Group:</span><span class="summary-value" id="rev_group">—</span></div>
                            <div class="summary-row"><span class="summary-label">Reg Fee Paid:</span><span class="summary-value" id="rev_fee_paid">—</span></div>
                            <div class="summary-row"><span class="summary-label">Fee Amount:</span><span class="summary-value" id="rev_fee_amount">—</span></div>
                            <div class="summary-row"><span class="summary-label">Photo:</span><span class="summary-value" id="rev_avatar">Not uploaded</span></div>
                        </div>
                    </div>
                    <div class="mt-5 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-sm">
                        <i class="fa-solid fa-circle-info mr-1"></i> Please verify all information before submitting. You can go back to any step to make corrections.
                    </div>
                </div>
                <div class="px-6 pb-5 flex justify-between items-center">
                    <button type="button" onclick="goToStep(4)" class="btn btn-secondary"><i class="fa-solid fa-arrow-left"></i> Previous</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn"><i class="fa-solid fa-save"></i> Update Member</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let currentStep = 1;
function goToStep(step) {
    if (step > currentStep && !validateStep(currentStep)) return;
    document.querySelectorAll('.form-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + step).classList.add('active');
    for (let i = 1; i <= 5; i++) {
        const ind = document.getElementById('step-ind-' + i);
        const line = document.getElementById('line-' + i);
        if (ind) {
            ind.className = 'step ' + (i < step ? 'done' : i === step ? 'active' : 'pending');
            if (i < step) ind.querySelector('.step-num').innerHTML = '<i class="fa-solid fa-check text-xs"></i>';
            else ind.querySelector('.step-num').textContent = i;
        }
        if (line) line.className = 'step-line ' + (i < step ? 'done' : '');
    }
    currentStep = step;
    if (step === 5) updateReviewSummary();
    document.getElementById('memberForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function validateStep(step) {
    const required = {
        1: ['first_name','last_name','gender'],
        2: ['phone','national_id'],
        3: ['next_of_kin_name','next_of_kin_phone','next_of_kin_relationship'],
        4: ['membership_date']
    };
    let valid = true;
    (required[step] || []).forEach(id => {
        const el = document.getElementById(id);
        if (!el || !el.value.trim()) { 
            el && el.classList.add('border-red-400'); 
            valid = false; 
            window.showToast('error', 'Please fill in all required fields.'); 
        } else {
            el && el.classList.remove('border-red-400');
        }
    });
    return valid;
}

function updateReviewSummary() {
    document.getElementById('rev_name').textContent = (document.getElementById('first_name').value + ' ' + document.getElementById('last_name').value).trim() || '—';
    document.getElementById('rev_gender').textContent = document.getElementById('gender').value || '—';
    document.getElementById('rev_dob').textContent = document.getElementById('date_of_birth').value || '—';
    document.getElementById('rev_occupation').textContent = document.getElementById('occupation').value || '—';
    document.getElementById('rev_employer').textContent = document.getElementById('employer').value || '—';
    document.getElementById('rev_district').textContent = document.getElementById('district').value || '—';
    document.getElementById('rev_address').textContent = document.getElementById('address').value || '—';
    
    document.getElementById('rev_phone').textContent = document.getElementById('phone').value || '—';
    document.getElementById('rev_phone_alt').textContent = document.getElementById('phone_alt').value || '—';
    document.getElementById('rev_email').textContent = document.getElementById('email').value || '—';
    document.getElementById('rev_nid').textContent = document.getElementById('national_id').value || '—';
    document.getElementById('rev_passport').textContent = document.getElementById('passport_no').value || '—';
    
    const idFrontInput = document.getElementById('id_front_input');
    const idBackInput = document.getElementById('id_back_input');
    const hasExistingFront = document.getElementById('existing_front_block');
    const hasExistingBack = document.getElementById('existing_back_block');
    
    document.getElementById('rev_id_front').textContent = (idFrontInput && idFrontInput.files && idFrontInput.files[0]) ? idFrontInput.files[0].name : (hasExistingFront ? 'Existing file kept' : 'Not uploaded');
    document.getElementById('rev_id_back').textContent = (idBackInput && idBackInput.files && idBackInput.files[0]) ? idBackInput.files[0].name : (hasExistingBack ? 'Existing file kept' : 'Not uploaded');
    
    document.getElementById('rev_kin_name').textContent = document.getElementById('next_of_kin_name').value || '—';
    document.getElementById('rev_kin_phone').textContent = document.getElementById('next_of_kin_phone').value || '—';
    document.getElementById('rev_kin_rel').textContent = document.getElementById('next_of_kin_relationship').value || '—';
    
    document.getElementById('rev_membership_date').textContent = document.getElementById('membership_date').value || '—';
    const groupSelect = document.getElementById('group_id');
    document.getElementById('rev_group').textContent = groupSelect ? groupSelect.options[groupSelect.selectedIndex]?.text || '—' : '—';
    
    const feePaid = document.getElementById('feePaid').checked;
    document.getElementById('rev_fee_paid').textContent = feePaid ? 'Yes' : 'No';
    const feeAmount = document.getElementById('membership_fee_amount').value;
    document.getElementById('rev_fee_amount').textContent = feeAmount ? '<?= $settings['currency_symbol'] ?? 'USh' ?> ' + parseFloat(feeAmount).toLocaleString() : '—';
    
    const avatarInput = document.getElementById('avatarInput');
    const hasExistingAvatar = document.getElementById('photoPreview') && document.getElementById('photoPreview').src && !document.getElementById('photoPreview').classList.contains('hidden');
    document.getElementById('rev_avatar').textContent = (avatarInput && avatarInput.files && avatarInput.files[0]) ? avatarInput.files[0].name : (hasExistingAvatar ? 'Existing photo kept' : 'Not uploaded');
}

document.getElementById('avatarInput').addEventListener('change', function() {
    const file = this.files[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) { window.showToast('error', 'Photo must be under 2MB.'); this.value=''; return; }
    const reader = new FileReader();
    reader.onload = e => { 
        document.getElementById('photoPreview').src = e.target.result; 
        document.getElementById('photoPreview').classList.remove('hidden'); 
        document.getElementById('photoPlaceholder').classList.add('hidden'); 
    };
    reader.readAsDataURL(file);
});

document.getElementById('feePaid').addEventListener('change', function() { 
    document.getElementById('feeAmountRow').classList.toggle('hidden', !this.checked); 
});

function setupDocPreview(inputId, previewId, thumbId, iconId, filenameSpanId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('change', function() {
        const file = this.files[0];
        const previewDiv = document.getElementById(previewId);
        const thumb = document.getElementById(thumbId);
        const icon = document.getElementById(iconId);
        const filenameSpan = document.getElementById(filenameSpanId);
        if (!file) { previewDiv.classList.add('hidden'); return; }
        if (file.size > 2 * 1024 * 1024) { window.showToast('error', 'File must be under 2MB.'); this.value = ''; previewDiv.classList.add('hidden'); return; }
        filenameSpan.textContent = file.name;
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) { thumb.src = e.target.result; thumb.classList.remove('hidden'); icon.classList.add('hidden'); };
            reader.readAsDataURL(file);
        } else if (file.type === 'application/pdf') {
            thumb.classList.add('hidden'); icon.className = 'fa-regular fa-file-pdf text-red-500 text-2xl'; icon.classList.remove('hidden');
        } else {
            thumb.classList.add('hidden'); icon.className = 'fa-regular fa-file text-slate-500 text-2xl'; icon.classList.remove('hidden');
        }
        previewDiv.classList.remove('hidden');
    });
}

function clearFile(inputId) {
    const input = document.getElementById(inputId);
    const previewId = inputId.replace('_input', '') + '_preview';
    const previewDiv = document.getElementById(previewId);
    if (input) { input.value = ''; if (previewDiv) previewDiv.classList.add('hidden'); }
}

function removeFile(type) {
    const container = document.querySelector(`input[name="existing_${type}"]`)?.parentNode;
    if (container) {
        let hidden = container.querySelector('input[name="delete_'+type+'"]');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `delete_${type}`;
            hidden.value = '1';
            container.appendChild(hidden);
        }
        container.style.display = 'none';
    }
}

setupDocPreview('id_front_input', 'id_front_preview', 'id_front_thumb', 'id_front_icon', 'id_front_filename');
setupDocPreview('id_back_input', 'id_back_preview', 'id_back_thumb', 'id_back_icon', 'id_back_filename');

document.getElementById('memberForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    if (!validateStep(4)) { window.showToast('error', 'Please complete all required fields before submitting.'); return; }
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Updating…';
    try {
        const formData = new FormData(this);
        const response = await fetch(this.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });
        const data = await response.json();
        if (data.success) {
            window.showToast('success', data.message);
            const redirectUrl = data.data?.redirect || '<?= APP_URL ?>/members/<?= $member['id'] ?? 0 ?>';
            setTimeout(() => { window.location.href = redirectUrl; }, 800);
        } else {
            window.showToast('error', data.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-save"></i> Update Member';
        }
    } catch (err) {
        window.showToast('error', 'Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-save"></i> Update Member';
    }
});

document.querySelectorAll('.form-control').forEach(el => { el.addEventListener('input', () => el.classList.remove('border-red-400')); });
document.addEventListener('DOMContentLoaded', function() { if (document.getElementById('panel-5').classList.contains('active')) updateReviewSummary(); });
</script>