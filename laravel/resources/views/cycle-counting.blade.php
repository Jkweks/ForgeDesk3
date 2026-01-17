@extends('layouts.app')

@section('content')
<div class="container-xl">
  <!-- Page header -->
  <div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">
          <i class="ti ti-clipboard-check me-2"></i>Cycle Counting
        </h2>
        <div class="text-muted mt-1">Physical inventory counting and variance management</div>
      </div>
      <div class="col-auto ms-auto d-print-none">
        <div class="btn-list">
          <button class="btn btn-primary" onclick="showCreateSessionModal()">
            <i class="ti ti-plus me-1"></i>Create Count Session
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Statistics Cards -->
  <div class="page-body">
    <div class="row row-deck row-cards mb-3">
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Total Sessions</div>
            </div>
            <div class="h1 mb-0" id="statTotalSessions">-</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Active Sessions</div>
            </div>
            <div class="h1 mb-0 text-primary" id="statActiveSessions">-</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Items Counted (Month)</div>
            </div>
            <div class="h1 mb-0 text-success" id="statItemsCounted">-</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center">
              <div class="subheader">Accuracy (Month)</div>
            </div>
            <div class="h1 mb-0 text-info" id="statAccuracy">-</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="row mb-3">
      <div class="col-md-12">
        <div class="card">
          <div class="card-body">
            <div class="row g-2">
              <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" id="filterStatus" onchange="loadCycleCounts()">
                  <option value="">All Statuses</option>
                  <option value="planned">Planned</option>
                  <option value="in_progress">In Progress</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" id="filterLocation" placeholder="Location..." onkeyup="debounceSearch()">
              </div>
              <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" id="filterSearch" placeholder="Session number..." onkeyup="debounceSearch()">
              </div>
              <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-secondary w-100" onclick="clearFilters()">
                  <i class="ti ti-x me-1"></i>Clear Filters
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cycle Count Sessions Table -->
    <div class="row">
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Cycle Count Sessions</h3>
          </div>
          <div id="sessionLoading" class="card-body text-center py-5">
            <div class="spinner-border" role="status"></div>
            <div class="text-muted mt-2">Loading cycle count sessions...</div>
          </div>
          <div id="sessionContent" style="display: none;">
            <div class="table-responsive">
              <table class="table table-vcenter card-table">
                <thead>
                  <tr>
                    <th>Session #</th>
                    <th>Location</th>
                    <th>Scheduled Date</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Accuracy</th>
                    <th class="w-1">Actions</th>
                  </tr>
                </thead>
                <tbody id="sessionTableBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Create Session Modal -->
<div class="modal modal-blur fade" id="createSessionModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Cycle Count Session</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="sessionForm">
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">Location (Optional)</label>
              <input type="text" class="form-control" id="sessionLocation" placeholder="e.g., Warehouse A, Aisle 4-B">
              <small class="form-hint">Leave blank to count all locations</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Category (Optional)</label>
              <select class="form-select" id="sessionCategory">
                <option value="">All Categories</option>
              </select>
              <small class="form-hint">Filter products by category</small>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label required">Scheduled Date</label>
              <input type="date" class="form-control" id="sessionDate" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Assigned To</label>
              <select class="form-select" id="sessionAssignedTo">
                <option value="">Select user...</option>
              </select>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="sessionNotes" rows="2" placeholder="Optional notes about this count session..."></textarea>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label">Product Selection (Optional)</label>
              <select class="form-select" id="sessionProducts" multiple size="10">
              </select>
              <small class="form-hint">Hold Ctrl/Cmd to select multiple products. Leave empty to count all products matching filters.</small>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="createCycleCountSession()">Create Session</button>
      </div>
    </div>
  </div>
</div>

<!-- Count Entry Modal -->
<div class="modal modal-blur fade" id="countEntryModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Cycle Count - <span id="countSessionNumber"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="countSessionId">

        <!-- Session Info -->
        <div class="row mb-3">
          <div class="col-md-12">
            <div class="alert alert-info">
              <strong>Location:</strong> <span id="countLocation"></span> |
              <strong>Progress:</strong> <span id="countProgress"></span> |
              <strong>Variances:</strong> <span id="countVariances"></span>
            </div>
          </div>
        </div>

        <!-- Count Entry Table -->
        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Description</th>
                <th>Location</th>
                <th class="text-end">System Qty</th>
                <th class="text-end">Counted Qty</th>
                <th class="text-end">Variance</th>
                <th>Status</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody id="countEntryTable"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-danger" id="cancelSessionBtn" onclick="cancelCurrentSession()" style="display: none;">
          <i class="ti ti-x me-1"></i>Cancel Session
        </button>
        <button type="button" class="btn btn-warning" onclick="showVarianceReview()">Review Variances</button>
        <button type="button" class="btn btn-success" onclick="completeSession()">Complete Session</button>
      </div>
    </div>
  </div>
</div>

<!-- Variance Review Modal -->
<div class="modal modal-blur fade" id="varianceModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Variance Review - <span id="varianceSessionNumber"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="varianceSessionId">

        <!-- Summary -->
        <div class="row mb-3">
          <div class="col-md-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="text-muted">Items with Variance</div>
                <div class="h2 mb-0" id="varianceSummaryItems">-</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="text-muted">Total Variance</div>
                <div class="h2 mb-0" id="varianceSummaryTotal">-</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="text-muted">Positive Variance</div>
                <div class="h2 mb-0 text-success" id="varianceSummaryPositive">-</div>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="card card-sm">
              <div class="card-body">
                <div class="text-muted">Negative Variance</div>
                <div class="h2 mb-0 text-danger" id="varianceSummaryNegative">-</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Variance Items -->
        <div class="mb-3">
          <button class="btn btn-sm btn-success" onclick="approveAllVariances()">
            <i class="ti ti-check me-1"></i>Approve All Variances
          </button>
        </div>

        <div class="table-responsive">
          <table class="table table-vcenter">
            <thead>
              <tr>
                <th style="width: 5%">
                  <input type="checkbox" class="form-check-input" onchange="toggleAllVariances(this)">
                </th>
                <th>SKU</th>
                <th>Description</th>
                <th class="text-end">System</th>
                <th class="text-end">Counted</th>
                <th class="text-end">Variance</th>
                <th class="text-end">%</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="varianceTable"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" onclick="approveSelectedVariances()">
          <i class="ti ti-check me-1"></i>Approve Selected
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Session Details Modal -->
<div class="modal modal-blur fade" id="sessionDetailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Session Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <table class="table table-sm">
              <tr>
                <th>Session #:</th>
                <td id="detailSessionNumber"></td>
              </tr>
              <tr>
                <th>Location:</th>
                <td id="detailLocation"></td>
              </tr>
              <tr>
                <th>Status:</th>
                <td id="detailStatus"></td>
              </tr>
              <tr>
                <th>Scheduled:</th>
                <td id="detailScheduled"></td>
              </tr>
            </table>
          </div>
          <div class="col-md-6">
            <table class="table table-sm">
              <tr>
                <th>Total Items:</th>
                <td id="detailTotalItems"></td>
              </tr>
              <tr>
                <th>Items Counted:</th>
                <td id="detailCountedItems"></td>
              </tr>
              <tr>
                <th>Variances:</th>
                <td id="detailVariances"></td>
              </tr>
              <tr>
                <th>Accuracy:</th>
                <td id="detailAccuracy"></td>
              </tr>
            </table>
          </div>
        </div>

        <div id="detailNotesSection" style="display: none;">
          <strong>Notes:</strong>
          <p id="detailNotes" class="text-muted"></p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
let currentSession = null;
let allCategories = [];
let allProducts = [];
let allUsers = [];
let searchTimeout = null;

// Safe modal helpers
function safeShowModal(modalId) {
  const modalElement = document.getElementById(modalId);
  if (!modalElement) return;

  // Try using Bootstrap if available
  if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
    const modal = new window.bootstrap.Modal(modalElement);
    modal.show();
    return modal;
  }

  // Fallback: manual modal display
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop fade show';
  backdrop.id = 'backdrop-' + modalId;
  document.body.appendChild(backdrop);

  modalElement.style.display = 'block';
  modalElement.classList.add('show');
  modalElement.setAttribute('aria-modal', 'true');
  modalElement.removeAttribute('aria-hidden');
  document.body.classList.add('modal-open');

  // Close on backdrop click
  backdrop.addEventListener('click', () => safeHideModal(modalId));

  // Add close button listeners
  const closeButtons = modalElement.querySelectorAll('[data-bs-dismiss="modal"]');
  closeButtons.forEach(btn => {
    btn.onclick = () => safeHideModal(modalId);
  });

  return null;
}

function safeHideModal(modalId) {
  const modalElement = document.getElementById(modalId);
  if (!modalElement) return;

  // Try using Bootstrap if available
  if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
    const modal = window.bootstrap.Modal.getInstance(modalElement);
    if (modal) {
      modal.hide();
      return;
    }
  }

  // Fallback: manual modal hide
  modalElement.style.display = 'none';
  modalElement.classList.remove('show');
  modalElement.setAttribute('aria-hidden', 'true');
  modalElement.removeAttribute('aria-modal');
  document.body.classList.remove('modal-open');

  const backdrop = document.getElementById('backdrop-' + modalId);
  if (backdrop) backdrop.remove();
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
  loadCategories();
  loadProducts();
  loadUsers();
  loadCycleCounts();
  loadStatistics();

  // Set today's date as default
  document.getElementById('sessionDate').value = new Date().toISOString().split('T')[0];
});

// Load cycle count sessions
async function loadCycleCounts() {
  try {
    document.getElementById('sessionLoading').style.display = 'block';
    document.getElementById('sessionContent').style.display = 'none';

    const params = new URLSearchParams();
    const status = document.getElementById('filterStatus').value;
    const location = document.getElementById('filterLocation').value;
    const search = document.getElementById('filterSearch').value;

    if (status) params.append('status', status);
    if (location) params.append('location', location);
    if (search) params.append('search', search);

    const response = await authenticatedFetch(`/cycle-counts?${params.toString()}`);
    const sessions = response.data || response;

    renderCycleCounts(sessions);

    document.getElementById('sessionLoading').style.display = 'none';
    document.getElementById('sessionContent').style.display = 'block';
  } catch (error) {
    console.error('Error loading cycle counts:', error);
    showNotification('Error loading cycle counts', 'danger');
  }
}

// Render cycle count sessions
function renderCycleCounts(sessions) {
  const tbody = document.getElementById('sessionTableBody');

  console.log('Rendering cycle count sessions:', sessions.length, 'sessions');
  if (sessions.length > 0) {
    console.log('First session data:', sessions[0]);
    console.log('First session ID:', sessions[0].id);
  }

  if (sessions.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No cycle count sessions found</td></tr>';
    return;
  }

  tbody.innerHTML = sessions.map(session => {
    console.log('Rendering session:', session.id, session.session_number);
    const statusBadge = getSessionStatusBadge(session.status);
    const progress = session.total_items > 0
      ? Math.round((session.counted_items / session.total_items) * 100)
      : 0;

    return `
      <tr>
        <td><strong>${escapeHtml(session.session_number)}</strong></td>
        <td>${session.location || 'All Locations'}</td>
        <td>${formatDate(session.scheduled_date)}</td>
        <td>${session.assigned_user ? escapeHtml(session.assigned_user.name) : '-'}</td>
        <td>${statusBadge}</td>
        <td>
          <div class="progress" style="height: 20px;">
            <div class="progress-bar ${progress === 100 ? 'bg-success' : 'bg-primary'}" style="width: ${progress}%">
              ${progress}%
            </div>
          </div>
        </td>
        <td>
          ${session.status === 'completed' ? `
            <span class="badge ${session.accuracy_percentage >= 95 ? 'text-bg-success' : session.accuracy_percentage >= 90 ? 'text-bg-warning' : 'text-bg-danger'}">
              ${session.accuracy_percentage}%
            </span>
          ` : '-'}
        </td>
        <td>
          <div class="btn-group">
            <button class="btn btn-sm btn-ghost-primary" onclick="viewSessionDetails(${session.id})" title="View">
              <i class="ti ti-eye"></i>
            </button>
            ${session.status === 'planned' || session.status === 'in_progress' ? `
              <button class="btn btn-sm btn-ghost-success" onclick="enterCounts(${session.id})" title="Count">
                <i class="ti ti-clipboard-check"></i>
              </button>
            ` : ''}
            ${session.status === 'completed' ? `
              <button class="btn btn-sm btn-ghost-info" onclick="viewVarianceReport(${session.id})" title="Variance Report">
                <i class="ti ti-chart-bar"></i>
              </button>
            ` : ''}
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

// Load categories
async function loadCategories() {
  try {
    const response = await authenticatedFetch('/categories?is_active=1&per_page=all');
    allCategories = response.data || response;

    const select = document.getElementById('sessionCategory');
    select.innerHTML = '<option value="">All Categories</option>';

    allCategories.forEach(category => {
      const option = document.createElement('option');
      option.value = category.id;
      option.textContent = category.name;
      select.appendChild(option);
    });
  } catch (error) {
    console.error('Error loading categories:', error);
  }
}

// Load products
async function loadProducts() {
  try {
    const response = await authenticatedFetch('/products?is_active=1&per_page=all');
    allProducts = response.data || response;

    const select = document.getElementById('sessionProducts');
    select.innerHTML = '';

    allProducts.forEach(product => {
      const option = document.createElement('option');
      option.value = product.id;
      option.textContent = `${product.sku} - ${product.description}`;
      select.appendChild(option);
    });
  } catch (error) {
    console.error('Error loading products:', error);
  }
}

// Load users
async function loadUsers() {
  try {
    const response = await authenticatedFetch('/user');
    const currentUser = response;

    const select = document.getElementById('sessionAssignedTo');
    select.innerHTML = '<option value="">Select user...</option>';

    const option = document.createElement('option');
    option.value = currentUser.id;
    option.textContent = currentUser.name;
    option.selected = true;
    select.appendChild(option);
  } catch (error) {
    console.error('Error loading users:', error);
  }
}

// Load statistics
async function loadStatistics() {
  try {
    const stats = await authenticatedFetch('/cycle-counts-statistics');

    document.getElementById('statTotalSessions').textContent = stats.total_sessions;
    document.getElementById('statActiveSessions').textContent = stats.active_sessions;
    document.getElementById('statItemsCounted').textContent = stats.items_counted_this_month;
    document.getElementById('statAccuracy').textContent = stats.accuracy_this_month + '%';
  } catch (error) {
    console.error('Error loading statistics:', error);
  }
}

// Show create session modal
function showCreateSessionModal() {
  document.getElementById('sessionForm').reset();
  document.getElementById('sessionDate').value = new Date().toISOString().split('T')[0];

  safeShowModal('createSessionModal');
}

// Create cycle count session
async function createCycleCountSession() {
  try {
    const location = document.getElementById('sessionLocation').value;
    const categoryId = document.getElementById('sessionCategory').value;
    const scheduledDate = document.getElementById('sessionDate').value;
    const assignedTo = document.getElementById('sessionAssignedTo').value;
    const notes = document.getElementById('sessionNotes').value;
    const productSelect = document.getElementById('sessionProducts');
    const productIds = Array.from(productSelect.selectedOptions).map(opt => parseInt(opt.value));

    const data = {
      location: location || null,
      category_id: categoryId ? parseInt(categoryId) : null,
      scheduled_date: scheduledDate,
      assigned_to: assignedTo ? parseInt(assignedTo) : null,
      notes: notes || null,
      product_ids: productIds.length > 0 ? productIds : null,
    };

    await authenticatedFetch('/cycle-counts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });

    showNotification('Cycle count session created successfully', 'success');
    safeHideModal('createSessionModal');
    loadCycleCounts();
    loadStatistics();
  } catch (error) {
    console.error('Error creating cycle count session:', error);
    showNotification(error.message || 'Error creating cycle count session', 'danger');
  }
}

// Enter counts
async function enterCounts(sessionId) {
  try {
    // Validate session ID parameter
    if (!sessionId || sessionId === 'undefined') {
      console.error('Invalid sessionId parameter:', sessionId);
      showNotification('Error: Invalid session ID', 'danger');
      return;
    }

    const apiUrl = `/cycle-counts/${sessionId}`;
    console.log('Fetching cycle count session from:', apiUrl);
    console.log('Full URL will be: /api/v1' + apiUrl);

    const session = await authenticatedFetch(apiUrl);
    currentSession = session;

    console.log('Session loaded:', session);
    console.log('Full session object keys:', Object.keys(session));
    console.log('Session ID:', session.id);
    console.log('Session items:', session.items);
    console.log('Items count:', session.items ? session.items.length : 0);

    // Validate session has an ID
    if (!session.id) {
      console.error('Session has no ID:', session);
      console.error('Session keys:', Object.keys(session));
      console.error('Raw response:', JSON.stringify(session));
      showNotification('Error: Session data is invalid. The session may have been deleted. Please refresh the page.', 'danger');

      // Reload the sessions list to get fresh data
      setTimeout(() => {
        loadCycleCounts();
      }, 2000);
      return;
    }

    document.getElementById('countSessionId').value = session.id;
    document.getElementById('countSessionNumber').textContent = session.session_number;
    document.getElementById('countLocation').textContent = session.location || 'All Locations';
    document.getElementById('countProgress').textContent = `${session.counted_items} / ${session.total_items} items`;
    document.getElementById('countVariances').textContent = `${session.variance_items} items`;

    // Verify the value was set correctly
    const setSessionId = document.getElementById('countSessionId').value;
    console.log('countSessionId set to:', setSessionId);
    if (!setSessionId) {
      console.error('Failed to set countSessionId');
      showNotification('Error: Failed to set session ID', 'danger');
      return;
    }

    // Render count items
    const tbody = document.getElementById('countEntryTable');

    if (!session.items || session.items.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No items to count. This session may have been created with filters that matched no products.</td></tr>';

      // Disable Complete button when there are no items
      const completeButton = document.querySelector('#countEntryModal .btn-success[onclick="completeSession()"]');
      if (completeButton) {
        completeButton.disabled = true;
        completeButton.title = 'Cannot complete session without items';
      }

      // Show Cancel button for empty sessions
      const cancelButton = document.getElementById('cancelSessionBtn');
      if (cancelButton) {
        cancelButton.style.display = 'inline-block';
      }

      safeShowModal('countEntryModal');
      showNotification('Warning: This session has no items to count', 'warning');
      return;
    }

    // Enable Complete button when there are items
    const completeButton = document.querySelector('#countEntryModal .btn-success[onclick="completeSession()"]');
    if (completeButton) {
      completeButton.disabled = false;
      completeButton.title = '';
    }

    // Hide Cancel button when there are items
    const cancelButton = document.getElementById('cancelSessionBtn');
    if (cancelButton) {
      cancelButton.style.display = 'none';
    }

    tbody.innerHTML = session.items.map(item => {
      const variance = item.counted_quantity !== null
        ? item.counted_quantity - item.system_quantity
        : 0;

      let varianceClass = '';
      if (variance > 0) varianceClass = 'text-success';
      else if (variance < 0) varianceClass = 'text-danger';

      return `
        <tr>
          <td><strong>${escapeHtml(item.product.sku)}</strong></td>
          <td>${escapeHtml(item.product.description)}</td>
          <td>${item.location ? escapeHtml(item.location.location) : '-'}</td>
          <td class="text-end">${item.system_quantity}</td>
          <td>
            <input type="number" class="form-control form-control-sm" id="counted${item.id}"
                   value="${item.counted_quantity !== null ? item.counted_quantity : ''}"
                   min="0" style="width: 100px;" onchange="recordItemCount(${item.id})">
          </td>
          <td class="text-end ${varianceClass}">
            <strong id="variance${item.id}">${item.counted_quantity !== null ? variance : '-'}</strong>
          </td>
          <td>
            <span class="badge ${getVarianceStatusBadge(item.variance_status)}" id="status${item.id}">
              ${formatVarianceStatus(item.variance_status)}
            </span>
          </td>
          <td>
            <input type="text" class="form-control form-control-sm" id="notes${item.id}"
                   value="${item.count_notes || ''}" placeholder="Notes..." style="width: 150px;">
          </td>
        </tr>
      `;
    }).join('');

    safeShowModal('countEntryModal');
  } catch (error) {
    console.error('Error loading count session:', error);
    showNotification('Error loading count session', 'danger');
  }
}

// Record item count
async function recordItemCount(itemId) {
  try {
    const countedQty = document.getElementById(`counted${itemId}`).value;
    const notes = document.getElementById(`notes${itemId}`).value;

    if (!countedQty || countedQty === '') return;

    const sessionId = document.getElementById('countSessionId').value;

    await authenticatedFetch(`/cycle-counts/${sessionId}/record-count`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        item_id: itemId,
        counted_quantity: parseInt(countedQty),
        notes: notes || null,
      }),
    });

    // Refresh the session data
    enterCounts(sessionId);
  } catch (error) {
    console.error('Error recording count:', error);
    showNotification(error.message || 'Error recording count', 'danger');
  }
}

// Show variance review
async function showVarianceReview() {
  const sessionId = document.getElementById('countSessionId').value;
  await viewVarianceReport(sessionId);
}

// View variance report
async function viewVarianceReport(sessionId) {
  try {
    const report = await authenticatedFetch(`/cycle-counts/${sessionId}/variance-report`);

    document.getElementById('varianceSessionId').value = sessionId;
    document.getElementById('varianceSessionNumber').textContent = report.session.session_number;

    // Summary
    document.getElementById('varianceSummaryItems').textContent = report.summary.items_with_variance;
    document.getElementById('varianceSummaryTotal').textContent = report.summary.total_variance;
    document.getElementById('varianceSummaryPositive').textContent = '+' + report.summary.positive_variance;
    document.getElementById('varianceSummaryNegative').textContent = report.summary.negative_variance;

    // Variance items
    const tbody = document.getElementById('varianceTable');
    tbody.innerHTML = report.variances.map(item => {
      const variancePercent = item.system_quantity > 0
        ? Math.round((item.variance / item.system_quantity) * 100)
        : 0;

      return `
        <tr>
          <td>
            ${item.variance_status !== 'approved' ? `
              <input type="checkbox" class="form-check-input variance-check" data-item-id="${item.id}" checked>
            ` : ''}
          </td>
          <td><strong>${escapeHtml(item.product.sku)}</strong></td>
          <td>${escapeHtml(item.product.description)}</td>
          <td class="text-end">${item.system_quantity}</td>
          <td class="text-end">${item.counted_quantity}</td>
          <td class="text-end ${item.variance > 0 ? 'text-success' : 'text-danger'}">
            <strong>${item.variance > 0 ? '+' : ''}${item.variance}</strong>
          </td>
          <td class="text-end ${Math.abs(variancePercent) > 10 ? 'text-danger' : 'text-warning'}">
            ${variancePercent}%
          </td>
          <td>
            <span class="badge ${getVarianceStatusBadge(item.variance_status)}">
              ${formatVarianceStatus(item.variance_status)}
            </span>
          </td>
        </tr>
      `;
    }).join('');

    // Hide count entry modal if open
    safeHideModal('countEntryModal');

    safeShowModal('varianceModal');
  } catch (error) {
    console.error('Error loading variance report:', error);
    showNotification('Error loading variance report', 'danger');
  }
}

// Approve selected variances
async function approveSelectedVariances() {
  try {
    const sessionId = document.getElementById('varianceSessionId').value;
    const itemIds = [];

    document.querySelectorAll('.variance-check:checked').forEach(checkbox => {
      itemIds.push(parseInt(checkbox.getAttribute('data-item-id')));
    });

    if (itemIds.length === 0) {
      showNotification('Please select at least one variance to approve', 'warning');
      return;
    }

    if (!confirm(`Approve ${itemIds.length} variance(s)? This will create inventory adjustments.`)) return;

    await authenticatedFetch(`/cycle-counts/${sessionId}/approve-variances`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ item_ids: itemIds }),
    });

    showNotification('Variances approved and adjustments created', 'success');
    viewVarianceReport(sessionId); // Refresh
  } catch (error) {
    console.error('Error approving variances:', error);
    showNotification(error.message || 'Error approving variances', 'danger');
  }
}

// Approve all variances
async function approveAllVariances() {
  const itemIds = [];
  document.querySelectorAll('.variance-check').forEach(checkbox => {
    itemIds.push(parseInt(checkbox.getAttribute('data-item-id')));
    checkbox.checked = true;
  });

  await approveSelectedVariances();
}

// Toggle all variances
function toggleAllVariances(checkbox) {
  document.querySelectorAll('.variance-check').forEach(cb => {
    cb.checked = checkbox.checked;
  });
}

// Complete session
async function completeSession() {
  const sessionId = document.getElementById('countSessionId').value;

  // Validate session ID
  if (!sessionId || sessionId === 'undefined' || sessionId === '') {
    console.error('Invalid session ID:', sessionId);
    showNotification('Error: Session ID is missing. Please close and reopen the count modal.', 'danger');
    return;
  }

  // Check if current session has items
  if (!currentSession || !currentSession.items || currentSession.items.length === 0) {
    showNotification('Cannot complete session: No items to count', 'danger');
    return;
  }

  if (!confirm('Complete this cycle count session? All variances must be reviewed first.')) return;

  try {
    await authenticatedFetch(`/cycle-counts/${sessionId}/complete`, { method: 'POST' });

    showNotification('Cycle count session completed successfully', 'success');
    safeHideModal('countEntryModal');
    loadCycleCounts();
    loadStatistics();
  } catch (error) {
    console.error('Error completing session:', error);
    showNotification(error.message || 'Error completing session', 'danger');
  }
}

// Cancel current session
async function cancelCurrentSession() {
  const sessionId = document.getElementById('countSessionId').value;

  // Validate session ID
  if (!sessionId || sessionId === 'undefined' || sessionId === '') {
    console.error('Invalid session ID:', sessionId);
    showNotification('Error: Session ID is missing', 'danger');
    return;
  }

  if (!confirm('Cancel this cycle count session? This action cannot be undone.')) return;

  try {
    await authenticatedFetch(`/cycle-counts/${sessionId}/cancel`, { method: 'POST' });

    showNotification('Cycle count session cancelled successfully', 'success');
    safeHideModal('countEntryModal');
    loadCycleCounts();
    loadStatistics();
  } catch (error) {
    console.error('Error cancelling session:', error);
    showNotification(error.message || 'Error cancelling session', 'danger');
  }
}

// View session details
async function viewSessionDetails(sessionId) {
  try {
    const session = await authenticatedFetch(`/cycle-counts/${sessionId}`);

    document.getElementById('detailSessionNumber').textContent = session.session_number;
    document.getElementById('detailLocation').textContent = session.location || 'All Locations';
    document.getElementById('detailStatus').innerHTML = getSessionStatusBadge(session.status);
    document.getElementById('detailScheduled').textContent = formatDate(session.scheduled_date);
    document.getElementById('detailTotalItems').textContent = session.total_items;
    document.getElementById('detailCountedItems').textContent = session.counted_items;
    document.getElementById('detailVariances').textContent = session.variance_items;
    document.getElementById('detailAccuracy').textContent = session.accuracy_percentage + '%';

    if (session.notes) {
      document.getElementById('detailNotes').textContent = session.notes;
      document.getElementById('detailNotesSection').style.display = 'block';
    } else {
      document.getElementById('detailNotesSection').style.display = 'none';
    }

    safeShowModal('sessionDetailsModal');
  } catch (error) {
    console.error('Error loading session details:', error);
    showNotification('Error loading session details', 'danger');
  }
}

// Clear filters
function clearFilters() {
  document.getElementById('filterStatus').value = '';
  document.getElementById('filterLocation').value = '';
  document.getElementById('filterSearch').value = '';
  loadCycleCounts();
}

// Debounce search
function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadCycleCounts();
  }, 500);
}

// Helper functions
function getSessionStatusBadge(status) {
  const badges = {
    'planned': '<span class="badge text-bg-secondary">Planned</span>',
    'in_progress': '<span class="badge text-bg-primary">In Progress</span>',
    'completed': '<span class="badge text-bg-success">Completed</span>',
    'cancelled': '<span class="badge text-bg-danger">Cancelled</span>',
  };
  return badges[status] || status;
}

function getVarianceStatusBadge(status) {
  const colors = {
    'pending': 'text-bg-secondary',
    'within_tolerance': 'text-bg-success',
    'requires_review': 'text-bg-warning',
    'approved': 'text-bg-primary',
    'rejected': 'text-bg-danger',
  };
  return colors[status] || 'text-bg-secondary';
}

function formatVarianceStatus(status) {
  const labels = {
    'pending': 'Pending',
    'within_tolerance': 'Within Tolerance',
    'requires_review': 'Requires Review',
    'approved': 'Approved',
    'rejected': 'Rejected',
  };
  return labels[status] || status;
}

function formatDate(dateString) {
  if (!dateString) return '-';
  return new Date(dateString).toLocaleDateString();
}

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
</script>
@endsection
