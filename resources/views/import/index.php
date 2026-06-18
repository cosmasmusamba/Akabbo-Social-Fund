<?php
use App\Helpers\Format;
$pageTitle   = 'Import & Export';
$activePage  = 'import';
$breadcrumbs = ['Import & Export' => null];
$recentImports = $recentImports ?? [];
?>
<style>
.drop-zone {
    border: 2px dashed #d1d5db; border-radius: 14px; padding: 40px 20px;
    text-align: center; transition: all 0.2s; cursor: pointer; background: #fafafa;
}
.drop-zone.drag-over { border-color: var(--green-bright, #10b981); background: #f0fdf9; }
.drop-zone:hover { border-color: var(--green-mid, #059669); background: #f0fdf9; }
.progress-bar { height: 6px; background: #e8edf2; border-radius: 10px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 10px; background: var(--green-mid, #059669); transition: width 0.3s; width: 0%; }
.import-tab {
    padding: 8px 16px; border-radius: 8px; font-size: 0.875rem; font-weight: 600;
    color: #64748b; background: transparent; border: 1.5px solid transparent;
    transition: all 0.18s; cursor: pointer;
}
.import-tab:hover { background: #f8fafc; color: #374151; }
.import-tab.active { background: #f0fdf9; color: #065f46; border-color: #a7f3d0; }
</style>

<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-xl font-bold text-slate-800">Import & Export</h1>
        <p class="text-sm text-slate-400 mt-0.5">Bulk import data into the system or export reports</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <!-- Left: Import & Export panels -->
    <div class="lg:col-span-2 space-y-5">
        <!-- Import panel -->
        <div class="card">
            <div class="card-header">
                <h3 class="section-title"><i class="fa-solid fa-file-import mr-2" style="color:var(--green-mid)"></i>Import Data</h3>
                <a href="#" id="templateLink" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-file-csv text-green-600"></i> Download Template
                </a>
            </div>
            <div class="card-body">
                <!-- Tabs -->
                <div class="flex gap-2 mb-5 border-b border-slate-200 pb-3 overflow-x-auto">
                    <button type="button" onclick="switchImportTab('members')" class="import-tab active" data-tab="members">Members</button>
                    <button type="button" onclick="switchImportTab('transactions')" class="import-tab" data-tab="transactions">Transactions</button>
                    <button type="button" onclick="switchImportTab('expenses')" class="import-tab" data-tab="expenses">Expenses</button>
                    <button type="button" onclick="switchImportTab('loan_products')" class="import-tab" data-tab="loan_products">Loan Products</button>
                </div>

                <form id="importForm" action="<?= APP_URL ?>/import/members" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Security::generateCsrfToken() ?>">
                    
                    <!-- Dynamic Instructions -->
                    <div id="importInstructions" class="p-4 rounded-xl mb-5" style="background:#eff6ff;border:1px solid #bfdbfe">
                        <!-- Populated by JS -->
                    </div>

                    <!-- Drop Zone -->
                    <div class="drop-zone" id="dropZone" onclick="document.getElementById('csvFile').click()">
                        <i class="fa-solid fa-cloud-arrow-up text-4xl text-slate-300 block mb-3"></i>
                        <p class="font-semibold text-slate-600 mb-1">Click to choose file or drag & drop</p>
                        <p class="text-sm text-slate-400">CSV files only · Max 5MB</p>
                        <p id="fileName" class="text-sm font-semibold mt-3 hidden" style="color:var(--green-mid)"></p>
                    </div>
                    <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" class="hidden" onchange="onFileSelect(this)">
                    
                    <!-- Progress -->
                    <div id="progressArea" class="mt-4 hidden">
                        <div class="flex justify-between text-xs text-slate-500 mb-1.5">
                            <span>Importing…</span><span id="progressPct">0%</span>
                        </div>
                        <div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
                    </div>
                    
                    <!-- Results -->
                    <div id="importResult" class="mt-4 hidden"></div>
                    
                    <!-- Submit -->
                    <div class="flex gap-3 justify-end mt-5">
                        <button type="button" id="importBtn" class="btn btn-primary" disabled onclick="doImport()">
                            <i class="fa-solid fa-file-import"></i> Import <span id="importTypeLabel">Members</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Export panel -->
        <div class="card">
            <div class="card-header">
                <h3 class="section-title"><i class="fa-solid fa-file-export mr-2" style="color:var(--green-mid)"></i>Export Data</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php
                    $exports = [
                        ['Members',      '/members/export',             'fa-users',               'bg-emerald-50 text-emerald-700 border-emerald-200'],
                        ['Savings',      '/reports/savings?export=1',   'fa-piggy-bank',          'bg-blue-50 text-blue-700 border-blue-200'],
                        ['Loans',        '/reports/loans?export=1',     'fa-file-contract',       'bg-purple-50 text-purple-700 border-purple-200'],
                        ['Transactions', '/reports/transactions?export=1','fa-arrow-right-arrow-left','bg-amber-50 text-amber-700 border-amber-200'],
                    ];
                    foreach ($exports as [$label, $url, $icon, $cls]):
                    ?>
                    <a href="<?= APP_URL . $url ?>" class="flex items-center gap-3 p-4 rounded-xl border <?= $cls ?> hover:shadow-sm transition-all font-semibold text-sm">
                        <i class="fa-solid <?= $icon ?> text-xl"></i>
                        <div>
                            <div><?= $label ?></div>
                            <div class="text-xs font-normal opacity-70">Download as CSV</div>
                        </div>
                        <i class="fa-solid fa-download ml-auto text-sm opacity-60"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Recent imports log & Tips -->
    <div class="space-y-5">
        <div class="card">
            <div class="card-header"><h3 class="section-title">Recent Imports</h3></div>
            <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                <?php if (empty($recentImports)): ?>
                    <div class="px-5 py-10 text-center">
                        <i class="fa-regular fa-clock text-3xl text-slate-200 block mb-2"></i>
                        <p class="text-slate-400 text-sm">No imports yet</p>
                    </div>
                <?php else: foreach ($recentImports as $log): ?>
                    <div class="px-4 py-3">
                        <div class="text-xs font-semibold text-slate-700 capitalize"><?= htmlspecialchars(str_replace('_', ' ', $log['action'] ?? '')) ?></div>
                        <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars(Format::truncate($log['description'] ?? '', 50)) ?></div>
                        <div class="text-[10px] text-slate-300 mt-0.5">
                            <?= Format::timeAgo($log['created_at'] ?? date('Y-m-d H:i:s')) ?> by <?= htmlspecialchars($log['user_name'] ?? 'System') ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3 class="section-title">Tips</h3></div>
            <div class="card-body">
                <ul class="space-y-2 text-sm text-slate-600">
                    <li class="flex items-start gap-2"><i class="fa-solid fa-lightbulb text-amber-400 mt-0.5 flex-shrink-0"></i> Ensure your CSV uses UTF-8 encoding to support local characters.</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-lightbulb text-amber-400 mt-0.5 flex-shrink-0"></i> Remove any empty rows at the bottom of your spreadsheet before saving as CSV.</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-lightbulb text-amber-400 mt-0.5 flex-shrink-0"></i> Test with a small batch first (10–20 rows) before importing thousands.</li>
                    <li class="flex items-start gap-2"><i class="fa-solid fa-lightbulb text-amber-400 mt-0.5 flex-shrink-0"></i> Phone numbers in international format (+256...) work best.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Configuration for each import type
const importConfig = {
    members: {
        label: 'Members',
        template: '<?= APP_URL ?>/import/template/members',
        instructions: `
            <h4 class="font-semibold text-blue-800 text-sm mb-2"><i class="fa-solid fa-circle-info mr-1"></i> Before you import Members</h4>
            <ul class="text-sm text-blue-700 space-y-1 list-none">
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Required columns: <code class="bg-blue-100 px-1 rounded">first_name, last_name, phone, membership_date</code></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Date format: <code class="bg-blue-100 px-1 rounded">YYYY-MM-DD</code> (e.g. 2024-01-15)</li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Duplicate phones and National IDs are automatically skipped</li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> A default savings account will be auto-created for each member</li>
            </ul>
        `
    },
    transactions: {
        label: 'Transactions',
        template: '<?= APP_URL ?>/import/template/transactions',
        instructions: `
            <h4 class="font-semibold text-blue-800 text-sm mb-2"><i class="fa-solid fa-circle-info mr-1"></i> Before you import Transactions</h4>
            <ul class="text-sm text-blue-700 space-y-1 list-none">
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Required columns: <code class="bg-blue-100 px-1 rounded">member_no, txn_type, amount, payment_method, transaction_date</code></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Allowed <code class="bg-blue-100 px-1 rounded">txn_type</code>: <code>deposit</code>, <code>withdrawal</code>, <code>loan_repayment</code></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> For <code>loan_repayment</code>, include the <code class="bg-blue-100 px-1 rounded">loan_no</code> column</li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Member must exist and have an active savings account</li>
            </ul>
        `
    },
    expenses: {
        label: 'Expenses',
        template: '<?= APP_URL ?>/import/template/expenses',
        instructions: `
            <h4 class="font-semibold text-blue-800 text-sm mb-2"><i class="fa-solid fa-circle-info mr-1"></i> Before you import Expenses</h4>
            <ul class="text-sm text-blue-700 space-y-1 list-none">
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Required columns: <code class="bg-blue-100 px-1 rounded">category_name, title, amount, expense_date</code></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> The <code class="bg-blue-100 px-1 rounded">category_name</code> must exactly match an existing category</li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Date format: <code class="bg-blue-100 px-1 rounded">YYYY-MM-DD</code></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Imported expenses will be marked as 'approved' automatically</li>
            </ul>
        `
    },
    loan_products: {
        label: 'Loan Products',
        template: '<?= APP_URL ?>/import/template/loan_products',
        instructions: `
            <h4 class="font-semibold text-blue-800 text-sm mb-2"><i class="fa-solid fa-circle-info mr-1"></i> Before you import Loan Products</h4>
            <ul class="text-sm text-blue-700 space-y-1 list-none">
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Required columns: <code class="bg-blue-100 px-1 rounded">name, min_amount, max_amount, interest_rate, interest_type, min_term_months, max_term_months</code></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Allowed <code class="bg-blue-100 px-1 rounded">interest_type</code>: <code>flat</code>, <code>reducing_balance</code>, <code>compound</code></li>
                <li class="flex items-start gap-2"><i class="fa-solid fa-check text-blue-500 mt-0.5 flex-shrink-0"></i> Products with duplicate names will be skipped</li>
            </ul>
        `
    }
};

let currentImportType = 'members';

function switchImportTab(type) {
    currentImportType = type;
    const config = importConfig[type];
    
    // Update tabs UI
    document.querySelectorAll('.import-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.tab === type);
    });
    
    // Update form action
    document.getElementById('importForm').action = '<?= APP_URL ?>/import/' + type;
    
    // Update template link
    document.getElementById('templateLink').href = config.template;
    
    // Update instructions
    document.getElementById('importInstructions').innerHTML = config.instructions;
    
    // Update button label
    document.getElementById('importTypeLabel').textContent = config.label;
    
    // Reset file input and results
    document.getElementById('csvFile').value = '';
    document.getElementById('fileName').classList.add('hidden');
    document.getElementById('importBtn').disabled = true;
    document.getElementById('importResult').classList.add('hidden');
    document.getElementById('progressArea').classList.add('hidden');
}

// Initialize first tab
switchImportTab('members');

function onFileSelect(input) {
    const label = document.getElementById('fileName');
    const btn   = document.getElementById('importBtn');
    if (input.files.length) {
        label.textContent = '✓ ' + input.files[0].name;
        label.classList.remove('hidden');
        btn.disabled = false;
    } else {
        label.classList.add('hidden');
        btn.disabled = true;
    }
}

const dropZone = document.getElementById('dropZone');
if (dropZone) {
    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) {
            const input = document.getElementById('csvFile');
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            onFileSelect(input);
        }
    });
}

async function doImport() {
    const form     = document.getElementById('importForm');
    const progress = document.getElementById('progressArea');
    const fill     = document.getElementById('progressFill');
    const pct      = document.getElementById('progressPct');
    const result   = document.getElementById('importResult');
    const btn      = document.getElementById('importBtn');
    
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin w-4 h-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Importing…';
    progress.classList.remove('hidden');
    result.classList.add('hidden');
    
    let p = 0;
    const timer = setInterval(() => {
        p = Math.min(p + 8, 85);
        fill.style.width = p + '%';
        pct.textContent  = p + '%';
    }, 200);
    
    try {
        const fd = new FormData(form);
        const r  = await fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        });
        const d = await r.json();
        clearInterval(timer);
        fill.style.width = '100%';
        pct.textContent  = '100%';
        
        setTimeout(() => {
            progress.classList.add('hidden');
            result.classList.remove('hidden');
            if (d.success) {
                let html = `<div class="p-4 rounded-xl" style="background:#f0fdf9;border:1px solid #a7f3d0">
                    <div class="font-bold text-emerald-800 mb-2"><i class="fa-solid fa-circle-check mr-1"></i>${d.message}</div>`;
                if (d.data?.inserted !== undefined) {
                    html += `<div class="text-sm text-emerald-700">✓ ${d.data.inserted} inserted &nbsp;&nbsp; ${d.data.skipped} skipped</div>`;
                }
                if (d.data?.errors?.length) {
                    html += `<div class="mt-3 text-xs text-amber-700 font-semibold">Skipped rows:</div><ul class="mt-1 text-xs text-amber-600 space-y-0.5 max-h-32 overflow-y-auto">`;
                    d.data.errors.forEach(err => { html += `<li>• ${err}</li>`; });
                    html += `</ul>`;
                }
                html += `</div>`;
                result.innerHTML = html;
                
                // Show toast
                if (window.showToast) window.showToast('success', d.message);
            } else {
                result.innerHTML = `<div class="p-4 rounded-xl" style="background:#fef2f2;border:1px solid #fecaca">
                    <div class="font-bold text-red-700"><i class="fa-solid fa-circle-xmark mr-1"></i>${d.message}</div></div>`;
                if (window.showToast) window.showToast('error', d.message);
            }
            btn.disabled = false;
            btn.innerHTML = `<i class="fa-solid fa-file-import"></i> Import ${importConfig[currentImportType].label}`;
        }, 400);
    } catch(e) {
        clearInterval(timer);
        progress.classList.add('hidden');
        result.innerHTML = `<div class="p-4 rounded-xl" style="background:#fef2f2;border:1px solid #fecaca">
            <div class="font-bold text-red-700">Network error. Please try again.</div></div>`;
        result.classList.remove('hidden');
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-file-import"></i> Import ${importConfig[currentImportType].label}`;
        if (window.showToast) window.showToast('error', 'Network error.');
    }
}
</script>