@extends('layouts.app')

@section('title', 'Transaction History - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Inventory Management</div>
              <h1 class="page-title">Transaction History</h1>
              <p class="text-muted">View and audit all inventory transactions</p>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn" onclick="exportTransactions()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                  Export
                </button>
                <button class="btn btn-primary" onclick="showAddTransactionModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Transaction
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <!-- Stats Cards -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Total Transactions</div>
                  <div class="h1 mb-3" id="statTotal">-</div>
                  <div>All time transactions</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">This Month</div>
                  <div class="h1 mb-3 text-primary" id="statThisMonth">-</div>
                  <div>Transactions this month</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Today</div>
                  <div class="h1 mb-3 text-success" id="statToday">-</div>
                  <div>Transactions today</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Most Active Type</div>
                  <div class="h1 mb-3 text-info" id="statMostActiveType">-</div>
                  <div>Most common transaction</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Filters & Transactions Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Transactions</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="typeFilter" style="width: 150px;">
                      <option value="">All Types</option>
                    </select>
                    <input type="date" class="form-control form-control-sm" id="startDate" style="width: 150px;">
                    <input type="date" class="form-control form-control-sm" id="endDate" style="width: 150px;">
                    <input type="text" class="form-control form-control-sm" placeholder="Search..." id="searchInput" style="min-width: 200px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="loadingIndicator">
                    <div class="spinner-border" role="status"></div>
                    <div>Loading transactions...</div>
                  </div>

                  <div class="table-responsive" id="transactionsTableContainer" style="display: none;">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th>Date/Time</th>
                          <th>Type</th>
                          <th>Product</th>
                          <th class="text-end">Quantity</th>
                          <th class="text-end">Before</th>
                          <th class="text-end">After</th>
                          <th>Reference</th>
                          <th>User</th>
                          <th class="w-1"></th>
                        </tr>
                      </thead>
                      <tbody id="transactionsTableBody"></tbody>
                    </table>
                  </div>

                  <!-- Pagination -->
                  <div class="mt-3 d-flex justify-content-between align-items-center" id="paginationContainer" style="display: none !important;">
                    <div class="text-muted">
                      Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalRecords">0</span> transactions
                    </div>
                    <nav>
                      <ul class="pagination mb-0" id="paginationLinks"></ul>
                    </nav>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>
  </div>

  <!-- Transaction Details Modal -->
  <div class="modal modal-blur fade" id="transactionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Transaction Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="transactionDetails"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Manual Transaction Modal -->
  <div class="modal modal-blur fade" id="addTransactionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Manual Transaction</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="addTransactionForm">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Product</label>
              <input type="text" class="form-control" id="manualTransactionProductSearch" placeholder="Search by SKU or description..." autocomplete="off">
              <input type="hidden" id="manualTransactionProductId" name="product_id">
              <div id="productSearchResults" class="list-group mt-1" style="display: none; max-height: 200px; overflow-y: auto;"></div>
              <div id="selectedProductInfo" class="mt-2" style="display: none;">
                <div class="alert alert-info mb-0">
                  <strong id="selectedProductDisplay"></strong>
                  <div class="small" id="selectedProductAvailable"></div>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">Transaction Type</label>
                <select class="form-select" name="type" id="manualTransactionType" required>
                  <option value="adjustment">Adjustment</option>
                  <option value="return">Return</option>
                  <option value="receipt">Receipt</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Quantity</label>
                <input type="number" class="form-control" name="quantity" id="manualTransactionQuantity" placeholder="Enter quantity" required>
                <small class="form-hint">Use negative numbers to remove from inventory</small>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label required">Date & Time</label>
              <input type="datetime-local" class="form-control" name="transaction_date" id="manualTransactionDate" required>
            </div>
            <div class="mb-3">
              <label class="form-label required">Reference/Job Name</label>
              <input type="text" class="form-control" name="reference_number" id="manualTransactionReference" placeholder="e.g., Service Job #123" required>
              <small class="form-hint">Enter the job name, service ticket, or other reference</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" name="notes" id="manualTransactionNotes" rows="2" placeholder="Additional details..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveManualTransactionBtn">
              <i class="ti ti-check me-1"></i>
              Save Transaction
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

@endsection


@push('scripts')
  <script>
    let allTransactions = [];
    let filteredTransactions = [];
    let currentPage = 1;
    let perPage = 50;

    document.addEventListener('DOMContentLoaded', () => {
      loadTransactionTypes();
      loadTransactions();
      loadStatistics();

      // Filter listeners
      document.getElementById('typeFilter').addEventListener('change', applyFilters);
      document.getElementById('startDate').addEventListener('change', applyFilters);
      document.getElementById('endDate').addEventListener('change', applyFilters);
      document.getElementById('searchInput').addEventListener('input', debounce(applyFilters, 300));
    });

    async function loadTransactionTypes() {
      try {
        const types = await authenticatedFetch('/transactions-types');
        const select = document.getElementById('typeFilter');

        Object.entries(types).forEach(([key, label]) => {
          const option = document.createElement('option');
          option.value = key;
          option.textContent = label;
          select.appendChild(option);
        });
      } catch (error) {
        console.error('Error loading transaction types:', error);
      }
    }

    async function loadStatistics() {
      try {
        const stats = await authenticatedFetch('/transactions-statistics');

        document.getElementById('statTotal').textContent = (stats.total || 0).toLocaleString();
        document.getElementById('statThisMonth').textContent = (stats.this_month || 0).toLocaleString();
        document.getElementById('statToday').textContent = (stats.today || 0).toLocaleString();

        if (stats.most_active_type) {
          document.getElementById('statMostActiveType').textContent = stats.most_active_type.label || '-';
        }
      } catch (error) {
        console.error('Error loading statistics:', error);
      }
    }

    async function loadTransactions() {
      try {
        const response = await authenticatedFetch('/transactions?per_page=500&sort=-transaction_date');

        // Handle both paginated and plain array responses
        allTransactions = Array.isArray(response) ? response : (response.data || []);
        filteredTransactions = allTransactions;
        renderTransactionsTable();

        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('transactionsTableContainer').style.display = 'block';
        document.getElementById('paginationContainer').style.display = 'flex';
      } catch (error) {
        console.error('Error loading transactions:', error);
        if (error.message !== 'Session expired') {
          showNotification('Failed to load transactions', 'danger');
        }
      }
    }

    function applyFilters() {
      const typeFilter = document.getElementById('typeFilter').value;
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;
      const search = document.getElementById('searchInput').value.toLowerCase();

      filteredTransactions = allTransactions.filter(trans => {
        // Type filter
        if (typeFilter && trans.type !== typeFilter) return false;

        // Date filters
        if (startDate) {
          const transDate = new Date(trans.transaction_date);
          if (transDate < new Date(startDate)) return false;
        }
        if (endDate) {
          const transDate = new Date(trans.transaction_date);
          if (transDate > new Date(endDate + 'T23:59:59')) return false;
        }

        // Search filter
        if (search) {
          const searchableText = [
            trans.product?.sku,
            trans.product?.description,
            trans.reference_number,
            trans.notes,
            trans.user?.name
          ].filter(Boolean).join(' ').toLowerCase();

          if (!searchableText.includes(search)) return false;
        }

        return true;
      });

      currentPage = 1;
      renderTransactionsTable();
    }

    function renderTransactionsTable() {
      const tbody = document.getElementById('transactionsTableBody');
      tbody.innerHTML = '';

      if (filteredTransactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-5">No transactions found</td></tr>';
        document.getElementById('paginationContainer').style.display = 'none';
        return;
      }

      // Pagination
      const start = (currentPage - 1) * perPage;
      const end = start + perPage;
      const pageTransactions = filteredTransactions.slice(start, end);

      pageTransactions.forEach(trans => {
        const typeColors = {
          'receipt': 'success',
          'shipment': 'warning',
          'adjustment': 'info',
          'transfer': 'purple',
          'return': 'danger',
          'cycle_count': 'azure',
          'job_issue': 'orange'
        };
        const typeColor = typeColors[trans.type] || 'secondary';

        const quantityClass = trans.quantity >= 0 ? 'text-success' : 'text-danger';
        const quantitySign = trans.quantity >= 0 ? '+' : '';

        const row = `
          <tr>
            <td>
              <div class="small">${formatDateTime(trans.transaction_date)}</div>
            </td>
            <td>
              <span class="badge text-bg-${typeColor}">${formatType(trans.type)}</span>
            </td>
            <td>
              <div>
                <strong>${trans.product?.sku || '-'}</strong>
                <div class="small text-muted">${trans.product?.description || '-'}</div>
              </div>
            </td>
            <td class="text-end ${quantityClass} fw-bold">${quantitySign}${trans.quantity}</td>
            <td class="text-end">${trans.quantity_before}</td>
            <td class="text-end">${trans.quantity_after}</td>
            <td>
              <div class="small">${trans.reference_number || '-'}</div>
            </td>
            <td>
              <div class="small">${trans.user?.name || '-'}</div>
            </td>
            <td class="table-actions">
              <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="viewTransaction(${trans.id})" title="View Details">
                <i class="ti ti-eye"></i>
              </button>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });

      updatePagination();
    }

    function updatePagination() {
      const total = filteredTransactions.length;
      const start = (currentPage - 1) * perPage + 1;
      const end = Math.min(currentPage * perPage, total);
      const totalPages = Math.ceil(total / perPage);

      document.getElementById('showingStart').textContent = start;
      document.getElementById('showingEnd').textContent = end;
      document.getElementById('totalRecords').textContent = total;

      const paginationLinks = document.getElementById('paginationLinks');
      paginationLinks.innerHTML = '';

      if (totalPages <= 1) {
        document.getElementById('paginationContainer').style.display = 'none';
        return;
      }

      document.getElementById('paginationContainer').style.display = 'flex';

      // Previous button
      paginationLinks.innerHTML += `
        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
          <a class="page-link" href="#" onclick="goToPage(${currentPage - 1}); return false;">Prev</a>
        </li>
      `;

      // Page numbers
      for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
          paginationLinks.innerHTML += `
            <li class="page-item ${i === currentPage ? 'active' : ''}">
              <a class="page-link" href="#" onclick="goToPage(${i}); return false;">${i}</a>
            </li>
          `;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
          paginationLinks.innerHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
      }

      // Next button
      paginationLinks.innerHTML += `
        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
          <a class="page-link" href="#" onclick="goToPage(${currentPage + 1}); return false;">Next</a>
        </li>
      `;
    }

    function goToPage(page) {
      currentPage = page;
      renderTransactionsTable();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function viewTransaction(transactionId) {
      try {
        const trans = await authenticatedFetch(`/transactions/${transactionId}`);

        const typeColors = {
          'receipt': 'success',
          'shipment': 'warning',
          'adjustment': 'info',
          'transfer': 'purple',
          'return': 'danger',
          'cycle_count': 'azure',
          'job_issue': 'orange'
        };
        const typeColor = typeColors[trans.type] || 'secondary';
        const quantityClass = trans.quantity >= 0 ? 'text-success' : 'text-danger';
        const quantitySign = trans.quantity >= 0 ? '+' : '';

        document.getElementById('transactionDetails').innerHTML = `
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Transaction Date</label>
              <p>${formatDateTime(trans.transaction_date)}</p>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Transaction Type</label>
              <p><span class="badge text-bg-${typeColor}">${formatType(trans.type)}</span></p>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Product</label>
              <p><strong>${trans.product?.sku || '-'}</strong> - ${trans.product?.description || '-'}</p>
            </div>
          </div>
          <div class="row mb-3">
            <div class="col-md-4">
              <label class="form-label fw-bold">Quantity Change</label>
              <p class="${quantityClass} h3">${quantitySign}${trans.quantity}</p>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Quantity Before</label>
              <p class="h3">${trans.quantity_before}</p>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold">Quantity After</label>
              <p class="h3">${trans.quantity_after}</p>
            </div>
          </div>
          ${trans.reference_number ? `
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Reference Number</label>
              <p>${trans.reference_number}</p>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Reference Type</label>
              <p>${trans.reference_type || '-'}</p>
            </div>
          </div>
          ` : ''}
          ${trans.notes ? `
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Notes</label>
              <p style="white-space: pre-wrap;">${trans.notes}</p>
            </div>
          </div>
          ` : ''}
          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label fw-bold">Performed By</label>
              <p>${trans.user?.name || '-'}</p>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-bold">Created At</label>
              <p>${formatDateTime(trans.created_at)}</p>
            </div>
          </div>
        `;

        showModal(document.getElementById('transactionModal'));
      } catch (error) {
        console.error('Error loading transaction details:', error);
        showNotification('Failed to load transaction details', 'danger');
      }
    }

    function formatType(type) {
      const types = {
        'receipt': 'Receipt',
        'shipment': 'Shipment',
        'adjustment': 'Adjustment',
        'transfer': 'Transfer',
        'return': 'Return',
        'cycle_count': 'Cycle Count',
        'job_issue': 'Job Issue'
      };
      return types[type] || type;
    }

    function formatDateTime(dateString) {
      if (!dateString) return '-';
      const date = new Date(dateString);
      return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }

    async function exportTransactions() {
      try {
        window.location.href = `/api/v1/transactions-export?token=${authToken}`;
        showNotification('Export started successfully', 'success');
      } catch (error) {
        console.error('Export failed:', error);
        showNotification('Export failed', 'danger');
      }
    }

    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    // ========== MANUAL TRANSACTION ==========
    let selectedProduct = null;
    let productSearchTimeout = null;

    function showAddTransactionModal() {
      // Reset form
      document.getElementById('addTransactionForm').reset();
      selectedProduct = null;
      document.getElementById('selectedProductInfo').style.display = 'none';
      document.getElementById('productSearchResults').style.display = 'none';

      // Set default date to now
      const now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      document.getElementById('manualTransactionDate').value = now.toISOString().slice(0, 16);

      showModal(document.getElementById('addTransactionModal'));
    }

    // Product search
    document.getElementById('manualTransactionProductSearch').addEventListener('input', function(e) {
      const searchTerm = e.target.value.trim();

      if (productSearchTimeout) {
        clearTimeout(productSearchTimeout);
      }

      if (searchTerm.length < 2) {
        document.getElementById('productSearchResults').style.display = 'none';
        return;
      }

      productSearchTimeout = setTimeout(async () => {
        try {
          const response = await authenticatedFetch(`/products?search=${encodeURIComponent(searchTerm)}&per_page=10`);
          const products = await response.json();

          const resultsContainer = document.getElementById('productSearchResults');

          if (products.length === 0) {
            resultsContainer.innerHTML = '<div class="list-group-item text-muted">No products found</div>';
            resultsContainer.style.display = 'block';
            return;
          }

          resultsContainer.innerHTML = products.map(product => `
            <a href="#" class="list-group-item list-group-item-action" onclick="selectProduct(${product.id}, '${product.sku.replace(/'/g, "\\'")}', '${product.description.replace(/'/g, "\\'")}', ${product.quantity_available || 0}); return false;">
              <div>
                <strong>${product.sku}</strong> - ${product.description}
                <div class="small text-muted">Available: ${product.quantity_available || 0}</div>
              </div>
            </a>
          `).join('');
          resultsContainer.style.display = 'block';
        } catch (error) {
          console.error('Error searching products:', error);
        }
      }, 300);
    });

    function selectProduct(id, sku, description, available) {
      selectedProduct = { id, sku, description, available };

      document.getElementById('manualTransactionProductId').value = id;
      document.getElementById('manualTransactionProductSearch').value = `${sku} - ${description}`;
      document.getElementById('selectedProductDisplay').textContent = `${sku} - ${description}`;
      document.getElementById('selectedProductAvailable').textContent = `Current Available: ${available}`;
      document.getElementById('selectedProductInfo').style.display = 'block';
      document.getElementById('productSearchResults').style.display = 'none';
    }

    // Submit manual transaction
    document.getElementById('addTransactionForm').addEventListener('submit', async function(e) {
      e.preventDefault();

      if (!selectedProduct) {
        showNotification('Please select a product', 'danger');
        return;
      }

      const formData = new FormData(e.target);
      const data = {
        product_id: selectedProduct.id,
        type: formData.get('type'),
        quantity: parseInt(formData.get('quantity')),
        reference_number: formData.get('reference_number'),
        transaction_date: formData.get('transaction_date'),
        notes: formData.get('notes') || null,
      };

      const btn = document.getElementById('saveManualTransactionBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

      try {
        const response = await apiCall('/transactions/manual', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(data)
        });

        // Handle 401 Unauthorized
        if (response.status === 401) {
          localStorage.removeItem('authToken');
          authToken = null;
          showLogin();
          showNotification('Session expired. Please login again.', 'warning');
          return;
        }

        if (response.ok) {
          showNotification('Transaction added successfully', 'success');
          hideModal(document.getElementById('addTransactionModal'));

          // Reload transactions and stats
          await loadTransactions();
          await loadStatistics();
        } else {
          const error = await response.json();
          showNotification(error.message || 'Failed to add transaction', 'danger');
        }
      } catch (error) {
        console.error('Error adding transaction:', error);
        showNotification('Failed to add transaction', 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-check me-1"></i>Save Transaction';
      }
    });
  </script>
@endpush
