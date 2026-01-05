<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ForgeDesk - Inventory Management</title>
  <link href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" rel="stylesheet">
<style>
    .status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
    .table-actions { white-space: nowrap; }
    .login-container {
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    #app { display: none; }
    #app.active { display: block; }
    #loginPage { display: none; }
    #loginPage.active { display: flex; }
    .loading { text-align: center; padding: 2rem; }

    /* Theme switcher styles */
    [data-bs-theme="dark"] .hide-theme-dark {
      display: none;
    }
    [data-bs-theme="light"] .hide-theme-light {
      display: none;
    }

    /* Modal improvements */
    .modal-body .form-label.required:after {
      content: " *";
      color: #d63939;
    }

    /* Toast notifications */
    .alert.position-fixed {
      animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
      from {
        transform: translateX(100%);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
  </style>
</head>
<body>
  <!-- Login Page -->
  <div id="loginPage" class="login-container">
    <div class="card" style="width: 100%; max-width: 400px;">
      <div class="card-body">
        <h2 class="text-center mb-4">ForgeDesk</h2>
        <form id="loginForm">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="loginEmail" value="admin@forgedesk.local" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" id="loginPassword" value="password" required>
          </div>
          <div id="loginError" class="alert alert-danger" style="display: none;"></div>
          <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Main Application -->
  <div id="app" class="page">
    <header class="navbar navbar-expand-md navbar-light d-print-none">
      <div class="container-xl">
        <h1 class="navbar-brand d-none-navbar-horizontal pe-0 pe-md-3">
          <a href="#">ForgeDesk</a>
        </h1>
        <div class="navbar-nav flex-row order-md-last">
          <div class="nav-item dropdown d-none d-md-flex me-3">
            <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1" aria-label="Show notifications">
              <i class="ti ti-bell icon"></i>
              <span class="badge bg-red"></span>
            </a>
            <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Notifications</h3>
                </div>
                <div class="list-group list-group-flush list-group-hoverable">
                  <div class="list-group-item">
                    <div class="text-truncate">
                      <div class="text-muted">No new notifications</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="nav-item dropdown d-none d-md-flex me-3">
            <a href="#" class="nav-link px-0 hide-theme-dark" title="Enable dark mode" data-bs-toggle="tooltip" data-bs-placement="bottom" id="theme-toggle-light">
              <i class="ti ti-moon icon"></i>
            </a>
            <a href="#" class="nav-link px-0 hide-theme-light" title="Enable light mode" data-bs-toggle="tooltip" data-bs-placement="bottom" id="theme-toggle-dark">
              <i class="ti ti-sun icon"></i>
            </a>
          </div>
          <div class="nav-item dropdown">
            <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open user menu">
              <span class="avatar avatar-sm" id="userAvatar">A</span>
              <div class="d-none d-xl-block ps-2">
                <div id="userName">Admin</div>
                <div class="mt-1 small text-muted" id="userEmail">admin@forgedesk.local</div>
              </div>
            </a>
            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
              <a href="#" class="dropdown-item" id="logoutBtn">Logout</a>
            </div>
          </div>
        </div>
        <div class="collapse navbar-collapse" id="navbar-menu">
          <div class="d-flex flex-column flex-md-row flex-fill align-items-stretch align-items-md-center">
            <ul class="navbar-nav">
              <li class="nav-item">
                <a class="nav-link" href="#">
                  <span class="nav-link-icon d-md-none d-lg-inline-block">
                    <i class="ti ti-home icon"></i>
                  </span>
                  <span class="nav-link-title">Dashboard</span>
                </a>
              </li>
              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#navbar-extra" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                  <span class="nav-link-icon d-md-none d-lg-inline-block">
                    <i class="ti ti-package icon"></i>
                  </span>
                  <span class="nav-link-title">Inventory</span>
                </a>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="#">All Products</a>
                  <a class="dropdown-item" href="#">Low Stock</a>
                  <a class="dropdown-item" href="#">Critical Stock</a>
                </div>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </header>

    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <h2 class="page-title">Inventory Dashboard</h2>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn btn-outline-primary" onclick="exportProducts()">
                  <i class="ti ti-download"></i> Export
                </button>
                <button class="btn btn-primary" onclick="showAddProductModal()">
                  <i class="ti ti-plus"></i> Add Product
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="page-body">
        <div class="container-xl">
          <!-- Stats Cards -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">SKUs Tracked</div>
                  <div class="h1 mb-3" id="statSkus">-</div>
                  <div>Active inventory items</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Units on Hand</div>
                  <div class="h1 mb-3" id="statOnHand">-</div>
                  <div>Total inventory count</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Available Units</div>
                  <div class="h1 mb-3" id="statAvailable">-</div>
                  <div>Uncommitted inventory</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Low Stock Alerts</div>
                  <div class="h1 mb-3 text-warning" id="statLowStock">-</div>
                  <div>Items below threshold</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Inventory Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Inventory Snapshot</h3>
                  <div class="ms-auto">
                    <input type="text" class="form-control form-control-sm" placeholder="Search..." id="searchInput">
                  </div>
                </div>
                <div class="card-body">
                  <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                      <a href="#" class="nav-link active" data-tab="all">All Inventory</a>
                    </li>
                    <li class="nav-item">
                      <a href="#" class="nav-link" data-tab="low_stock">Low Stock <span class="badge bg-warning ms-2" id="badgeLowStock">0</span></a>
                    </li>
                    <li class="nav-item">
                      <a href="#" class="nav-link" data-tab="critical">Critical <span class="badge bg-danger ms-2" id="badgeCritical">0</span></a>
                    </li>
                  </ul>

                  <div class="loading" id="loadingIndicator">
                    <div class="spinner-border" role="status"></div>
                    <div>Loading inventory...</div>
                  </div>

                  <div class="table-responsive" id="inventoryTableContainer" style="display: none;">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th>SKU</th>
                          <th>Description</th>
                          <th>Location</th>
                          <th class="text-end">On Hand</th>
                          <th class="text-end">Committed</th>
                          <th class="text-end">Available</th>
                          <th>Status</th>
                          <th class="w-1"></th>
                        </tr>
                      </thead>
                      <tbody id="inventoryTableBody"></tbody>
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

  <!-- Add Product Modal -->
  <div class="modal modal-blur fade" id="addProductModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add New Product</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="addProductForm">
          <div class="modal-body">
            <div class="row mb-3">
              <div class="col-lg-6">
                <div class="mb-3">
                  <label class="form-label required">SKU</label>
                  <input type="text" class="form-control" name="sku" id="productSku" placeholder="Enter SKU" required>
                  <div class="invalid-feedback"></div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="mb-3">
                  <label class="form-label required">Description</label>
                  <input type="text" class="form-control" name="description" id="productDescription" placeholder="Product description" required>
                  <div class="invalid-feedback"></div>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Long Description</label>
              <textarea class="form-control" name="long_description" id="productLongDescription" rows="3" placeholder="Detailed product description"></textarea>
            </div>

            <div class="row mb-3">
              <div class="col-lg-6">
                <div class="mb-3">
                  <label class="form-label">Category</label>
                  <input type="text" class="form-control" name="category" id="productCategory" placeholder="e.g., Hardware, Electronics">
                </div>
              </div>
              <div class="col-lg-6">
                <div class="mb-3">
                  <label class="form-label">Location</label>
                  <input type="text" class="form-control" name="location" id="productLocation" placeholder="e.g., A-12-03">
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-lg-6">
                <div class="mb-3">
                  <label class="form-label required">Unit Cost</label>
                  <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" name="unit_cost" id="productUnitCost" placeholder="0.00" step="0.01" min="0" required>
                  </div>
                  <div class="invalid-feedback"></div>
                </div>
              </div>
              <div class="col-lg-6">
                <div class="mb-3">
                  <label class="form-label required">Unit Price</label>
                  <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" name="unit_price" id="productUnitPrice" placeholder="0.00" step="0.01" min="0" required>
                  </div>
                  <div class="invalid-feedback"></div>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-lg-4">
                <div class="mb-3">
                  <label class="form-label required">Quantity on Hand</label>
                  <input type="number" class="form-control" name="quantity_on_hand" id="productQuantityOnHand" placeholder="0" min="0" required>
                  <div class="invalid-feedback"></div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="mb-3">
                  <label class="form-label required">Minimum Quantity</label>
                  <input type="number" class="form-control" name="minimum_quantity" id="productMinQuantity" placeholder="0" min="0" required>
                  <div class="invalid-feedback"></div>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="mb-3">
                  <label class="form-label">Maximum Quantity</label>
                  <input type="number" class="form-control" name="maximum_quantity" id="productMaxQuantity" placeholder="Optional" min="0">
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-lg-4">
                <div class="mb-3">
                  <label class="form-label required">Unit of Measure</label>
                  <select class="form-select" name="unit_of_measure" id="productUOM" required>
                    <option value="EA">Each (EA)</option>
                    <option value="BOX">Box</option>
                    <option value="CASE">Case</option>
                    <option value="GAL">Gallon (GAL)</option>
                    <option value="LB">Pound (LB)</option>
                    <option value="FT">Foot (FT)</option>
                    <option value="ROLL">Roll</option>
                    <option value="SET">Set</option>
                  </select>
                </div>
              </div>
              <div class="col-lg-8">
                <div class="mb-3">
                  <label class="form-label">Supplier</label>
                  <input type="text" class="form-control" name="supplier" id="productSupplier" placeholder="Supplier name">
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <div class="col-lg-6">
                <div class="mb-3">
                  <label class="form-label">Supplier SKU</label>
                  <input type="text" class="form-control" name="supplier_sku" id="productSupplierSku" placeholder="Supplier's product code">
                </div>
              </div>
              <div class="col-lg-6">
                <div class="mb-3">
                  <label class="form-label">Lead Time (Days)</label>
                  <input type="number" class="form-control" name="lead_time_days" id="productLeadTime" placeholder="0" min="0">
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="productIsActive" checked>
                <span class="form-check-label">Active Product</span>
              </label>
            </div>

            <div id="formError" class="alert alert-danger" style="display: none;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary ms-auto" id="saveProductBtn">
              <i class="ti ti-device-floppy icon"></i>
              Save Product
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js"></script>
  <script>
    const API_BASE = '/api/v1';
    let authToken = localStorage.getItem('authToken');
    let currentTab = 'all';

    async function apiCall(endpoint, options = {}) {
      const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        ...options.headers
      };
      
      if (authToken) {
        headers['Authorization'] = `Bearer ${authToken}`;
      }

      const response = await fetch(`${API_BASE}${endpoint}`, {
        ...options,
        headers
      });

      if (response.status === 401) {
        logout();
        throw new Error('Unauthorized');
      }

      return response;
    }

    document.getElementById('loginForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const email = document.getElementById('loginEmail').value;
      const password = document.getElementById('loginPassword').value;
      
      try {
        const response = await fetch('/api/login', {
          method: 'POST',
          headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ email, password })
        });

        if (response.ok) {
          const data = await response.json();
          authToken = data.token;
          localStorage.setItem('authToken', authToken);
          showApp();
          loadDashboard();
        } else {
          document.getElementById('loginError').textContent = 'Invalid credentials';
          document.getElementById('loginError').style.display = 'block';
        }
      } catch (error) {
        document.getElementById('loginError').textContent = 'Login failed: ' + error.message;
        document.getElementById('loginError').style.display = 'block';
      }
    });

    document.getElementById('logoutBtn').addEventListener('click', logout);

    function logout() {
      authToken = null;
      localStorage.removeItem('authToken');
      showLogin();
    }

    function showApp() {
      document.getElementById('loginPage').classList.remove('active');
      document.getElementById('app').classList.add('active');
    }

    function showLogin() {
      document.getElementById('app').classList.remove('active');
      document.getElementById('loginPage').classList.add('active');
    }

    async function loadDashboard() {
      try {
        document.getElementById('loadingIndicator').style.display = 'block';
        document.getElementById('inventoryTableContainer').style.display = 'none';

        const response = await apiCall('/dashboard');
        const data = await response.json();

        document.getElementById('statSkus').textContent = data.stats.skus_tracked.toLocaleString();
        document.getElementById('statOnHand').textContent = data.stats.units_on_hand.toLocaleString();
        document.getElementById('statAvailable').textContent = data.stats.units_available.toLocaleString();
        document.getElementById('statLowStock').textContent = data.stats.low_stock_alerts.toLocaleString();
        document.getElementById('badgeLowStock').textContent = data.stats.low_stock_alerts;
        document.getElementById('badgeCritical').textContent = data.stats.critical_count;

        renderInventoryTable(data.inventory.data);

        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('inventoryTableContainer').style.display = 'block';
      } catch (error) {
        console.error('Error loading dashboard:', error);
        alert('Failed to load dashboard data');
      }
    }

    function renderInventoryTable(products) {
      const tbody = document.getElementById('inventoryTableBody');
      tbody.innerHTML = '';

      products.forEach(product => {
        const statusBadge = getStatusBadge(product.status);
        const row = `
          <tr>
            <td><span class="text-muted">${product.sku}</span></td>
            <td>${product.description}</td>
            <td>${product.location || '-'}</td>
            <td class="text-end">${product.quantity_on_hand.toLocaleString()}</td>
            <td class="text-end">${product.quantity_committed.toLocaleString()}</td>
            <td class="text-end">${product.quantity_available.toLocaleString()}</td>
            <td>${statusBadge}</td>
            <td class="table-actions">
              <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="viewProduct(${product.id})" title="View">
                <i class="ti ti-eye"></i>
              </button>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });
    }

    function getStatusBadge(status) {
      const badges = {
        'in_stock': '<span class="badge bg-success status-badge">In Stock</span>',
        'low_stock': '<span class="badge bg-warning status-badge">Low Stock</span>',
        'critical': '<span class="badge bg-danger status-badge">Critical</span>',
        'out_of_stock': '<span class="badge bg-dark status-badge">Out of Stock</span>'
      };
      return badges[status] || badges['in_stock'];
    }

    document.querySelectorAll('.nav-link[data-tab]').forEach(link => {
      link.addEventListener('click', async (e) => {
        e.preventDefault();
        document.querySelectorAll('.nav-link[data-tab]').forEach(l => l.classList.remove('active'));
        e.target.classList.add('active');
        
        currentTab = e.target.dataset.tab;
        if (currentTab === 'all') {
          loadDashboard();
        } else {
          await loadByStatus(currentTab);
        }
      });
    });

    async function loadByStatus(status) {
      try {
        const response = await apiCall(`/dashboard/inventory/${status}`);
        const data = await response.json();
        renderInventoryTable(data.data);
      } catch (error) {
        console.error('Error loading filtered inventory:', error);
      }
    }

    async function exportProducts() {
      try {
        window.location.href = `${API_BASE}/export/products`;
      } catch (error) {
        alert('Export failed: ' + error.message);
      }
    }

    function viewProduct(id) { alert('View product: ' + id); }

    function showAddProductModal() {
      const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
      document.getElementById('addProductForm').reset();
      document.getElementById('formError').style.display = 'none';
      document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
      modal.show();
    }

    // Add Product Form Submission
    document.getElementById('addProductForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      const formData = new FormData(e.target);
      const data = {};

      formData.forEach((value, key) => {
        if (key === 'is_active') {
          data[key] = document.getElementById('productIsActive').checked;
        } else if (value !== '') {
          data[key] = value;
        }
      });

      // Clear previous errors
      document.getElementById('formError').style.display = 'none';
      document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

      try {
        const saveBtn = document.getElementById('saveProductBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

        const response = await apiCall('/products', {
          method: 'POST',
          body: JSON.stringify(data)
        });

        if (response.ok) {
          const modal = bootstrap.Modal.getInstance(document.getElementById('addProductModal'));
          modal.hide();
          showNotification('Product created successfully!', 'success');
          loadDashboard();
        } else {
          const error = await response.json();
          if (error.errors) {
            // Display field-specific errors
            Object.keys(error.errors).forEach(field => {
              const input = document.querySelector(`[name="${field}"]`);
              if (input) {
                input.classList.add('is-invalid');
                const feedback = input.parentElement.querySelector('.invalid-feedback') ||
                                input.closest('.mb-3').querySelector('.invalid-feedback');
                if (feedback) {
                  feedback.textContent = error.errors[field][0];
                  feedback.style.display = 'block';
                }
              }
            });
          } else {
            document.getElementById('formError').textContent = error.message || 'Failed to create product';
            document.getElementById('formError').style.display = 'block';
          }
        }
      } catch (error) {
        document.getElementById('formError').textContent = 'Error: ' + error.message;
        document.getElementById('formError').style.display = 'block';
      } finally {
        const saveBtn = document.getElementById('saveProductBtn');
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="ti ti-device-floppy icon"></i> Save Product';
      }
    });

    // Notification system
    function showNotification(message, type = 'info') {
      const toast = document.createElement('div');
      toast.className = `alert alert-${type} alert-dismissible position-fixed top-0 end-0 m-3`;
      toast.style.zIndex = '9999';
      toast.style.minWidth = '300px';
      toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      `;
      document.body.appendChild(toast);

      setTimeout(() => {
        toast.classList.add('fade');
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }

    // Theme Switcher
    const themeToggleLight = document.getElementById('theme-toggle-light');
    const themeToggleDark = document.getElementById('theme-toggle-dark');

    function setTheme(theme) {
      document.body.setAttribute('data-bs-theme', theme);
      localStorage.setItem('theme', theme);
    }

    function getTheme() {
      return localStorage.getItem('theme') || 'light';
    }

    // Initialize theme
    setTheme(getTheme());

    themeToggleLight.addEventListener('click', (e) => {
      e.preventDefault();
      setTheme('dark');
    });

    themeToggleDark.addEventListener('click', (e) => {
      e.preventDefault();
      setTheme('light');
    });

    if (authToken) {
      showApp();
      loadDashboard();
    } else {
      showLogin();
    }
  </script>
</body>
</html>