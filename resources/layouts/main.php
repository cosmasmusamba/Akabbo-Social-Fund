<?php
/**
 * Akabbo Social Fund - Main Layout Template (Permission-Guarded)
 *
 * Expected variables from controller:
 * - $pageTitle (string, default 'Dashboard')
 * - $activePage (string, current route name)
 * - $content (string, HTML content for main area)
 * - $stats (array, optional: kyc_pending, pending_loans, pending_approvals)
 * - $unreadNotifications (int, optional)
 * - $breadcrumbs (array, optional, label => URL)
 * - $settings (array, optional, for favicon)
 * - $auth (object, with user() and can() methods)
 * - APP_URL, APP_VERSION (constants)
 */
use App\Helpers\Avatar;
use App\Helpers\Format;

// Safely extract user
$user = $auth->user() ?? [];
$userName = $user['name'] ?? 'User';
$userFirstName = $user['first_name'] ?? '';
$userEmail = $user['email'] ?? '';
$userRole = $user['role_name'] ?? '';
$userMemberId = $user['member_id'] ?? null;  // for member-specific links

// Safe fallbacks for optional data
$stats = $stats ?? [];
$kycPending = $stats['kyc_pending'] ?? 0;
$pendingLoans = $stats['pending_loans'] ?? 0;
$pendingApprovals = $stats['pending_approvals'] ?? 0;
$unreadNotifications = $unreadNotifications ?? 0;

// Active page detection helpers
$currentPage = $activePage ?? '';
$isReportsPage = in_array($currentPage, [
    'reports', 'reports-savings', 'reports-loans',
    'reports-transactions', 'reports-members', 'reports-cash-flow'
]);
$isUsersPage = in_array($currentPage, [
    'users', 'users-create', 'users-show', 'profile', 'profile-change-password'
]);
$isMembersPage = in_array($currentPage, ['members', 'member-create', 'members-edit', 'members-show']);
$isSavingsPage = in_array($currentPage, ['savings', 'savings-deposit', 'savings-withdraw', 'savings-transfer']);
$isLoansPage = in_array($currentPage, ['loans', 'loans-create', 'loans-show', 'loans-edit']);

// ── Pre-calculate permissions for efficient sidebar rendering ──────────────
// Evaluating permissions ONCE here prevents multiple DB/cache hits in the HTML.
// It also fixes a bug where 'import_export.view' was checked instead of 'import.upload'.
$can = [
    'members.view'       => $auth->can('members.view'),
    'members.view_own'   => $auth->can('members.view_own'),
    'groups.view'        => $auth->can('groups.view'),
    'savings.view'       => $auth->can('savings.view'),
    'shares.view'        => $auth->can('shares.view'),
    'social_fund.view'   => $auth->can('social_fund.view'),
    'loans.view'         => $auth->can('loans.view'),
    'loan_products.view' => $auth->can('loan_products.view'),
    'transfers.view'     => $auth->can('transfers.view'),
    'transactions.view'  => $auth->can('transactions.view'),
    'approvals.view'     => $auth->can('approvals.view'),
    'expenses.view'      => $auth->can('expenses.view'),
    'reports.view'       => $auth->can('reports.view'),
    'import.upload'      => $auth->can('import.upload'), // FIXED: was import_export.view
    'audit.view'         => $auth->can('audit.view'),
    'users.view'         => $auth->can('users.view'),
    'permissions.manage' => $auth->can('permissions.manage'),
    'settings.view'      => $auth->can('settings.view'),
    'trash.view'         => $auth->can('trash.view'),
];

// Section visibility flags (Pre-calculated to avoid complex OR conditions in HTML)
$showMembers    = $can['members.view'] || $can['members.view_own'] || $can['groups.view'];
$showFinance    = $can['savings.view'] || $can['shares.view'] || $can['social_fund.view'] || 
                  $can['loans.view'] || $can['loan_products.view'] || $can['transfers.view'] || 
                  $can['transactions.view'];
$showApprovals  = $can['approvals.view'];
$showOperations = $can['expenses.view'] || $can['reports.view'] || $can['import.upload'] || $can['audit.view'];
$showAdmin      = $can['users.view'] || $can['permissions.manage'] || $can['settings.view'] || $can['trash.view'];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= \App\Helpers\Security::generateCsrfToken() ?>">
<title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — Akabbo Social Fund</title>
<!-- Favicon with fallback -->
<link rel="icon" href="<?= Avatar::favicon($settings ?? []) ?>" type="image/x-icon">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,700;1,9..144,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/public/assets/css/app.css">
<style>
/* ===== ALL STYLES REMAIN EXACTLY AS IN THE ORIGINAL ===== */
:root {
--green-deep:    #0a4033;
--green-mid:     #136b55;
--green-bright:  #1a8f6f;
--gold:          #c9963a;
--gold-light:    #e8b85a;
--sidebar-bg:    #071e18;
--sidebar-hover: rgba(26,143,111,0.18);
--sidebar-active:rgba(26,143,111,0.28);
--surface:       #f4f6f9;
--card:          #ffffff;
}
* { box-sizing: border-box; }
html, body { height: 100%; margin: 0; }
body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--surface); color: #1e293b; }
.brand-font { font-family: 'Fraunces', serif; }
/* ── Sidebar ─────────────────────────────────────────────────────── */
#sidebar {
width: 260px; min-height: 100vh;
background: var(--sidebar-bg);
display: flex; flex-direction: column;
position: fixed; top: 0; left: 0; z-index: 40;
transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
border-right: 1px solid rgba(255,255,255,0.05);
}
#sidebar.collapsed { transform: translateX(-260px); }
@media (max-width: 1023px) {
#sidebar { transform: translateX(-260px); }
#sidebar.open { transform: translateX(0); }
}
.sidebar-logo {
padding: 20px 20px 16px;
border-bottom: 1px solid rgba(255,255,255,0.07);
}
.logo-mark {
background: linear-gradient(135deg, #136b55, #1a8f6f);
box-shadow: 0 4px 14px rgba(26, 143, 111, 0.4);
}
.text-sidebar-muted {
color: rgba(255, 255, 255, 0.4);
}
.text-sidebar-muted-light {
color: rgba(255, 255, 255, 0.45);
}
/* ── Collapsible Nav Sections ────────────────────────────────────── */
.nav-section-label {
font-size: 0.65rem; font-weight: 700; letter-spacing: 0.1em;
text-transform: uppercase; color: rgba(255,255,255,0.4);
padding: 14px 20px 6px; margin-top: 4px;
display: flex; justify-content: space-between; align-items: center;
cursor: pointer; user-select: none;
transition: color 0.2s;
}
.nav-section-label:hover {
color: rgba(255,255,255,0.7);
}
.nav-section-chevron {
font-size: 0.55rem;
transition: transform 0.3s ease;
}
.nav-section.collapsed .nav-section-chevron {
transform: rotate(-90deg);
}
.nav-section-items {
overflow: hidden;
transition: max-height 0.35s ease, opacity 0.35s ease;
max-height: 500px;
opacity: 1;
}
.nav-section.collapsed .nav-section-items {
max-height: 0;
opacity: 0;
}
/* Nav items */
.nav-item {
display: flex; align-items: center; gap: 11px;
padding: 10px 20px; margin: 1px 10px; border-radius: 9px;
color: rgba(255,255,255,0.65); font-size: 0.88rem; font-weight: 500;
cursor: pointer; text-decoration: none; transition: all 0.18s;
position: relative;
}
.nav-item:hover { background: var(--sidebar-hover); color: #fff; }
.nav-item.active {
background: var(--sidebar-active);
color: #fff;
font-weight: 600;
}
.nav-item.active::before {
content: ''; position: absolute;
left: -10px; top: 50%; transform: translateY(-50%);
width: 3px; height: 22px; border-radius: 0 2px 2px 0;
background: var(--gold);
}
.nav-icon { width: 18px; text-align: center; font-size: 0.9rem; opacity: 0.85; }
.nav-badge {
margin-left: auto; background: #ef4444; color: #fff;
font-size: 0.65rem; font-weight: 700; border-radius: 10px;
padding: 1px 6px; min-width: 18px; text-align: center;
}
.nav-badge.gold { background: var(--gold); }
/* Sidebar user footer */
.sidebar-user {
padding: 14px 20px;
border-top: 1px solid rgba(255,255,255,0.07);
display: flex; align-items: center; gap: 10px;
}
.user-avatar {
width: 36px; height: 36px; border-radius: 50%;
background: linear-gradient(135deg, var(--green-mid), var(--green-bright));
display: flex; align-items: center; justify-content: center;
font-size: 0.8rem; font-weight: 700; color: #fff;
flex-shrink: 0;
}
/* ── Top navbar ──────────────────────────────────────────────────── */
#topbar {
height: 64px; background: #fff; border-bottom: 1px solid #e8edf2;
display: flex; align-items: center; justify-content: space-between;
padding: 0 24px;
position: sticky; top: 0; z-index: 30;
box-shadow: 0 1px 8px rgba(0,0,0,0.06);
}
/* ── Main content ─────────────────────────────────────────────────── */
#mainContent {
margin-left: 260px;
min-height: 100vh;
display: flex; flex-direction: column;
transition: margin-left 0.3s cubic-bezier(0.4,0,0.2,1);
}
#mainContent.full { margin-left: 0; }
@media (max-width: 1023px) {
#mainContent { margin-left: 0; }
}
.page-inner { padding: 24px; flex: 1; }
/* ── Cards ───────────────────────────────────────────────────────── */
.card {
background: var(--card); border-radius: 14px;
border: 1px solid #e8edf2;
box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.card-header {
padding: 18px 22px 14px;
border-bottom: 1px solid #f1f5f9;
display: flex; align-items: center; justify-content: space-between;
}
.card-body { padding: 20px 22px; }
/* ── Stat cards ──────────────────────────────────────────────────── */
.stat-card {
background: var(--card); border-radius: 14px;
border: 1px solid #e8edf2; padding: 20px 22px;
box-shadow: 0 1px 4px rgba(0,0,0,0.04);
transition: box-shadow 0.2s, transform 0.2s;
}
.stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); transform: translateY(-1px); }
/* ── Tables ──────────────────────────────────────────────────────── */
.data-table { width: 100%; border-collapse: collapse; }
.data-table th {
padding: 10px 14px; background: #f8fafc;
font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
letter-spacing: 0.05em; color: #64748b; text-align: left;
border-bottom: 1px solid #e8edf2;
}
.data-table td {
padding: 13px 14px; font-size: 0.875rem; color: #374151;
border-bottom: 1px solid #f1f5f9; vertical-align: middle;
}
.data-table tbody tr:hover { background: #f8fafc; }
.data-table tbody tr:last-child td { border-bottom: none; }
/* ── Buttons ─────────────────────────────────────────────────────── */
.btn { display: inline-flex; align-items: center; gap: 7px;
padding: 9px 18px; border-radius: 9px; font-weight: 600;
font-size: 0.875rem; cursor: pointer; transition: all 0.18s;
border: 1.5px solid transparent; text-decoration: none;
font-family: 'Plus Jakarta Sans', sans-serif;
}
.btn-primary {
background: linear-gradient(135deg, var(--green-mid), var(--green-deep));
color: #fff; border-color: transparent;
}
.btn-primary:hover { box-shadow: 0 4px 14px rgba(10,64,51,0.35); transform: translateY(-1px); }
.btn-secondary { background: #fff; color: #374151; border-color: #d1d5db; }
.btn-secondary:hover { background: #f8fafc; border-color: #9ca3af; }
.btn-danger { background: #fff; color: #dc2626; border-color: #fca5a5; }
.btn-danger:hover { background: #fef2f2; }
.btn-gold { background: linear-gradient(135deg, var(--gold), #a87524); color: #fff; border-color: transparent; }
.btn-sm { padding: 6px 13px; font-size: 0.8rem; border-radius: 7px; }
/* ── Status badges ───────────────────────────────────────────────── */
.badge {
display: inline-flex; align-items: center;
padding: 3px 10px; border-radius: 100px;
font-size: 0.72rem; font-weight: 600; border: 1px solid;
}
/* ── Forms ───────────────────────────────────────────────────────── */
.form-label { display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 5px; }
.form-control {
width: 100%; padding: 9px 13px; border: 1.5px solid #d1d5db;
border-radius: 8px; font-size: 0.875rem; color: #1e293b;
background: #f9fafb; font-family: 'Plus Jakarta Sans', sans-serif;
transition: all 0.18s;
}
.form-control:focus { outline: none; border-color: var(--green-bright); background: #fff; box-shadow: 0 0 0 3px rgba(26,143,111,0.12); }
.form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3E%3Cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3E%3C/svg%3E"); background-position: right 0.5rem center; background-repeat: no-repeat; background-size: 1.5em 1.5em; padding-right: 2.5rem; }
/* ── Modals ──────────────────────────────────────────────────────── */
.modal-overlay {
position: fixed; inset: 0; background: rgba(0,0,0,0.45);
backdrop-filter: blur(3px); z-index: 50;
display: flex; align-items: center; justify-content: center;
padding: 16px;
}
.modal { background: #fff; border-radius: 16px; width: 100%; max-width: 560px;
box-shadow: 0 24px 64px rgba(0,0,0,0.2); animation: modalIn 0.25s ease;
}
.modal-lg { max-width: 780px; }
.modal-xl { max-width: 1000px; }
@keyframes modalIn {
from { opacity: 0; transform: scale(0.95) translateY(10px); }
to   { opacity: 1; transform: scale(1) translateY(0); }
}
.modal-header { padding: 20px 24px; border-bottom: 1px solid #e8edf2; display: flex; align-items: center; justify-content: space-between; }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid #e8edf2; display: flex; justify-content: flex-end; gap: 10px; }
/* ── Toast notifications ─────────────────────────────────────────── */
#toastContainer {
position: fixed; top: 20px; right: 20px; z-index: 9999;
display: flex; flex-direction: column; gap: 10px; max-width: 360px;
}
.toast {
background: #fff; border-radius: 12px; padding: 14px 16px;
box-shadow: 0 8px 30px rgba(0,0,0,0.14); border-left: 4px solid;
display: flex; align-items: flex-start; gap: 12px;
animation: toastIn 0.3s ease;
}
.toast-success { border-color: #10b981; }
.toast-error   { border-color: #ef4444; }
.toast-warning { border-color: #f59e0b; }
.toast-info    { border-color: #3b82f6; }
@keyframes toastIn {
from { opacity: 0; transform: translateX(20px); }
to   { opacity: 1; transform: translateX(0); }
}
/* ── Loading overlay ─────────────────────────────────────────────── */
#pageLoader {
position: fixed; inset: 0; background: rgba(255,255,255,0.85);
z-index: 9990; display: none; align-items: center; justify-content: center;
}
.loader-ring {
width: 44px; height: 44px; border-radius: 50%;
border: 4px solid #e8edf2;
border-top-color: var(--green-mid);
animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
/* ── Overlay for mobile sidebar ──────────────────────────────────── */
#sidebarOverlay {
display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
z-index: 39; backdrop-filter: blur(2px);
}
/* ── Breadcrumb ──────────────────────────────────────────────────── */
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 0.82rem; color: #94a3b8; }
.breadcrumb a { color: #64748b; text-decoration: none; }
.breadcrumb a:hover { color: var(--green-mid); text-decoration: underline; }
.breadcrumb .sep { color: #cbd5e1; }
.breadcrumb .current { color: #1e293b; font-weight: 600; }
/* ── Pagination ──────────────────────────────────────────────────── */
.pager-btn {
padding: 7px 13px; border-radius: 8px; border: 1.5px solid #e2e8f0;
background: #fff; font-size: 0.82rem; font-weight: 600; color: #374151;
cursor: pointer; transition: all 0.15s;
}
.pager-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
.pager-btn.active { background: var(--green-mid); color: #fff; border-color: var(--green-mid); }
.pager-btn:disabled { opacity: 0.4; cursor: not-allowed; }
/* ── Misc helpers ────────────────────────────────────────────────── */
.avatar-circle {
width: 38px; height: 38px; border-radius: 50%;
display: inline-flex; align-items: center; justify-content: center;
font-size: 0.82rem; font-weight: 700; color: #fff; flex-shrink: 0;
background: linear-gradient(135deg, var(--green-mid), var(--green-bright));
}
.section-title { font-size: 1.05rem; font-weight: 700; color: #1e293b; }
.text-muted { color: #94a3b8; }
.divider { height: 1px; background: #f1f5f9; margin: 16px 0; }
/* Scrollbar */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
</head>
<body class="flex h-full">
<!-- ════════════════════════════════════════════════
SIDEBAR (PERMISSION-GUARDED)
════════════════════════════════════════════════════ -->
<aside id="sidebar">
<!-- Logo -->
<div class="sidebar-logo">
<a href="<?= APP_URL ?>/dashboard" class="flex items-center gap-3">
<div class="logo-mark w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0">
<i class="fa-solid fa-coins text-white text-sm"></i>
</div>
<div>
<div class="brand-font text-white text-base font-bold leading-tight">Akabbo</div>
<div class="text-xs font-medium tracking-wider text-sidebar-muted-light">
SOCIAL FUND
</div>
</div>
</a>
</div>
<!-- Navigation -->
<nav class="flex-1 overflow-y-auto py-3" id="sidebarNav">
    <!-- ======================== MAIN SECTION ======================== -->
    <div class="nav-section" data-section="main">
        <div class="nav-section-label" onclick="toggleSection('main')">
            <span>Main</span>
            <i class="fa-solid fa-chevron-down nav-section-chevron"></i>
        </div>
        <div class="nav-section-items">
            <a href="<?= APP_URL ?>/dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high nav-icon"></i>
                <span>Dashboard</span>
            </a>
        </div>
    </div>

    <!-- ======================== MEMBERS SECTION ======================== -->
    <?php if ($showMembers): ?>
    <div class="nav-section" data-section="members">
        <div class="nav-section-label" onclick="toggleSection('members')">
            <span>Members</span>
            <i class="fa-solid fa-chevron-down nav-section-chevron"></i>
        </div>
        <div class="nav-section-items">
            <?php if ($can['members.view']): ?>
            <a href="<?= APP_URL ?>/members" class="nav-item <?= $isMembersPage ? 'active' : '' ?>">
                <i class="fa-solid fa-users nav-icon"></i>
                <span>All Members</span>
                <?php if ($kycPending): ?><span class="nav-badge gold"><?= $kycPending ?></span><?php endif; ?>
            </a>
            <?php endif; ?>
            
            <?php if ($can['groups.view']): ?>
            <a href="<?= APP_URL ?>/groups" class="nav-item <?= $currentPage === 'groups' ? 'active' : '' ?>">
                <i class="fa-solid fa-people-group nav-icon"></i>
                <span>Groups</span>
            </a>
            <?php endif; ?>

            <?php if ($can['members.view_own'] && $userMemberId): ?>
            <a href="<?= APP_URL ?>/members/<?= $userMemberId ?>" class="nav-item <?= $currentPage === 'my-profile' ? 'active' : '' ?>">
                <i class="fa-solid fa-id-card nav-icon"></i>
                <span>My Profile</span>
            </a>
            <?php endif; ?>
        </div>
        </div>
    <?php endif; ?>

    <!-- ======================== FINANCE SECTION ======================== -->
    <?php if ($showFinance): ?>
    <div class="nav-section" data-section="finance">
        <div class="nav-section-label" onclick="toggleSection('finance')">
            <span>Finance</span>
            <i class="fa-solid fa-chevron-down nav-section-chevron"></i>
        </div>
        <div class="nav-section-items">
            <?php if ($can['savings.view']): ?>
            <a href="<?= APP_URL ?>/savings" class="nav-item <?= $isSavingsPage ? 'active' : '' ?>">
                <i class="fa-solid fa-piggy-bank nav-icon"></i><span>Savings</span>
            </a>
            <?php endif; ?>

            <?php if ($can['loans.view']): ?>
            <a href="<?= APP_URL ?>/loans" class="nav-item <?= $isLoansPage ? 'active' : '' ?>">
                <i class="fa-solid fa-hand-holding-dollar nav-icon"></i>
                <span>Loans</span>
                <?php if ($pendingLoans): ?><span class="nav-badge"><?= $pendingLoans ?></span><?php endif; ?>
            </a>
            <?php endif; ?>

            <?php if ($can['shares.view']): ?>
            <a href="<?= APP_URL ?>/shares" class="nav-item <?= $currentPage === 'shares' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie nav-icon"></i><span>Shares</span>
            </a>
            <?php endif; ?>

            <?php if ($can['transfers.view']): ?>
            <a href="<?= APP_URL ?>/transfers" class="nav-item <?= $currentPage === 'transfers' ? 'active' : '' ?>">
                <i class="fa-solid fa-right-left nav-icon"></i><span>Transfers</span>
            </a>
            <?php endif; ?>

            <?php if ($can['transactions.view']): ?>
            <a href="<?= APP_URL ?>/transactions" class="nav-item <?= $currentPage === 'transactions' ? 'active' : '' ?>">
                <i class="fa-solid fa-arrow-right-arrow-left nav-icon"></i><span>Transactions</span>
            </a>
            <?php endif; ?>

            <?php if ($can['social_fund.view']): ?>
            <a href="<?= APP_URL ?>/social-fund" class="nav-item <?= $currentPage === 'social-fund' ? 'active' : '' ?>">
                <i class="fa-solid fa-heart-circle-plus nav-icon"></i><span>Social Fund</span>
            </a>
            <?php endif; ?>

            <?php if ($can['loan_products.view']): ?>
            <a href="<?= APP_URL ?>/loan-products" class="nav-item <?= $currentPage === 'loan-products' ? 'active' : '' ?>">
                <i class="fa-solid fa-tags nav-icon"></i><span>Loan Products</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================== APPROVALS SECTION ======================== -->
    <?php if ($showApprovals): ?>
    <div class="nav-section" data-section="approvals">
        <div class="nav-section-label" onclick="toggleSection('approvals')">
            <span>Approvals</span>
            <i class="fa-solid fa-chevron-down nav-section-chevron"></i>
        </div>
        <div class="nav-section-items">
            <a href="<?= APP_URL ?>/approvals" class="nav-item <?= $currentPage === 'approvals' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-check nav-icon"></i>
                <span>Approvals</span>
                <?php if ($pendingApprovals): ?><span class="nav-badge"><?= $pendingApprovals ?></span><?php endif; ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================== OPERATIONS SECTION ======================== -->
    <?php if ($showOperations): ?>
    <div class="nav-section" data-section="operations">
        <div class="nav-section-label" onclick="toggleSection('operations')">
            <span>Operations</span>
            <i class="fa-solid fa-chevron-down nav-section-chevron"></i>
        </div>
        <div class="nav-section-items">
            <?php if ($can['expenses.view']): ?>
            <a href="<?= APP_URL ?>/expenses" class="nav-item <?= $currentPage === 'expenses' ? 'active' : '' ?>">
                <i class="fa-solid fa-receipt nav-icon"></i><span>Expenses</span>
            </a>
            <?php endif; ?>

            <?php if ($can['reports.view']): ?>
            <a href="<?= APP_URL ?>/reports" class="nav-item <?= $isReportsPage ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-bar nav-icon"></i><span>Reports</span>
            </a>
            <?php endif; ?>

            <?php if ($can['import.upload']): ?>
            <a href="<?= APP_URL ?>/import" class="nav-item <?= $currentPage === 'import' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-import nav-icon"></i><span>Import / Export</span>
            </a>
            <?php endif; ?>

            <?php if ($can['audit.view']): ?>
            <a href="<?= APP_URL ?>/audit" class="nav-item <?= $currentPage === 'audit' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved nav-icon"></i><span>Audit Log</span>
            </a>
            <?php endif; ?>

            <!-- Notifications (Visible to all authenticated users) -->
            <a href="<?= APP_URL ?>/notifications" class="nav-item <?= $currentPage === 'notifications' ? 'active' : '' ?>">
                <i class="fa-regular fa-bell nav-icon"></i>
                <span>Notifications</span>
                <?php if ($unreadNotifications > 0): ?><span class="nav-badge"><?= $unreadNotifications ?></span><?php endif; ?>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================== ADMINISTRATION SECTION ======================== -->
    <?php if ($showAdmin): ?>
    <div class="nav-section" data-section="administration">
        <div class="nav-section-label" onclick="toggleSection('administration')">
            <span>Administration</span>
            <i class="fa-solid fa-chevron-down nav-section-chevron"></i>
        </div>
        <div class="nav-section-items">
            <?php if ($can['users.view']): ?>
            <a href="<?= APP_URL ?>/users" class="nav-item <?= $isUsersPage ? 'active' : '' ?>">
                <i class="fa-solid fa-user-gear nav-icon"></i><span>Users</span>
            </a>
            <?php endif; ?>

            <?php if ($can['permissions.manage']): ?>
            <a href="<?= APP_URL ?>/access-control" class="nav-item <?= $currentPage === 'access-control' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved nav-icon"></i><span>Access Control</span>
            </a>
            <?php endif; ?>

            <?php if ($can['settings.view']): ?>
            <a href="<?= APP_URL ?>/settings" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <i class="fa-solid fa-sliders nav-icon"></i><span>Settings</span>
            </a>
            <?php endif; ?>

            <?php if ($can['trash.view']): ?>
            <a href="<?= APP_URL ?>/trash" class="nav-item <?= $currentPage === 'trash' ? 'active' : '' ?>">
                <i class="fa-solid fa-trash-can nav-icon"></i><span>Trash</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</nav>

<!-- Sidebar User Footer (visible to all) -->
<div class="sidebar-user">
<div class="user-avatar flex items-center justify-center">
<?= Avatar::small($user) ?>
</div>
<div class="flex-1 min-w-0">
<div class="text-white text-sm font-semibold truncate">
<?= htmlspecialchars($userName) ?>
</div>
<div class="text-xs truncate text-sidebar-muted">
<?= htmlspecialchars($userRole) ?>
</div>
</div>
<a href="<?= APP_URL ?>/auth/logout"
       title="Sign out"
       class="text-red-400 hover:text-red-300 transition-colors p-1"
       onclick="event.preventDefault(); confirmLogout();">
        <i class="fa-solid fa-right-from-bracket text-sm"></i>
    </a>
</div>
</aside>

<!-- Mobile overlay -->
<div id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ════════════════════════════════════════════════
MAIN CONTENT (unchanged)
════════════════════════════════════════════════════ -->
<div id="mainContent" class="flex-1 flex flex-col min-h-screen">
<!-- Top bar -->
<header id="topbar">
<div class="flex items-center gap-3">
<button onclick="toggleSidebar()" class="text-slate-500 hover:text-slate-800 transition-colors p-1.5 rounded-lg hover:bg-slate-100 lg:hidden">
<i class="fa-solid fa-bars text-lg"></i>
</button>
<button onclick="toggleDesktopSidebar()" class="hidden lg:flex text-slate-400 hover:text-slate-700 transition-colors p-1.5 rounded-lg hover:bg-slate-100">
<i class="fa-solid fa-bars text-base"></i>
</button>
<div class="hidden sm:block">
<?php if (!empty($breadcrumbs)): ?>
<nav class="breadcrumb">
<a href="<?= APP_URL ?>/dashboard"><i class="fa-solid fa-house-chimney text-xs"></i></a>
<?php foreach ($breadcrumbs as $label => $url): ?>
<span class="sep">/</span>
<?php if ($url): ?>
<a href="<?= $url ?>"><?= htmlspecialchars($label) ?></a>
<?php else: ?>
<span class="current"><?= htmlspecialchars($label) ?></span>
<?php endif; ?>
<?php endforeach; ?>
</nav>
<?php else: ?>
<h1 class="text-base font-bold text-slate-800"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
<?php endif; ?>
</div>
</div>
<div class="flex items-center gap-2 sm:gap-3">
<button onclick="openGlobalSearch()"
class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-slate-400 hover:bg-white hover:text-slate-600 transition-all text-sm">
<i class="fa-solid fa-magnifying-glass"></i>
<span class="hidden sm:inline text-xs">Search…</span>
<kbd class="hidden sm:inline px-1.5 py-0.5 text-xs bg-slate-100 border border-slate-200 rounded font-mono">⌘K</kbd>
</button>
<a href="<?= APP_URL ?>/notifications" class="relative p-2 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-all">
<i class="fa-regular fa-bell text-lg"></i>
<?php if ($unreadNotifications > 0): ?>
<span class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center">
<?= $unreadNotifications > 9 ? '9+' : $unreadNotifications ?>
</span>
<?php endif; ?>
</a>
<div class="relative" x-data="{ open: false }">
<button onclick="toggleUserMenu(this)"
class="flex items-center gap-2.5 pl-2 pr-3 py-1.5 rounded-xl border border-slate-200 hover:bg-slate-50 transition-all">
<div class="avatar-circle w-8 h-8 text-xs">
<?= Avatar::small($user) ?>
</div>
<div class="hidden sm:block text-left">
<div class="text-xs font-bold text-slate-800 leading-tight"><?= htmlspecialchars($userFirstName) ?></div>
<div class="text-[10px] text-slate-400"><?= htmlspecialchars($userRole) ?></div>
</div>
<i class="fa-solid fa-chevron-down text-xs text-slate-400 hidden sm:block"></i>
</button>
<div id="userDropdown" class="hidden absolute right-0 top-full mt-2 w-52 bg-white rounded-xl shadow-lg border border-slate-200 py-2 z-50">
<div class="px-4 py-2 border-b border-slate-100 mb-1">
<div class="font-semibold text-sm text-slate-800"><?= htmlspecialchars($userName) ?></div>
<div class="text-xs text-slate-400 truncate"><?= htmlspecialchars($userEmail) ?></div>
</div>
<a href="<?= APP_URL ?>/profile" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
<i class="fa-regular fa-user w-4 text-slate-400"></i> My Profile
</a>
<a href="<?= APP_URL ?>/profile/change-password" class="flex items-center gap-3 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 transition-colors">
<i class="fa-solid fa-key w-4 text-slate-400"></i> Change Password
</a>
<div class="border-t border-slate-100 mt-1 pt-1">
<a href="<?= APP_URL ?>/auth/logout"
   onclick="event.preventDefault(); confirmLogout();"
   class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
    <i class="fa-solid fa-right-from-bracket w-4"></i> Sign Out
</a>
</div>
</div>
</div>
</div>
</header>

<!-- Flash message (unchanged) -->
<?php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if ($flash):
$fc = [
'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
'error'   => 'bg-red-50 border-red-200 text-red-800',
'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
'info'    => 'bg-blue-50 border-blue-200 text-blue-800',
];
$fi = [
'success' => 'fa-circle-check text-emerald-500',
'error'   => 'fa-circle-xmark text-red-500',
'warning' => 'fa-triangle-exclamation text-amber-500',
'info'    => 'fa-circle-info text-blue-500'
];
$t  = $flash['type'] ?? 'info';
?>
<div class="mx-6 mt-4 px-4 py-3 rounded-xl border flex items-center gap-3 <?= $fc[$t] ?? $fc['info'] ?>" id="flashBanner">
<i class="fa-solid <?= $fi[$t] ?? $fi['info'] ?> text-base flex-shrink-0"></i>
<span class="text-sm font-medium"><?= htmlspecialchars($flash['message']) ?></span>
<button onclick="this.closest('#flashBanner').remove()" class="ml-auto text-current opacity-60 hover:opacity-100">
<i class="fa-solid fa-xmark"></i>
</button>
</div>
<?php endif; ?>

<!-- Page body -->
<main class="page-inner">
<?= $content ?? '' ?>
</main>

<!-- Footer -->
<footer class="px-6 py-4 mt-auto border-t border-slate-100 flex items-center justify-between">
<span class="text-xs text-slate-400">© <?= date('Y') ?> Akabbo Social Fund &nbsp;·&nbsp; v<?= APP_VERSION ?></span>
<span class="text-xs text-slate-400 hidden sm:inline">Enterprise Savings & Loan Management</span>
</footer>
</div>

<!-- ════ Loader, Toasts, Search Modal (unchanged) ════ -->
<div id="pageLoader"><div class="loader-ring"></div></div>
<div id="toastContainer"></div>
<div id="globalSearchModal" class="hidden modal-overlay" onclick="if(event.target===this)closeGlobalSearch()">
<div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden" style="animation:modalIn 0.2s ease">
<div class="relative">
<i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
<input id="globalSearchInput" type="text"
placeholder="Search members, loans, transactions…"
class="w-full pl-11 pr-4 py-4 text-base text-slate-800 border-0 focus:outline-none focus:ring-0"
oninput="runGlobalSearch(this.value)"
>
</div>
<div id="globalSearchResults" class="max-h-96 overflow-y-auto border-t border-slate-100">
<div class="px-4 py-8 text-center text-slate-400 text-sm">Start typing to search…</div>
</div>
<div class="px-4 py-3 border-t border-slate-100 flex justify-between items-center">
<span class="text-xs text-slate-400"><kbd class="px-1.5 py-0.5 bg-slate-100 rounded text-xs">Esc</kbd> to close</span>
<span class="text-xs text-slate-400">Powered by Akabbo Search</span>
</div>
</div>
</div>

<script>
    window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/public/assets/js/app.js"></script>
<script src="<?= APP_URL ?>/public/assets/js/ajax.js"></script>
<script>
// Sidebar toggle functionality (unchanged except constant extraction)
const SIDEBAR_STATE_KEY = 'sidebarCollapsedSections';
function getCollapsedSections() {
return JSON.parse(localStorage.getItem(SIDEBAR_STATE_KEY) || '[]');
}
function saveCollapsedSections(collapsed) {
localStorage.setItem(SIDEBAR_STATE_KEY, JSON.stringify(collapsed));
}
function toggleSection(sectionName) {
const section = document.querySelector(`.nav-section[data-section="${sectionName}"]`);
if (!section) return;
section.classList.toggle('collapsed');
const collapsedSections = getCollapsedSections();
if (section.classList.contains('collapsed')) {
if (!collapsedSections.includes(sectionName)) {
collapsedSections.push(sectionName);
}
} else {
const index = collapsedSections.indexOf(sectionName);
if (index > -1) collapsedSections.splice(index, 1);
}
saveCollapsedSections(collapsedSections);
}
document.addEventListener('DOMContentLoaded', function() {
const collapsedSections = getCollapsedSections();
collapsedSections.forEach(sectionName => {
const section = document.querySelector(`.nav-section[data-section="${sectionName}"]`);
if (section) section.classList.add('collapsed');
});
const activeItem = document.querySelector('.nav-item.active');
if (activeItem) {
const parentSection = activeItem.closest('.nav-section');
if (parentSection && parentSection.classList.contains('collapsed')) {
parentSection.classList.remove('collapsed');
const sectionName = parentSection.dataset.section;
const updated = getCollapsedSections().filter(s => s !== sectionName);
saveCollapsedSections(updated);
}
}
});
// ✅ COMPLIANCE: Uses custom modal instead of native confirm() for logout
function confirmLogout() {
    if (typeof window.confirmAction === 'function') {
        window.confirmAction({
            title: 'Sign Out',
            message: 'Are you sure you want to sign out of Akabbo Social Fund?',
            confirmText: 'Sign Out',
            type: 'danger',
            onConfirm: () => { 
                window.location.href = '<?= APP_URL ?>/auth/logout'; 
            }
        });
    } else {
        // Fallback just in case the modal script hasn't loaded
        window.location.href = '<?= APP_URL ?>/auth/logout';
    }
}
</script>
</body>
</html>