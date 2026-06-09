/**
 * AKABBO SOCIAL FUND
 * AJAX Utility Library
 *
 * Centralized AJAX request handling with automatic CSRF token injection,
 * error handling, loading states, and structured JSON response parsing.
 */

'use strict';

// ════════════════════════════════════════════════════════════════
// CORE AJAX FUNCTION
// ════════════════════════════════════════════════════════════════

/**
 * Make an authenticated AJAX request to the Akabbo API.
 *
 * @param {string}  method   HTTP method (GET, POST, PUT, DELETE, PATCH)
 * @param {string}  url      Endpoint URL (relative or absolute)
 * @param {object}  data     Request body data (for POST/PUT/PATCH)
 * @param {object}  options  Additional options
 * @returns {Promise<object>} Parsed JSON response
 */
window.akabboAjax = async function(method = 'GET', url, data = null, options = {}) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const baseUrl   = document.querySelector('meta[name="app-url"]')?.content || '';

  const fullUrl = url.startsWith('http') ? url : baseUrl + url;

  const headers = {
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-Token':     csrfToken,
    'Accept':           'application/json',
  };

  let body = null;

  if (data instanceof FormData) {
    body = data; // Don't set Content-Type; browser sets boundary for multipart
  } else if (data && typeof data === 'object') {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(data);
  }

  const config = {
    method: method.toUpperCase(),
    headers,
    body: ['GET', 'HEAD'].includes(method.toUpperCase()) ? null : body,
    signal: options.signal,
  };

  try {
    const response = await fetch(fullUrl, config);

    // Handle non-JSON responses gracefully
    const contentType = response.headers.get('Content-Type') || '';
    if (!contentType.includes('application/json')) {
      if (!response.ok) throw new AjaxError(`HTTP ${response.status}: ${response.statusText}`, response.status);
      return { success: true, data: await response.text() };
    }

    const result = await response.json();

    if (!response.ok) {
      throw new AjaxError(result.message || `HTTP Error ${response.status}`, response.status, result);
    }

    return result;

  } catch (err) {
    if (err instanceof AjaxError) throw err;
    if (err.name === 'AbortError') throw new AjaxError('Request was cancelled', 0);
    throw new AjaxError('Network error. Check your internet connection.', 0);
  }
};

/** Custom AJAX error class */
class AjaxError extends Error {
  constructor(message, status = 0, data = null) {
    super(message);
    this.name   = 'AjaxError';
    this.status = status;
    this.data   = data;
  }
}

// ════════════════════════════════════════════════════════════════
// FORM SUBMISSION HELPER
// ════════════════════════════════════════════════════════════════

/**
 * Submit a form via AJAX with automatic loading state management.
 *
 * @param {HTMLFormElement|string} form     Form element or selector
 * @param {object}                 options  Callbacks and configuration
 */
window.submitForm = async function(form, options = {}) {
  if (typeof form === 'string') form = document.querySelector(form);
  if (!form) return;

  const {
    onSuccess  = null,
    onError    = null,
    submitBtn  = form.querySelector('[type="submit"]'),
    loadingText = 'Processing…',
    resetOnSuccess = false,
  } = options;

  const origText = submitBtn?.innerHTML;

  // Set loading state
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
    </svg>${loadingText}`;
  }

  // Clear previous field errors
  form.querySelectorAll('.field-error').forEach(el => el.remove());
  form.querySelectorAll('.border-red-400').forEach(el => el.classList.remove('border-red-400'));

  try {
    const method = (form.querySelector('[name="_method"]')?.value || form.method || 'POST').toUpperCase();
    const result = await akabboAjax(method, form.action, new FormData(form));

    if (resetOnSuccess) form.reset();

    if (onSuccess) {
      onSuccess(result);
    } else {
      window.showToast('success', result.message || 'Operation completed successfully.');
    }

    return result;

  } catch (err) {
    // Show field-level validation errors
    if (err.data?.errors && typeof err.data.errors === 'object') {
      renderFieldErrors(form, err.data.errors);
    }

    if (onError) {
      onError(err);
    } else {
      window.showToast('error', err.message || 'An error occurred. Please try again.');
    }

    throw err;

  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = origText;
    }
  }
};

/**
 * Render field-level validation error messages below their inputs.
 *
 * @param {HTMLFormElement} form   The form containing the fields
 * @param {object}          errors Object mapping field names to error messages
 */
function renderFieldErrors(form, errors) {
  Object.entries(errors).forEach(([field, message]) => {
    const input = form.querySelector(`[name="${field}"]`);
    if (!input) return;

    input.classList.add('border-red-400');

    const errEl = document.createElement('p');
    errEl.className = 'field-error text-xs text-red-500 mt-1 flex items-center gap-1';
    errEl.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ${message}`;

    input.parentElement.appendChild(errEl);

    // Remove error on input
    input.addEventListener('input', () => {
      input.classList.remove('border-red-400');
      errEl.remove();
    }, { once: true });
  });

  // Scroll to first error
  const firstError = form.querySelector('.border-red-400');
  firstError?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// ════════════════════════════════════════════════════════════════
// TABLE AJAX LOADER
// ════════════════════════════════════════════════════════════════

/**
 * Load data into a table via AJAX with loading skeleton.
 *
 * @param {string} tableBodyId  ID of the <tbody> element
 * @param {string} url          Data endpoint URL
 * @param {object} params       Query parameters
 * @param {function} rowRenderer Function that returns HTML string for a row
 * @param {number}  colSpan     Number of columns (for empty state)
 */
window.loadTableData = async function(tableBodyId, url, params = {}, rowRenderer, colSpan = 6) {
  const tbody = document.getElementById(tableBodyId);
  if (!tbody) return;

  // Show skeleton
  tbody.innerHTML = Array(5).fill(`
    <tr class="animate-pulse">
      ${Array(colSpan).fill('<td><div class="h-4 bg-slate-100 rounded w-3/4 mx-auto"></div></td>').join('')}
    </tr>`).join('');

  const query = new URLSearchParams(params).toString();
  const fullUrl = query ? `${url}?${query}` : url;

  try {
    const result = await akabboAjax('GET', fullUrl);
    const data   = result.data?.data || result.data || [];

    if (!data.length) {
      tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-10 text-slate-400 text-sm">
        <i class="fa-regular fa-folder-open text-3xl block mb-2 text-slate-200"></i>
        No records found.
      </td></tr>`;
      return;
    }

    tbody.innerHTML = data.map(rowRenderer).join('');
    return result.data;

  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center py-8 text-red-500 text-sm">
      <i class="fa-solid fa-circle-xmark text-2xl block mb-2"></i>
      Failed to load data. <button onclick="location.reload()" class="underline ml-1">Retry</button>
    </td></tr>`;
    throw err;
  }
};

// ════════════════════════════════════════════════════════════════
// DELETE WITH CONFIRM
// ════════════════════════════════════════════════════════════════

/**
 * Delete a record after confirmation.
 *
 * @param {string}   url        DELETE endpoint URL
 * @param {string}   label      Human-readable record name for the confirm dialog
 * @param {Function} onSuccess  Callback after successful deletion
 */
window.deleteRecord = function(url, label, onSuccess) {
  window.confirmAction({
    title:       'Delete Record',
    message:     `Are you sure you want to delete "${label}"? This action moves the record to Trash and can be recovered.`,
    confirmText: 'Delete',
    type:        'danger',
    onConfirm:   async () => {
      try {
        const result = await akabboAjax('DELETE', url);
        window.showToast('success', result.message || `"${label}" has been deleted.`);
        onSuccess && onSuccess(result);
      } catch (err) {
        window.showToast('error', err.message || 'Failed to delete record.');
      }
    }
  });
};

// ════════════════════════════════════════════════════════════════
// LIVE SEARCH HELPER
// ════════════════════════════════════════════════════════════════

/**
 * Set up a live search input that fetches results as the user types.
 *
 * @param {string}   inputId      ID of the search input element
 * @param {string}   url          Search endpoint URL
 * @param {Function} onResults    Callback with fetched results array
 * @param {number}   minChars     Minimum characters before search fires
 */
window.setupLiveSearch = function(inputId, url, onResults, minChars = 2, delay = 350) {
  const input = document.getElementById(inputId);
  if (!input) return;

  let debounce;
  let controller;

  input.addEventListener('input', async () => {
    const query = input.value.trim();
    clearTimeout(debounce);

    if (query.length < minChars) {
      onResults([]);
      return;
    }

    debounce = setTimeout(async () => {
      controller?.abort();
      controller = new AbortController();

      try {
        const result = await akabboAjax('GET', `${url}?q=${encodeURIComponent(query)}`, null, {
          signal: controller.signal
        });
        onResults(result.data || []);
      } catch (err) {
        if (err.message !== 'Request was cancelled') {
          console.error('Live search error:', err);
        }
      }
    }, delay);
  });
};

// ════════════════════════════════════════════════════════════════
// STATUS UPDATER (Inline approval / rejection)
// ════════════════════════════════════════════════════════════════

/**
 * Update a record's status inline via AJAX (e.g. approve/reject loans).
 *
 * @param {string}   url        API endpoint
 * @param {object}   payload    Data to send (e.g. { status: 'approved' })
 * @param {Function} onSuccess  Success callback
 */
window.updateStatus = async function(url, payload, onSuccess) {
  try {
    const result = await akabboAjax('PATCH', url, payload);
    window.showToast('success', result.message || 'Status updated successfully.');
    onSuccess && onSuccess(result);
  } catch (err) {
    window.showToast('error', err.message || 'Failed to update status.');
  }
};

// ════════════════════════════════════════════════════════════════
// AUTO-REGISTER AJAX FORMS
// ════════════════════════════════════════════════════════════════

/**
 * Automatically handle forms with data-ajax="true" attribute.
 * These forms are submitted via AJAX without page reload.
 */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('form[data-ajax="true"]').forEach(form => {
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const redirect = form.dataset.redirect;
      const reload   = form.dataset.reload === 'true';

      await submitForm(form, {
        onSuccess: result => {
          window.showToast('success', result.message || 'Done!');
          if (redirect) {
            setTimeout(() => { window.location.href = redirect; }, 800);
          } else if (reload) {
            setTimeout(() => location.reload(), 800);
          }
        }
      });
    });
  });
});
