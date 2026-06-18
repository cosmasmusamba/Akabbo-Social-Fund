/**
 * AKABBO SOCIAL FUND
 * Main Application JavaScript
 *
 * Global UI behaviors: sidebar, toasts, modals, keyboard shortcuts,
 * user dropdown, page loader, global search, and unified AJAX form handling.
 */

'use strict';

// ════════════════════════════════════════════════════════════════
// SIDEBAR MANAGEMENT
// ════════════════════════════════════════════════════════════════

let sidebarOpen = false;
let desktopCollapsed = false;

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

function closeSidebar() {
  sidebarOpen = false;
  document.getElementById('sidebar')?.classList.remove('open');
  document.getElementById('sidebarOverlay').style.display = 'none';
  document.body.style.overflow = '';
}

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

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeSidebar();
    closeGlobalSearch();
    document.getElementById('userDropdown')?.classList.add('hidden');
  }
  if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
    e.preventDefault();
    openGlobalSearch();
  }
});

// ════════════════════════════════════════════════════════════════
// USER DROPDOWN
// ════════════════════════════════════════════════════════════════

function toggleUserMenu(btn) {
  document.getElementById('userDropdown')?.classList.toggle('hidden');
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

window.showToast = function(type = 'info', message = '', duration = 4500) {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const t = TOAST_ICONS[type] || TOAST_ICONS.info;
  const id = 'toast_' + Date.now();

  const toast = document.createElement('div');
  toast.id    = id;
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <div style="color:${t.color};font-size:1.1rem;flex-shrink:0"><i class="fa-solid ${t.icon}"></i></div>
    <div class="flex-1"><p class="text-sm font-semibold text-slate-800">${escapeHtml(message)}</p></div>
    <button onclick="dismissToast('${id}')" class="text-slate-300 hover:text-slate-600 transition-colors flex-shrink-0 ml-2"><i class="fa-solid fa-xmark text-sm"></i></button>`;

  container.appendChild(toast);
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

function showLoader() { document.getElementById('pageLoader')?.style.setProperty('display', 'flex'); }
function hideLoader() { document.getElementById('pageLoader')?.style.setProperty('display', 'none'); }

document.addEventListener('click', e => {
  const link = e.target.closest('a[href]');
  if (!link) return;
  const href = link.getAttribute('href') || '';
  if (href.startsWith('#') || href.startsWith('javascript') || link.hasAttribute('download') || link.dataset.noLoader || link.target === '_blank' || href.startsWith('http')) return;
  showLoader();
});

window.addEventListener('pageshow', hideLoader);
window.addEventListener('load', hideLoader);

// ════════════════════════════════════════════════════════════════
// GLOBAL AJAX & CSRF INTERCEPTOR (CRITICAL FOR BACKEND SYNC)
// ════════════════════════════════════════════════════════════════

/**
 * Intercept all fetch requests to automatically include the CSRF token.
 * This ensures all AJAX calls pass the backend's $this->verifyCsrf() check.
 */
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                   || document.querySelector('input[name="csrf_token"]')?.value;

    options.headers = options.headers || {};
    
    if (!(options.body instanceof FormData) && !options.headers['Content-Type']) {
        options.headers['Content-Type'] = 'application/json';
    }

    if (csrfToken && ['POST', 'PUT', 'DELETE', 'PATCH'].includes((options.method || 'GET').toUpperCase())) {
        if (options.body instanceof FormData) {
            options.body.append('csrf_token', csrfToken);
        } else {
            options.headers['X-CSRF-Token'] = csrfToken;
        }
    }

    if (!options.headers['Accept']) options.headers['Accept'] = 'application/json';
    options.headers['X-Requested-With'] = 'XMLHttpRequest';

    return originalFetch(url, options);
};

// ════════════════════════════════════════════════════════════════
// UNIFIED AJAX FORM HANDLER (Replaces native form submissions)
// ════════════════════════════════════════════════════════════════

/**
 * Handles form submission via AJAX, perfectly syncing with backend jsonSuccess/jsonError.
 * Automatically handles field validation errors (422), success modals, and redirects.
 */
window.handleAjaxForm = function(formSelector = 'form[data-ajax="true"]') {
    document.querySelectorAll(formSelector).forEach(form => {
        if (form.dataset.ajaxBound) return; 
        form.dataset.ajaxBound = 'true';

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
            
            clearFormErrors(form);
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processing...';
            }

            try {
                const formData = new FormData(form);
                const res = await fetch(form.action, { method: form.method || 'POST', body: formData });
                const json = await res.json();

                if (res.ok && json.success) {
                    // SUCCESS: Show toast and redirect if backend provides a URL
                    showToast('success', json.message || 'Operation successful!');
                    if (json.redirect) setTimeout(() => window.location.href = json.redirect, 800);
                } else {
                    // ERROR: Handle validation (422), forbidden (403), or general errors
                    if (res.status === 422 && json.errors) {
                        renderFormErrors(form, json.errors);
                        showToast('error', json.message || 'Please correct the highlighted fields.');
                    } else if (res.status === 403) {
                        showSecurityModal(json.message);
                    } else {
                        showErrorModal(json.message || 'An unexpected error occurred.');
                    }
                }
            } catch (err) {
                console.error('Form submission error:', err);
                showErrorModal('Network error. Please check your connection and try again.');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            }
        });
    });
};

function clearFormErrors(form) {
    form.querySelectorAll('.error-message').forEach(el => el.remove());
    form.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error', 'border-red-500'));
}

function renderFormErrors(form, errors) {
    for (const [field, message] of Object.entries(errors)) {
        const input = form.querySelector(`[name="${field}"], [name="${field}[]"]`);
        if (input) {
            input.classList.add('input-error', 'border-red-500');
            const errEl = document.createElement('p');
            errEl.className = 'error-message text-xs text-red-500 mt-1';
            errEl.textContent = Array.isArray(message) ? message[0] : message;
            input.parentNode.appendChild(errEl);
        }
    }
}

// ════════════════════════════════════════════════════════════════
// STANDARDIZED MODALS (Strictly avoiding native alert/confirm)
// ════════════════════════════════════════════════════════════════

window.showSuccessModal = function(message, redirectUrl = null) {
    showModal({ title: 'Success', message, icon: 'fa-circle-check text-green-500', confirmText: 'OK', confirmClass: 'bg-green-600 hover:bg-green-700', onConfirm: () => { if (redirectUrl) window.location.href = redirectUrl; } });
};

window.showErrorModal = function(message) {
    showModal({ title: 'Error', message, icon: 'fa-circle-xmark text-red-500', confirmText: 'Dismiss', confirmClass: 'bg-red-600 hover:bg-red-700' });
};

window.showSecurityModal = function(message) {
    showModal({ title: 'Access Denied', message: message || 'You do not have permission to perform this action.', icon: 'fa-shield-halved text-amber-500', confirmText: 'Understood', confirmClass: 'bg-amber-600 hover:bg-amber-700' });
};

function showModal({ title, message, icon, confirmText = 'OK', confirmClass = 'bg-blue-600 hover:bg-blue-700', onConfirm }) {
    const id = 'modal_' + Date.now();
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.id = id;
    overlay.innerHTML = `
        <div class="modal" style="max-width:420px">
            <div class="modal-header"><div class="flex items-center gap-3"><i class="fa-solid ${icon} text-xl"></i><h3 class="font-bold text-slate-800">${escapeHtml(title)}</h3></div></div>
            <div class="modal-body"><p class="text-slate-600 text-sm">${escapeHtml(message)}</p></div>
            <div class="modal-footer">
                <button onclick="document.getElementById('${id}').remove()" class="btn btn-secondary">Close</button>
                <button id="${id}_confirm" class="btn text-white ${confirmClass}">${escapeHtml(confirmText)}</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    document.getElementById(id + '_confirm').addEventListener('click', () => { overlay.remove(); if (onConfirm) onConfirm(); });
}

window.confirmAction = function({ title = 'Confirm', message = 'Are you sure?', confirmText = 'Confirm', type = 'danger', onConfirm } = {}) {
  const colors = { danger: { btn: 'bg-red-600 hover:bg-red-700', icon: 'fa-triangle-exclamation text-red-500' }, warning: { btn: 'bg-amber-500 hover:bg-amber-600', icon: 'fa-exclamation-circle text-amber-500' }, primary: { btn: 'bg-green-700 hover:bg-green-800', icon: 'fa-circle-question text-green-500' } };
  const c = colors[type] || colors.danger;
  const id = 'confirm_' + Date.now();
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.id = id;
  overlay.innerHTML = `<div class="modal" style="max-width:420px"><div class="modal-header"><div class="flex items-center gap-3"><i class="fa-solid ${c.icon} text-xl"></i><h3 class="font-bold text-slate-800">${escapeHtml(title)}</h3></div></div><div class="modal-body"><p class="text-slate-600 text-sm">${escapeHtml(message)}</p></div><div class="modal-footer"><button onclick="document.getElementById('${id}').remove()" class="btn btn-secondary">Cancel</button><button id="${id}_confirm" class="btn text-white ${c.btn}">${escapeHtml(confirmText)}</button></div></div>`;
  document.body.appendChild(overlay);
  document.getElementById(id + '_confirm').addEventListener('click', () => { overlay.remove(); onConfirm && onConfirm(); });
};

// ════════════════════════════════════════════════════════════════
// GLOBAL SEARCH (Unchanged from your excellent implementation)
// ════════════════════════════════════════════════════════════════

function openGlobalSearch() {
  document.getElementById('globalSearchModal')?.classList.remove('hidden');
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
      const baseUrl = window.APP_URL || '';
      const res = await fetch(`${baseUrl}/search?q=${encodeURIComponent(query)}`);
      
      // HANDLE 403 FORBIDDEN (User lacks permissions)
      if (res.status === 403) {
          const json = await res.json().catch(() => ({}));
          results.innerHTML = `
            <div class="px-4 py-6 text-center text-red-500 text-sm">
              <i class="fa-solid fa-lock mr-2"></i>
              ${escapeHtml(json.message || 'You do not have permission to perform searches.')}
            </div>`;
          return;
      }

      // HANDLE 404 NOT FOUND (Route is missing in web.php)
      if (res.status === 404) {
          results.innerHTML = `
            <div class="px-4 py-6 text-center text-amber-600 text-sm">
              <i class="fa-solid fa-triangle-exclamation mr-2"></i>
              Search endpoint not found. Please verify the route in <code>routes/web.php</code>.
            </div>`;
          return;
      }

      // Handle other HTTP errors
      if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
      
      const json = await res.json();
      if (json.success) {
          renderGlobalResults(json.data || {}, query);
      } else {
          throw new Error(json.message || 'Search failed');
      }
    } catch (err) {
      console.error('Global search error:', err);
      results.innerHTML = `
        <div class="px-4 py-6 text-center text-red-500 text-sm">
          <i class="fa-solid fa-circle-xmark mr-2"></i>
          Network error. Please check your connection and try again.
        </div>`;
    }
  }, 300);
}

function renderGlobalResults(data, query) {
  const results = document.getElementById('globalSearchResults');
  const { 
    members = [], loans = [], transactions = [], savings = [], 
    groups = [], shares = [], expenses = [], users = [], approvals = [] 
  } = data;
  
  const total = members.length + loans.length + transactions.length + savings.length + 
                groups.length + shares.length + expenses.length + users.length + approvals.length;

  if (!total) {
    results.innerHTML = `<div class="px-4 py-8 text-center text-slate-400 text-sm">No results found for "<strong>${escapeHtml(query)}</strong>"</div>`;
    return;
  }

  const baseUrl = window.APP_URL || '';
  let html = '';

  // Local bulletproof helpers
  const escape = (str) => {
    if (typeof str !== 'string') str = String(str ?? '');
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  };
  const hl = (text) => {
    const safe = escape(text || '');
    if (!query) return safe;
    const re = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return safe.replace(re, '<mark class="bg-yellow-100 text-yellow-800 rounded px-0.5">$1</mark>');
  };

  // Helper to generate section headers
  const sectionHeader = (title) => `<div class="px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50 border-t border-slate-100">${title}</div>`;

  // 1. Members
  if (members.length) {
    html += sectionHeader('Members');
    members.forEach(m => {
      html += `<a href="${baseUrl}/members/${m.id}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="avatar-circle w-9 h-9 text-xs">${escape(m.initials || '?')}</div>
        <div class="flex-1 min-w-0"><div class="text-sm font-semibold text-slate-800">${hl(m.full_name)}</div><div class="text-xs text-slate-400">${escape(m.member_no)} · ${escape(m.phone)}</div></div>
        <span class="text-xs font-semibold text-emerald-700">${escape(m.total_savings_fmt)}</span>
      </a>`;
    });
  }

  // 2. Loans
  if (loans.length) {
    html += sectionHeader('Loans');
    loans.forEach(l => {
      html += `<a href="${baseUrl}/loans/${l.id}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-file-contract text-sm"></i></div>
        <div class="flex-1"><div class="text-sm font-semibold text-slate-800">${hl(l.loan_no)}</div><div class="text-xs text-slate-400">${escape(l.member_name)} · ${escape(l.product_name)}</div></div>
        <span class="badge bg-slate-100 text-slate-700">${escape(l.status)}</span>
      </a>`;
    });
  }

  // 3. Transactions
  if (transactions.length) {
    html += sectionHeader('Transactions');
    transactions.forEach(t => {
      html += `<a href="${baseUrl}/transactions/${t.id}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="w-9 h-9 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-arrow-right-arrow-left text-sm"></i></div>
        <div class="flex-1"><div class="text-sm font-semibold text-slate-800">${hl(t.txn_ref)}</div><div class="text-xs text-slate-400">${escape(t.member_name)} · ${escape(t.type_label)}</div></div>
        <span class="text-xs font-semibold text-purple-700">${escape(t.amount_fmt)}</span>
      </a>`;
    });
  }

  // 4. Savings
  if (savings.length) {
    html += sectionHeader('Savings Accounts');
    savings.forEach(s => {
      html += `<a href="${baseUrl}/savings/${s.id}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-piggy-bank text-sm"></i></div>
        <div class="flex-1"><div class="text-sm font-semibold text-slate-800">${hl(s.account_no)}</div><div class="text-xs text-slate-400">${escape(s.member_name)}</div></div>
        <span class="text-xs font-semibold text-emerald-700">${escape(s.balance_fmt)}</span>
      </a>`;
    });
  }

  // 5. Groups
  if (groups.length) {
    html += sectionHeader('Savings Groups');
    groups.forEach(g => {
      html += `<a href="${baseUrl}/groups" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="w-9 h-9 rounded-full bg-teal-100 text-teal-600 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-people-group text-sm"></i></div>
        <div class="flex-1"><div class="text-sm font-semibold text-slate-800">${hl(g.name)}</div><div class="text-xs text-slate-400">Savings Group · ${escape(g.date_fmt)}</div></div>
      </a>`;
    });
  }

  // 6. Shares
  if (shares.length) {
    html += sectionHeader('Shares');
    shares.forEach(s => {
      html += `<a href="${baseUrl}/shares" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-chart-pie text-sm"></i></div>
        <div class="flex-1"><div class="text-sm font-semibold text-slate-800">${hl(s.txn_ref)}</div><div class="text-xs text-slate-400">${escape(s.member_name)} · ${escape(s.type_label)}</div></div>
        <span class="text-xs font-semibold text-indigo-700">${escape(s.amount_fmt)}</span>
      </a>`;
    });
  }

  // 7. Expenses
  if (expenses.length) {
    html += sectionHeader('Expenses');
    expenses.forEach(e => {
      html += `<a href="${baseUrl}/expenses" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="w-9 h-9 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-receipt text-sm"></i></div>
        <div class="flex-1"><div class="text-sm font-semibold text-slate-800">${hl(e.expense_ref)}</div><div class="text-xs text-slate-400">${escape(e.description)} · ${escape(e.category_name || 'Uncategorized')}</div></div>
        <span class="text-xs font-semibold text-orange-700">${escape(e.amount_fmt)}</span>
      </a>`;
    });
  }

  // 8. System Users
  if (users.length) {
    html += sectionHeader('System Users');
    users.forEach(u => {
      html += `<a href="${baseUrl}/users/${u.id}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="avatar-circle w-9 h-9 text-xs bg-slate-200 text-slate-600">${escape(u.initials || '?')}</div>
        <div class="flex-1 min-w-0"><div class="text-sm font-semibold text-slate-800">${hl(u.full_name)}</div><div class="text-xs text-slate-400">${escape(u.email)} · ${escape(u.role_name)}</div></div>
        <span class="badge bg-slate-100 text-slate-600">${escape(u.status)}</span>
      </a>`;
    });
  }

  // 9. Pending Approvals
  if (approvals.length) {
    html += sectionHeader('Pending Approvals');
    approvals.forEach(a => {
      html += `<a href="${baseUrl}/approvals" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors">
        <div class="w-9 h-9 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center flex-shrink-0"><i class="fa-solid fa-circle-check text-sm"></i></div>
        <div class="flex-1"><div class="text-sm font-semibold text-slate-800">${hl(a.reference_type)} #${a.reference_id}</div><div class="text-xs text-slate-400">${escape(a.notes || 'No notes')} · ${escape(a.date_fmt)}</div></div>
        <span class="text-xs font-semibold text-purple-700">${escape(a.amount_fmt)}</span>
      </a>`;
    });
  }

  results.innerHTML = html;
}

// ════════════════════════════════════════════════════════════════
// INITIALIZATION & AUTO-BINDING
// ════════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    // 1. Automatically bind AJAX handling to all forms marked with data-ajax="true"
    window.handleAjaxForm();
    
    // 2. Auto-bind confirm dialogs to buttons/links with data-confirm attribute
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const message = this.dataset.confirm || 'Are you sure you want to proceed?';
            const url = this.href || this.dataset.action;
            
            window.confirmAction({
                title: 'Confirm Action',
                message: message,
                type: this.dataset.confirmType || 'danger',
                onConfirm: () => {
                    if (url && url !== '#') {
                        window.location.href = url;
                    } else if (this.form) {
                        this.form.requestSubmit(); // Triggers the AJAX handler if form has data-ajax="true"
                    }
                }
            });
        });
    });

    // 3. Flash message auto-dismiss
    const banner = document.getElementById('flashBanner');
    if (banner) {
        setTimeout(() => {
            banner.style.transition = 'opacity 0.4s';
            banner.style.opacity = '0';
            setTimeout(() => banner.remove(), 400);
        }, 5000);
    }
});

// ════════════════════════════════════════════════════════════════
// UTILITY FUNCTIONS
// ════════════════════════════════════════════════════════════════

function escapeHtml(str) {
  if (typeof str !== 'string') str = String(str ?? '');
  return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

window.formatCurrency = function(amount, symbol = 'USh') {
  return symbol + ' ' + Number(amount).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

window.copyToClipboard = function(text, label = 'Copied!') {
  navigator.clipboard.writeText(text).then(() => window.showToast('success', label)).catch(() => window.showToast('error', 'Copy failed.'));
};

window.printSection = function(elementId) {
  const el = document.getElementById(elementId);
  if (!el) return;
  const win = window.open('', '_blank');
  win.document.write(`<html><head><title>Akabbo Social Fund — Print</title><style>body{font-family:sans-serif;margin:20px;color:#1e293b}table{width:100%;border-collapse:collapse}th,td{padding:8px 12px;border:1px solid #e2e8f0;font-size:13px}th{background:#f8fafc;font-weight:600}</style></head><body>${el.innerHTML}</body></html>`);
  win.document.close(); win.focus(); win.print(); win.close();
};

(function addToastOutStyle() {
  const style = document.createElement('style');
  style.textContent = `@keyframes toastOut { to { opacity:0; transform:translateX(20px); max-height:0; margin-bottom:0; padding:0; overflow:hidden; } }`;
  document.head.appendChild(style);
})();