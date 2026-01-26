@extends('layouts.app')

@section('content')
<div class="container-xl">
  <!-- Page header -->
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          <i class="ti ti-chart-bar me-2"></i>Reports & Analytics
        </h2>
        <div class="text-muted mt-1">Inventory insights and analysis tools</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
          <button class="btn btn-icon" onclick="refreshAllReports()">
            <i class="ti ti-refresh"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Report Navigation -->
  <div class="page-body">
    <div class="row row-deck row-cards">
      <!-- Report Selector Cards -->
      <div class="col-12">
        <div class="card">
          <div class="card-body">
            <div class="row g-2">
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-primary w-100" onclick="showReport('lowStock')">
                  <i class="ti ti-alert-triangle me-1"></i>
                  Low Stock
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-info w-100" onclick="showReport('committed')">
                  <i class="ti ti-lock me-1"></i>
                  Committed
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-success w-100" onclick="showReport('velocity')">
                  <i class="ti ti-trending-up me-1"></i>
                  Velocity
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-warning w-100" onclick="showReport('reorder')">
                  <i class="ti ti-shopping-cart me-1"></i>
                  Reorder
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-danger w-100" onclick="showReport('obsolete')">
                  <i class="ti ti-archive me-1"></i>
                  Obsolete
                </button>
              </div>
              <div class="col-6 col-md-4 col-lg-2">
                <button class="btn btn-outline-secondary w-100" onclick="showReport('usage')">
                  <i class="ti ti-activity me-1"></i>
                  Usage
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Low Stock Report -->
      <div class="col-12" id="lowStockReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-alert-triangle me-2"></i>Low Stock & Critical Items</h3>
            <div class="ms-auto">
              <button class="btn btn-sm btn-primary" onclick="exportReport('low_stock')">
                <i class="ti ti-download me-1"></i>Export
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Low Stock Items</div>
                    <div class="h2 mb-0" id="lowStockCount">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Critical Items</div>
                    <div class="h2 mb-0 text-danger" id="criticalCount">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Affected</div>
                    <div class="h2 mb-0" id="totalAffected">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Value at Risk</div>
                    <div class="h2 mb-0" id="valueAtRisk">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="lowStockLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="lowStockContent" style="display: none;">
              <div class="table-responsive">
                <table class="table table-vcenter">
                  <thead>
                    <tr>
                      <th>SKU</th>
                      <th>Description</th>
                      <th>Category</th>
                      <th class="text-end">On Hand</th>
                      <th class="text-end">Available</th>
                      <th class="text-end">Minimum</th>
                      <th>Status</th>
                      <th class="text-end">Value</th>
                    </tr>
                  </thead>
                  <tbody id="lowStockTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="lowStockPagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Committed Parts Report -->
      <div class="col-12" id="committedReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-lock me-2"></i>Committed Parts</h3>
            <div class="ms-auto">
              <button class="btn btn-sm btn-primary" onclick="exportReport('committed')">
                <i class="ti ti-download me-1"></i>Export
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Products with Commitments</div>
                    <div class="h2 mb-0" id="committedProductsCount">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Qty Committed</div>
                    <div class="h2 mb-0" id="totalQtyCommitted">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Value Committed</div>
                    <div class="h2 mb-0" id="valueCommitted">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="committedLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="committedContent" style="display: none;">
              <div class="table-responsive">
                <table class="table table-vcenter">
                  <thead>
                    <tr>
                      <th>SKU</th>
                      <th>Description</th>
                      <th>Category</th>
                      <th class="text-end">Committed</th>
                      <th class="text-end">Available</th>
                      <th>Reservations</th>
                    </tr>
                  </thead>
                  <tbody id="committedTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="committedPagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Velocity Analysis Report -->
      <div class="col-12" id="velocityReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-trending-up me-2"></i>Stock Velocity Analysis</h3>
            <div class="ms-auto">
              <select class="form-select form-select-sm" id="velocityDays" onchange="loadVelocityReport()">
                <option value="30">Last 30 Days</option>
                <option value="60">Last 60 Days</option>
                <option value="90" selected>Last 90 Days</option>
                <option value="180">Last 180 Days</option>
              </select>
              <button class="btn btn-sm btn-primary ms-2" onclick="exportReport('velocity')">
                <i class="ti ti-download me-1"></i>Export
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Fast Movers</div>
                    <div class="h2 mb-0 text-success" id="fastMovers">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Medium Movers</div>
                    <div class="h2 mb-0 text-info" id="mediumMovers">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Slow Movers</div>
                    <div class="h2 mb-0 text-warning" id="slowMovers">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Analyzed</div>
                    <div class="h2 mb-0" id="totalAnalyzed">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="velocityLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="velocityContent" style="display: none;">
              <div class="table-responsive">
                <table class="table table-vcenter">
                  <thead>
                    <tr>
                      <th>SKU</th>
                      <th>Description</th>
                      <th>Category</th>
                      <th class="text-end">On Hand</th>
                      <th class="text-end">Receipts</th>
                      <th class="text-end">Shipments</th>
                      <th class="text-end">Turnover %</th>
                      <th>Velocity</th>
                      <th class="text-end">Days to Stockout</th>
                    </tr>
                  </thead>
                  <tbody id="velocityTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="velocityPagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Reorder Recommendations Report -->
      <div class="col-12" id="reorderReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-shopping-cart me-2"></i>Reorder Recommendations</h3>
            <div class="ms-auto">
              <button class="btn btn-sm btn-primary" onclick="exportReport('reorder')">
                <i class="ti ti-download me-1"></i>Export
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Items to Reorder</div>
                    <div class="h2 mb-0" id="itemsToReorder">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Critical Items</div>
                    <div class="h2 mb-0 text-danger" id="criticalReorderItems">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Order Value</div>
                    <div class="h2 mb-0" id="totalOrderValue">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="reorderLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="reorderContent" style="display: none;">
              <div class="table-responsive">
                <table class="table table-vcenter">
                  <thead>
                    <tr>
                      <th>SKU</th>
                      <th>Description</th>
                      <th>Supplier</th>
                      <th class="text-end">Available</th>
                      <th class="text-end">Reorder Point</th>
                      <th class="text-end">Shortage</th>
                      <th class="text-end">Recommended Qty</th>
                      <th class="text-end">Est. Cost</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody id="reorderTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="reorderPagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Obsolete Inventory Report -->
      <div class="col-12" id="obsoleteReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-archive me-2"></i>Obsolete Inventory</h3>
            <div class="ms-auto">
              <select class="form-select form-select-sm" id="obsoleteDays" onchange="loadObsoleteReport()">
                <option value="90">90 Days</option>
                <option value="180" selected>180 Days</option>
                <option value="365">365 Days</option>
              </select>
              <button class="btn btn-sm btn-primary ms-2" onclick="exportReport('obsolete')">
                <i class="ti ti-download me-1"></i>Export
              </button>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Obsolete Candidates</div>
                    <div class="h2 mb-0" id="obsoleteItems">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Used in BOM</div>
                    <div class="h2 mb-0" id="usedInBom">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Value at Risk</div>
                    <div class="h2 mb-0" id="obsoleteValue">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="obsoleteLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="obsoleteContent" style="display: none;">
              <div class="table-responsive">
                <table class="table table-vcenter">
                  <thead>
                    <tr>
                      <th>SKU</th>
                      <th>Description</th>
                      <th>Category</th>
                      <th class="text-end">On Hand</th>
                      <th class="text-end">Unit Cost</th>
                      <th class="text-end">Total Value</th>
                      <th>Last Used</th>
                      <th class="text-end">Days Inactive</th>
                      <th>In BOM</th>
                    </tr>
                  </thead>
                  <tbody id="obsoleteTableBody"></tbody>
                </table>
              </div>
              <div class="card-footer d-flex align-items-center" id="obsoletePagination" style="display: none;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Usage Analytics Report -->
      <div class="col-12" id="usageReport" style="display: none;">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title"><i class="ti ti-activity me-2"></i>Usage Analytics</h3>
            <div class="ms-auto">
              <select class="form-select form-select-sm" id="usageDays" onchange="loadUsageReport()">
                <option value="7">Last 7 Days</option>
                <option value="30" selected>Last 30 Days</option>
                <option value="60">Last 60 Days</option>
                <option value="90">Last 90 Days</option>
              </select>
            </div>
          </div>
          <div class="card-body">
            <div class="row mb-3">
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Receipts</div>
                    <div class="h2 mb-0 text-success" id="totalReceipts">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Total Shipments</div>
                    <div class="h2 mb-0 text-danger" id="totalShipments">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Adjustments</div>
                    <div class="h2 mb-0" id="totalAdjustments">-</div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card card-sm">
                  <div class="card-body">
                    <div class="text-muted">Period</div>
                    <div class="h2 mb-0" id="periodDays">-</div>
                  </div>
                </div>
              </div>
            </div>

            <div id="usageLoading" class="text-center py-4">
              <div class="spinner-border" role="status"></div>
              <div class="text-muted mt-2">Loading report...</div>
            </div>

            <div id="usageContent" style="display: none;">
              <div class="row">
                <div class="col-md-6">
                  <h4>Activity by Date</h4>
                  <div class="table-responsive">
                    <table class="table table-sm">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th class="text-end">Receipts</th>
                          <th class="text-end">Shipments</th>
                          <th class="text-end">Transactions</th>
                        </tr>
                      </thead>
                      <tbody id="usageByDateBody"></tbody>
                    </table>
                  </div>
                </div>
                <div class="col-md-6">
                  <h4>Activity by Category</h4>
                  <div class="table-responsive">
                    <table class="table table-sm">
                      <thead>
                        <tr>
                          <th>Category</th>
                          <th class="text-end">Receipts</th>
                          <th class="text-end">Shipments</th>
                          <th class="text-end">Transactions</th>
                        </tr>
                      </thead>
                      <tbody id="usageByCategoryBody"></tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let currentReport = null;

// Show specific report
function showReport(reportType) {
  // Hide all reports
  document.querySelectorAll('[id$="Report"]').forEach(el => el.style.display = 'none');

  // Show selected report
  const reportMap = {
    'lowStock': 'lowStockReport',
    'committed': 'committedReport',
    'velocity': 'velocityReport',
    'reorder': 'reorderReport',
    'obsolete': 'obsoleteReport',
    'usage': 'usageReport'
  };

  const reportId = reportMap[reportType];
  if (reportId) {
    document.getElementById(reportId).style.display = 'block';
    currentReport = reportType;

    // Load the report data
    switch(reportType) {
      case 'lowStock':
        loadLowStockReport();
        break;
      case 'committed':
        loadCommittedReport();
        break;
      case 'velocity':
        loadVelocityReport();
        break;
      case 'reorder':
        loadReorderReport();
        break;
      case 'obsolete':
        loadObsoleteReport();
        break;
      case 'usage':
        loadUsageReport();
        break;
    }
  }
}

// Low Stock Report
async function loadLowStockReport(page = 1) {
  try {
    document.getElementById('lowStockLoading').style.display = 'block';
    document.getElementById('lowStockContent').style.display = 'none';

    const response = await authenticatedFetch('/reports/low-stock');

    // Update summary cards
    document.getElementById('lowStockCount').textContent = response.summary.low_stock_count;
    document.getElementById('criticalCount').textContent = response.summary.critical_count;
    document.getElementById('totalAffected').textContent = response.summary.total_affected;
    document.getElementById('valueAtRisk').textContent = formatCurrency(response.summary.estimated_value_at_risk);

    // Store full data for pagination
    const allItems = [...response.low_stock, ...response.critical];
    reportPaginationState.lowStock = allItems;

    // Render paginated table
    renderLowStockTable(page);

    document.getElementById('lowStockLoading').style.display = 'none';
    document.getElementById('lowStockContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading low stock report:', error);
    showNotification('Error loading low stock report', 'danger');
  }
}

function renderLowStockTable(page = 1) {
  const allItems = reportPaginationState.lowStock || [];
  const pagination = paginateData(allItems, page);
  const tbody = document.getElementById('lowStockTableBody');

  if (allItems.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No low stock items</td></tr>';
    document.getElementById('lowStockPagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => `
      <tr>
        <td><strong>${escapeHtml(item.sku)}</strong></td>
        <td>${escapeHtml(item.description)}</td>
        <td>${item.category || '-'}</td>
        <td class="text-end">${item.on_hand}</td>
        <td class="text-end">${item.available}</td>
        <td class="text-end">${item.minimum}</td>
        <td>${getStatusBadge(item.status)}</td>
        <td class="text-end">${formatCurrency(item.total_value)}</td>
      </tr>
    `).join('');

    renderReportPagination('lowStockPagination', pagination, renderLowStockTable);
  }
}

// Committed Parts Report
async function loadCommittedReport(page = 1) {
  try {
    document.getElementById('committedLoading').style.display = 'block';
    document.getElementById('committedContent').style.display = 'none';

    const response = await authenticatedFetch('/reports/committed-parts');

    // Update summary cards
    document.getElementById('committedProductsCount').textContent = response.summary.total_products;
    document.getElementById('totalQtyCommitted').textContent = response.summary.total_quantity_committed;
    document.getElementById('valueCommitted').textContent = formatCurrency(response.summary.total_value_committed);

    // Store full data for pagination
    reportPaginationState.committed = response.committed_products;

    // Render paginated table
    renderCommittedTable(page);

    document.getElementById('committedLoading').style.display = 'none';
    document.getElementById('committedContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading committed report:', error);
    showNotification('Error loading committed report', 'danger');
  }
}

function renderCommittedTable(page = 1) {
  const allItems = reportPaginationState.committed || [];
  const pagination = paginateData(allItems, page);
  const tbody = document.getElementById('committedTableBody');

  if (allItems.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No committed parts</td></tr>';
    document.getElementById('committedPagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => `
      <tr>
        <td><strong>${escapeHtml(item.sku)}</strong></td>
        <td>${escapeHtml(item.description)}</td>
        <td>${item.category || '-'}</td>
        <td class="text-end">${item.committed}</td>
        <td class="text-end">${item.available}</td>
        <td>
          ${item.reservations.map(r => {
            const statusBadge = {
              'active': 'text-bg-info',
              'in_progress': 'text-bg-primary',
              'on_hold': 'text-bg-warning'
            }[r.status] || 'text-bg-secondary';
            return `<span class="badge ${statusBadge} me-1" title="${r.job_name || ''}">${r.job_number}-${r.release_number || 1}: ${r.quantity}</span>`;
          }).join('')}
        </td>
      </tr>
    `).join('');

    renderReportPagination('committedPagination', pagination, renderCommittedTable);
  }
}

// Velocity Analysis Report
async function loadVelocityReport(page = 1) {
  try {
    const days = document.getElementById('velocityDays').value;
    document.getElementById('velocityLoading').style.display = 'block';
    document.getElementById('velocityContent').style.display = 'none';

    const response = await authenticatedFetch(`/reports/velocity?days=${days}`);

    // Update summary cards
    document.getElementById('fastMovers').textContent = response.summary.fast_movers;
    document.getElementById('mediumMovers').textContent = response.summary.medium_movers;
    document.getElementById('slowMovers').textContent = response.summary.slow_movers;
    document.getElementById('totalAnalyzed').textContent = response.summary.total_analyzed;

    // Store full data for pagination
    reportPaginationState.velocity = response.products;

    // Render paginated table
    renderVelocityTable(page);

    document.getElementById('velocityLoading').style.display = 'none';
    document.getElementById('velocityContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading velocity report:', error);
    showNotification('Error loading velocity report', 'danger');
  }
}

function renderVelocityTable(page = 1) {
  const allItems = reportPaginationState.velocity || [];
  const pagination = paginateData(allItems, page);
  const tbody = document.getElementById('velocityTableBody');

  if (allItems.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No data</td></tr>';
    document.getElementById('velocityPagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => {
      const velocityBadge = {
        'fast': '<span class="badge text-bg-success">Fast</span>',
        'medium': '<span class="badge text-bg-info">Medium</span>',
        'slow': '<span class="badge text-bg-warning">Slow</span>'
      }[item.velocity];

      return `
        <tr>
          <td><strong>${escapeHtml(item.sku)}</strong></td>
          <td>${escapeHtml(item.description)}</td>
          <td>${item.category || '-'}</td>
          <td class="text-end">${item.on_hand}</td>
          <td class="text-end">${item.receipts}</td>
          <td class="text-end">${item.shipments}</td>
          <td class="text-end">${item.turnover_rate}%</td>
          <td>${velocityBadge}</td>
          <td class="text-end">${item.days_until_stockout || '-'}</td>
        </tr>
      `;
    }).join('');

    renderReportPagination('velocityPagination', pagination, renderVelocityTable);
  }
}

// Reorder Recommendations Report
async function loadReorderReport(page = 1) {
  try {
    document.getElementById('reorderLoading').style.display = 'block';
    document.getElementById('reorderContent').style.display = 'none';

    const response = await authenticatedFetch('/reports/reorder-recommendations');

    // Update summary cards
    document.getElementById('itemsToReorder').textContent = response.summary.items_to_reorder;
    document.getElementById('criticalReorderItems').textContent = response.summary.critical_items;
    document.getElementById('totalOrderValue').textContent = formatCurrency(response.summary.total_order_value);

    // Store full data for pagination
    reportPaginationState.reorder = response.recommendations;

    // Render paginated table
    renderReorderTable(page);

    document.getElementById('reorderLoading').style.display = 'none';
    document.getElementById('reorderContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading reorder report:', error);
    showNotification('Error loading reorder report', 'danger');
  }
}

function renderReorderTable(page = 1) {
  const allItems = reportPaginationState.reorder || [];
  const pagination = paginateData(allItems, page);
  const tbody = document.getElementById('reorderTableBody');

  if (allItems.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No reorder recommendations</td></tr>';
    document.getElementById('reorderPagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => `
      <tr>
        <td><strong>${escapeHtml(item.sku)}</strong></td>
        <td>${escapeHtml(item.description)}</td>
        <td>${item.supplier || '-'}</td>
        <td class="text-end">${item.available}</td>
        <td class="text-end">${item.reorder_point}</td>
        <td class="text-end text-danger">${item.shortage}</td>
        <td class="text-end"><strong>${item.recommended_order_qty}</strong></td>
        <td class="text-end">${formatCurrency(item.recommended_order_value)}</td>
        <td>${getStatusBadge(item.status)}</td>
      </tr>
    `).join('');

    renderReportPagination('reorderPagination', pagination, renderReorderTable);
  }
}

// Obsolete Inventory Report
async function loadObsoleteReport(page = 1) {
  try {
    const days = document.getElementById('obsoleteDays').value;
    document.getElementById('obsoleteLoading').style.display = 'block';
    document.getElementById('obsoleteContent').style.display = 'none';

    const response = await authenticatedFetch(`/reports/obsolete?inactive_days=${days}`);

    // Update summary cards
    document.getElementById('obsoleteItems').textContent = response.summary.total_items;
    document.getElementById('usedInBom').textContent = response.summary.used_in_bom;
    document.getElementById('obsoleteValue').textContent = formatCurrency(response.summary.total_value_at_risk);

    // Store full data for pagination
    reportPaginationState.obsolete = response.obsolete_candidates;

    // Render paginated table
    renderObsoleteTable(page);

    document.getElementById('obsoleteLoading').style.display = 'none';
    document.getElementById('obsoleteContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading obsolete report:', error);
    showNotification('Error loading obsolete report', 'danger');
  }
}

function renderObsoleteTable(page = 1) {
  const allItems = reportPaginationState.obsolete || [];
  const pagination = paginateData(allItems, page);
  const tbody = document.getElementById('obsoleteTableBody');

  if (allItems.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No obsolete items found</td></tr>';
    document.getElementById('obsoletePagination').style.display = 'none';
  } else {
    tbody.innerHTML = pagination.data.map(item => `
      <tr>
        <td><strong>${escapeHtml(item.sku)}</strong></td>
        <td>${escapeHtml(item.description)}</td>
        <td>${item.category || '-'}</td>
        <td class="text-end">${item.on_hand}</td>
        <td class="text-end">${formatCurrency(item.unit_cost)}</td>
        <td class="text-end">${formatCurrency(item.total_value)}</td>
        <td>${item.last_shipment_date || 'Never'}</td>
        <td class="text-end">${item.days_since_last_use}</td>
        <td>${item.is_used_in_bom ? '<span class="badge text-bg-info">Yes</span>' : '-'}</td>
      </tr>
    `).join('');

    renderReportPagination('obsoletePagination', pagination, renderObsoleteTable);
  }
}

// Usage Analytics Report
async function loadUsageReport() {
  try {
    const days = document.getElementById('usageDays').value;
    document.getElementById('usageLoading').style.display = 'block';
    document.getElementById('usageContent').style.display = 'none';

    const response = await authenticatedFetch(`/reports/usage-analytics?days=${days}`);

    // Update summary cards
    document.getElementById('totalReceipts').textContent = response.summary.total_receipts;
    document.getElementById('totalShipments').textContent = response.summary.total_shipments;
    document.getElementById('totalAdjustments').textContent = response.summary.total_adjustments;
    document.getElementById('periodDays').textContent = response.summary.period_days + ' days';

    // Render by date table
    const dateBody = document.getElementById('usageByDateBody');
    if (response.by_date.length === 0) {
      dateBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data</td></tr>';
    } else {
      dateBody.innerHTML = response.by_date.map(item => `
        <tr>
          <td>${item.date}</td>
          <td class="text-end text-success">${item.receipts}</td>
          <td class="text-end text-danger">${item.shipments}</td>
          <td class="text-end">${item.total_transactions}</td>
        </tr>
      `).join('');
    }

    // Render by category table
    const categoryBody = document.getElementById('usageByCategoryBody');
    if (response.by_category.length === 0) {
      categoryBody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No data</td></tr>';
    } else {
      categoryBody.innerHTML = response.by_category.map(item => `
        <tr>
          <td>${item.category}</td>
          <td class="text-end text-success">${item.receipts}</td>
          <td class="text-end text-danger">${item.shipments}</td>
          <td class="text-end">${item.transaction_count}</td>
        </tr>
      `).join('');
    }

    document.getElementById('usageLoading').style.display = 'none';
    document.getElementById('usageContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading usage report:', error);
    showNotification('Error loading usage report', 'danger');
  }
}

// Export report
async function exportReport(type) {
  try {
    const response = await fetch(`${API_BASE}/reports/export?type=${type}`, {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${authToken}`,
        'Accept': 'text/csv'
      }
    });

    if (!response.ok) {
      throw new Error('Export failed');
    }

    // Get the blob from response
    const blob = await response.blob();

    // Create download link
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${type}_report_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);

    showNotification('Report exported successfully', 'success');
  } catch (error) {
    console.error('Error exporting report:', error);
    showNotification('Error exporting report', 'danger');
  }
}

// Refresh all visible reports
function refreshAllReports() {
  if (currentReport) {
    showReport(currentReport);
  }
}

// ============================================
// Client-Side Pagination Helper
// ============================================
const ITEMS_PER_PAGE = 50;
const reportPaginationState = {};

function paginateData(data, page, perPage = ITEMS_PER_PAGE) {
  const startIndex = (page - 1) * perPage;
  const endIndex = startIndex + perPage;
  return {
    data: data.slice(startIndex, endIndex),
    currentPage: page,
    lastPage: Math.ceil(data.length / perPage),
    total: data.length,
    from: data.length > 0 ? startIndex + 1 : 0,
    to: Math.min(endIndex, data.length)
  };
}

function renderReportPagination(containerId, pagination, onPageChange) {
  const container = document.getElementById(containerId);
  if (!container || !pagination || pagination.total === 0) {
    if (container) container.style.display = 'none';
    return;
  }

  const { currentPage, lastPage, total, from, to } = pagination;

  if (lastPage <= 1) {
    container.style.display = 'none';
    return;
  }

  // Build page numbers - show max 7 pages
  const pageNumbers = getReportPageNumbers(currentPage, lastPage, 7);

  let html = `
    <p class="m-0 text-muted">Showing ${from} to ${to} of ${total.toLocaleString()} items</p>
    <ul class="pagination m-0 ms-auto">
      <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage - 1}" tabindex="-1" ${currentPage === 1 ? 'aria-disabled="true"' : ''}>
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
            <path d="M15 6l-6 6l6 6"></path>
          </svg>
        </a>
      </li>
  `;

  pageNumbers.forEach(pageNum => {
    if (pageNum === '...') {
      html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
    } else {
      html += `
        <li class="page-item ${pageNum === currentPage ? 'active' : ''}">
          <a class="page-link" href="#" data-page="${pageNum}">${pageNum}</a>
        </li>
      `;
    }
  });

  html += `
      <li class="page-item ${currentPage === lastPage ? 'disabled' : ''}">
        <a class="page-link" href="#" data-page="${currentPage + 1}" ${currentPage === lastPage ? 'aria-disabled="true"' : ''}>
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-1">
            <path d="M9 6l6 6l-6 6"></path>
          </svg>
        </a>
      </li>
    </ul>
  `;

  container.innerHTML = html;
  container.style.display = 'flex';

  // Add click handlers
  container.querySelectorAll('.page-link[data-page]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const page = parseInt(link.dataset.page);
      if (page >= 1 && page <= lastPage && page !== currentPage) {
        onPageChange(page);
      }
    });
  });
}

function getReportPageNumbers(current, last, maxVisible) {
  if (last <= maxVisible) {
    return Array.from({length: last}, (_, i) => i + 1);
  }

  const pages = [];
  const half = Math.floor(maxVisible / 2);

  if (current <= half + 1) {
    for (let i = 1; i <= maxVisible - 2; i++) pages.push(i);
    pages.push('...');
    pages.push(last);
  } else if (current >= last - half) {
    pages.push(1);
    pages.push('...');
    for (let i = last - maxVisible + 3; i <= last; i++) pages.push(i);
  } else {
    pages.push(1);
    pages.push('...');
    for (let i = current - 1; i <= current + 1; i++) pages.push(i);
    pages.push('...');
    pages.push(last);
  }

  return pages;
}

// ============================================
// Helper functions
// ============================================
function getStatusBadge(status) {
  const badges = {
    'in_stock': '<span class="badge text-bg-success">In Stock</span>',
    'low_stock': '<span class="badge text-bg-warning">Low Stock</span>',
    'critical': '<span class="badge text-bg-danger">Critical</span>',
    'out_of_stock': '<span class="badge text-bg-dark">Out of Stock</span>'
  };
  return badges[status] || status;
}

function formatCurrency(value) {
  return '$' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Load first report on page load
document.addEventListener('DOMContentLoaded', () => {
  showReport('lowStock');
});
</script>
@endsection
