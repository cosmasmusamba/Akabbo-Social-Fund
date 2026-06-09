/**
 * AKABBO SOCIAL FUND
 * Main Application JavaScript
 *
 * Global UI behaviors: sidebar, toasts, modals, keyboard shortcuts,
 * user dropdown, page loader, and global search functionality.
 */

'use strict';

// ════════════════════════════════════════════════════════════════
// SIDEBAR MANAGEMENT
// ════════════════════════════════════════════════════════════════

let sidebarOpen = false;
let desktopCollapsed = false;

/** Toggle sidebar on mobile */
function toggleSidebar() {
  sidebarOpen = !sidebarOpen;
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');

  if (sidebarOpen) {
    sidebar.classList.add('open');
    overlay.style.display = 'block';
    document.body.style.overflow = 'hidden';
  } else {
    closeSidebar();
  }
}

/** Close mobile sidebar */
function closeSidebar() {
  sidebarOpen = false;
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  sidebar.classList.remove('open');
  overlay.style.display = 'none';
  document.body.style.overflow = '';
}

/** Toggle desktop sidebar collapse */
function toggleDesktopSidebar() {
  desktopCollapsed = !desktopCollapsed;
  const sidebar = document.getElementById('sidebar');
  const main    = document.getElementById('mainContent');

  if (desktopCollapsed) {
    sidebar.classList.add('collapsed');
    main.classList.add('full');
    localStorage.setItem('sidebar_collapsed', '1');
  } else {
    sidebar.classList.remove('collapsed');
    main.classList.remove('full');
    localStorage.setItem('sidebar_collapsed', '0');
  }
}

// Restore sidebar state on desktop
(function restoreSidebarState() {
  if (window.innerWidth >= 1024) {
    const collapsed = localStorage.getItem('sidebar_collapsed') === '1';
    if (collapsed) {
      document.getElementById('sidebar')?.classList.add('collapsed');
      document.getElementById('mainContent')?.classList.add('full');
      desktopCollapsed = true;
    }
  }
})();

// Close sidebar on Escape key
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeSidebar();
    closeGlobalSearch();
    document.getElementById('userDropdown')?.classList.add('hidden');
  }

  // ⌘K / Ctrl+K — Global search
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault();
    openGlobalSearch();
  }
});

// ════════════════════════════════════════════════════════════════
// USER DROPDOWN
// ════════════════════════════════════════════════════════════════

function toggleUserMenu(btn) {
  const dd = document.getElementById('userDropdown');
  dd?.classList.toggle('hidden');
  event.stopPropagation();
}

document.addEventListener('click', () => {
  document.getElementById('userDropdown')?.classList.add('hidden');
});

// ════════════════════════════════════════════════════════════════
// TOAST NOTIFICATIONS
// ════════════════════════════════════════════════════════════════

const TOAST_ICONS = {
  success: { icon: 'fa-circle-check',      color: '#10b981' },
  error:   { icon: 'fa-circle-xmark',      color: '#ef4444' },
  warning: { icon: 'fa-triangle-exclamation', color: '#f59e0b' },
  info:    { icon: 'fa-circle-info',        color: '#3b82f6' },
};

/**
 * Display a toast notification.
 *
 * @param {string} type    - 'success' | 'error' | 'warning' | 'info'
 * @param {string} message - Toast message text
 * @param {number} duration - Auto-dismiss duration in ms (default 4000)
 */
window.showToast = function(type = 'info', message = '', duration = 4500) {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const t = TOAST_ICONS[type] || TOAST_ICONS.info;
  const id = 'toast_' + Date.now();

  const toast = document.createElement('div');
  toast.id    = id;
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <div style="color:${t.color};font-size:1.1rem;flex-shrink:0">
      <i class="fa-solid ${t.icon}"></i>
    </div>
    <div class="flex-1">
      <p class="text-sm font-semibold text-slate-800">${escapeHtml(message)}</p>
    </div>
    <button onclick="dismissToast('${id}')" class="text-slate-300 hover:text-slate-600 transition-colors flex-shrink-0 ml-2">
      <i class="fa-solid fa-xmark text-sm"></i>
    </button>`;

  container.appendChild(toast);

  // Auto dismiss
  const timer = setTimeout(() => dismissToast(id), duration);
  toast._timer = timer;
};

function dismissToast(id) {
  const el = document.getElementById(id);
  if (!el) return;
  clearTimeout(el._timer);
  el.style.animation = 'toastOut 0.25s ease forwards';
  el.addEventListener('animationend', () => el.remove());
}

// ════════════════════════════════════════════════════════════════
// PAGE LOADER
// ════════════════════════════════════════════════════════════════

function showLoader() {
  const loader = document.getElementById('pageLoader');
  if (loader) { loader.style.display = 'flex'; }
}

function hideLoader() {
  const loader = document.getElementById('pageLoader');
  if (loader) { loader.style.display = 'none'; }
}

// Show loader on link navigation (except AJAX / download links)
document.addEventListener('click', e => {
  const link = e.target.closest('a[href]');
  if (!link) return;
  const href = link.getAttribute('href') || '';

  // Skip: external, #anchors, download, data-no-loader, javascript:
  if (href.startsWith('#') || href.startsWith('javascript') ||
      link.hasAttribute('download') || link.dataset.noLoader ||
      link.target === '_blank' || href.startsWith('http')) return;

  showLoader();
});

window.addEventListener('pageshow', hideLoader);
window.addEventListener('load', hideLoader);

// ════════════════════════════════════════════════════════════════
// GLOBAL SEARCH
// ════════════════════════════════════════════════════════════════

function openGlobalSearch() {
  const modal = document.getElementById('globalSearchModal');
  if (!modal) return;
  modal.classList.remove('hidden');
  document.getElementById('globalSearchInput')?.focus();
  document.body.style.overflow = 'hidden';
}

function closeGlobalSearch() {
  document.getElementById('globalSearchModal')?.classList.add('hidden');
  document.body.style.overflow = '';
  const input = document.getElementById('globalSearchInput');
  if (input) input.value = '';
  const results = document.getElementById('globalSearchResults');
  if (results) results.innerHTML = '<div class="px-4 py-8 text-center text-slate-400 text-sm">Start typing to search…</div>';
}

let globalSearchDebounce;
async function runGlobalSearch(query) {
  clearTimeout(globalSearchDebounce);
  const results = document.getElementById('globalSearchResults');

  if (query.length < 2) {
    results.innerHTML = '<div class="px-4 py-8 text-center text-slate-400 text-sm">Start typing to search…</div>';
    return;
  }

  results.innerHTML = '<div class="px-4 py-6 text-center text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Searching…</div>';

  globalSearchDebounce = setTimeout(async () => {
    try {
      const res  = await akabboAjax('GET', `/search?q=${encodeURIComponent(query)}`);
      renderGlobalResults(res.data || {}, query);
    } catch {
      results.innerHTML = '<div class="px-4 py-4 text-center text-red-500 text-sm">Search failed. Please try again.</div>';
    }
  }, 300);
}

function renderGlobalResults(data, query) {
  const results = document.getElementById('globalSearchResults');
  const { members = [], loans = [], transactions = [] } = data;
  const total = members.length + loans.length + transactions.length;

  if (!total) {
    results.innerHTML = `<div class="px-4 py-8 text-center text-slate-400 text-sm">No results found for "<strong>${escapeHtml(query)}</strong>"</div>`;
    return;
  }

  let html = '';

  if (members.length) {
    html += `<div class="px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50">Members</div>`;
    members.forEach(m => {
      html += `<a href="/members/${m.id}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="avatar-circle w-9 h-9 text-xs">${escapeHtml(m.initials || '?')}</div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold text-slate-800">${highlight(m.full_name, query)}</div>
          <div class="text-xs text-slate-400">${escapeHtml(m.member_no)} · ${escapeHtml(m.phone)}</div>
        </div>
        <span class="text-xs font-semibold text-emerald-700">${escapeHtml(m.total_savings_fmt || '')}</span>
      </a>`;
    });
  }

  if (loans.length) {
    html += `<div class="px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50 border-t border-slate-100">Loans</div>`;
    loans.forEach(l => {
      html += `<a href="/loans/${l.id}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0">
          <i class="fa-solid fa-file-contract text-sm"></i>
        </div>
        <div class="flex-1">
          <div class="text-sm font-semibold text-slate-800">${highlight(l.loan_no, query)}</div>
          <div class="text-xs text-slate-400">${escapeHtml(l.member_name)} · ${escapeHtml(l.product_name)}</div>
        </div>
        <span class="badge badge-${l.status}">${escapeHtml(l.status)}</span>
      </a>`;
    });
  }

  results.innerHTML = html;
}

function highlight(text, query) {
  if (!query) return escapeHtml(text);
  const escaped = escapeHtml(text);
  const re      = new RegExp(`(${escapeHtml(query).replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`, 'gi');
  return escaped.replace(re, '<mark class="bg-yellow-100 text-yellow-800 rounded px-0.5">$1</mark>');
}

// ════════════════════════════════════════════════════════════════
// FLASH MESSAGE AUTO-DISMISS
// ════════════════════════════════════════════════════════════════

(function autoDismissFlash() {
  const banner = document.getElementById('flashBanner');
  if (banner) {
    setTimeout(() => {
      banner.style.transition = 'opacity 0.4s';
      banner.style.opacity    = '0';
      setTimeout(() => banner.remove(), 400);
    }, 5000);
  }
})();

// ════════════════════════════════════════════════════════════════
// CONFIRM DIALOG HELPER
// ════════════════════════════════════════════════════════════════

/**
 * Show a custom confirm dialog.
 *
 * @param {object} opts - { title, message, confirmText, type, onConfirm }
 */
window.confirmAction = function({ title = 'Confirm', message = 'Are you sure?', confirmText = 'Confirm', type = 'danger', onConfirm } = {}) {
  const colors = {
    danger:  { btn: 'bg-red-600 hover:bg-red-700', icon: 'fa-triangle-exclamation text-red-500' },
    warning: { btn: 'bg-amber-500 hover:bg-amber-600', icon: 'fa-exclamation-circle text-amber-500' },
    primary: { btn: 'bg-green-700 hover:bg-green-800', icon: 'fa-circle-question text-green-500' },
  };
  const c   = colors[type] || colors.danger;
  const id  = 'confirm_' + Date.now();

  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.id = id;
  overlay.innerHTML = `
    <div class="modal" style="max-width:420px">
      <div class="modal-header">
        <div class="flex items-center gap-3">
          <i class="fa-solid ${c.icon} text-xl"></i>
          <h3 class="font-bold text-slate-800">${escapeHtml(title)}</h3>
        </div>
      </div>
      <div class="modal-body">
        <p class="text-slate-600 text-sm">${escapeHtml(message)}</p>
      </div>
      <div class="modal-footer">
        <button onclick="document.getElementById('${id}').remove()"
          class="btn btn-secondary">Cancel</button>
        <button id="${id}_confirm"
          class="btn text-white ${c.btn}">${escapeHtml(confirmText)}</button>
      </div>
    </div>`;

  document.body.appendChild(overlay);
  document.getElementById(id + '_confirm').addEventListener('click', () => {
    overlay.remove();
    onConfirm && onConfirm();
  });
};

// ════════════════════════════════════════════════════════════════
// UTILITY FUNCTIONS
// ════════════════════════════════════════════════════════════════

/** Escape HTML entities to prevent XSS in dynamic content */
function escapeHtml(str) {
  if (typeof str !== 'string') str = String(str ?? '');
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

/** Format a number as currency (client-side) */
window.formatCurrency = function(amount, symbol = 'USh') {
  return symbol + ' ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

/** Copy text to clipboard */
window.copyToClipboard = function(text, label = 'Copied!') {
  navigator.clipboard.writeText(text).then(() => {
    window.showToast('success', label);
  }).catch(() => {
    window.showToast('error', 'Copy failed. Please copy manually.');
  });
};

/** Debounce function factory */
window.debounce = function(fn, delay = 300) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
};

// ════════════════════════════════════════════════════════════════
// PRINT HELPER
// ════════════════════════════════════════════════════════════════

window.printSection = function(elementId) {
  const el = document.getElementById(elementId);
  if (!el) return;
  const win = window.open('', '_blank');
  win.document.write(`
    <html><head><title>Akabbo Social Fund — Print</title>
    <style>body{font-family:sans-serif;margin:20px;color:#1e293b}
    table{width:100%;border-collapse:collapse}
    th,td{padding:8px 12px;border:1px solid #e2e8f0;font-size:13px}
    th{background:#f8fafc;font-weight:600}</style>
    </head><body>${el.innerHTML}</body></html>`);
  win.document.close();
  win.focus();
  win.print();
  win.close();
};

// ════════════════════════════════════════════════════════════════
// ADD CSS FOR TOAST OUT ANIMATION
// ════════════════════════════════════════════════════════════════

(function addToastOutStyle() {
  const style = document.createElement('style');
  style.textContent = `
    @keyframes toastOut {
      to { opacity:0; transform:translateX(20px); max-height:0; margin-bottom:0; padding:0; overflow:hidden; }
    }`;
  document.head.appendChild(style);
})();
