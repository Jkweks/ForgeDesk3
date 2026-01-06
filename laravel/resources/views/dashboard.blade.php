<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ForgeDesk - Inventory Management</title>
  <link href="{{ asset('assets/tabler/css/tabler.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-flags.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-socials.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-payments.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-vendors.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-marketing.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-themes.min.css') }}" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" rel="stylesheet">
  <style>
    @import url("https://rsms.me/inter/inter.css");
  </style>
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
  <script src="{{ asset('assets/tabler/js/tabler-theme.min.js') }}"></script>
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
    <a href="#content" class="visually-hidden skip-link">Skip to main content</a>
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu" aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation">
          <span class="navbar-toggler-icon"></span>
        </button>
        <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
          <a href="#">
            <svg xmlns="http://www.w3.org/2000/svg" width="110" height="32" viewBox="0 0 232 68" class="navbar-brand-image">
              <text x="10" y="50" font-family="Arial, sans-serif" font-size="48" font-weight="bold" fill="var(--tblr-primary, #066fd1)">FD</text>
            </svg>
          </a>
        </h1>
        <div class="navbar-nav flex-row order-md-last">
          <div class="nav-item dropdown d-none d-md-flex me-3">
            <a href="#" class="nav-link px-0" data-bs-toggle="dropdown" tabindex="-1" aria-label="Show notifications">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 5a2 2 0 1 1 4 0a7 7 0 0 1 4 6v3a4 4 0 0 0 2 3h-16a4 4 0 0 0 2 -3v-3a7 7 0 0 1 4 -6" /><path d="M9 17v1a3 3 0 0 0 6 0v-1" /></svg>
              <span class="badge bg-red"></span>
            </a>
            <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-end dropdown-menu-card">
              <div class="card">
                <div class="card-header d-flex">
                  <h3 class="card-title">Notifications</h3>
                  <div class="btn-close ms-auto" data-bs-dismiss="dropdown"></div>
                </div>
                <div class="list-group list-group-flush list-group-hoverable">
                  <div class="list-group-item">
                    <div class="row align-items-center">
                      <div class="col text-truncate">
                        <div class="text-muted">No new notifications</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="nav-item d-none d-md-flex me-3">
            <a href="#" class="nav-link px-0" title="Theme settings" data-bs-toggle="offcanvas" data-bs-target="#offcanvasTheme" aria-controls="offcanvasTheme">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 21a9 9 0 0 1 0 -18c4.97 0 9 3.582 9 8c0 1.06 -.474 2.078 -1.318 2.828c-.844 .75 -1.989 1.172 -3.182 1.172h-2.5a2 2 0 0 0 -1 3.75a1.3 1.3 0 0 1 -1 2.25" /><path d="M8.5 10.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M12.5 7.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M16.5 10.5m-1 0a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /></svg>
            </a>
          </div>
          <div class="nav-item dropdown">
            <a href="#" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" aria-label="Open user menu">
              <span class="avatar avatar-sm" id="userAvatar">A</span>
              <div class="d-none d-xl-block ps-2">
                <div id="userName">Admin</div>
                <div class="mt-1 small text-secondary" id="userEmail">admin@forgedesk.local</div>
              </div>
            </a>
            <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
              <a href="#" class="dropdown-item">Status</a>
              <a href="#" class="dropdown-item">Profile</a>
              <div class="dropdown-divider"></div>
              <a href="#" class="dropdown-item">Settings</a>
              <a href="#" class="dropdown-item" id="logoutBtn">Logout</a>
            </div>
          </div>
        </div>
      </div>
    </header>
    <div class="navbar-expand-md">
      <div class="collapse navbar-collapse" id="navbar-menu">
        <div class="navbar">
          <div class="container-xl">
            <div class="row flex-column flex-md-row flex-fill align-items-center">
              <div class="col">
                <nav aria-label="Primary">
                  <ul class="navbar-nav">
                    <li class="nav-item active">
                      <a class="nav-link" href="#" aria-current="page">
                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" /><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" /></svg>
                        </span>
                        <span class="nav-link-title">Dashboard</span>
                      </a>
                    </li>
                    <li class="nav-item dropdown">
                      <a class="nav-link dropdown-toggle" href="#navbar-inventory" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /></svg>
                        </span>
                        <span class="nav-link-title">Inventory</span>
                      </a>
                      <div class="dropdown-menu">
                        <a class="dropdown-item" href="#">All Products</a>
                        <a class="dropdown-item" href="#">Low Stock</a>
                        <a class="dropdown-item" href="#">Critical Stock</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#">Categories</a>
                        <a class="dropdown-item" href="#">Suppliers</a>
                      </div>
                    </li>
                  </ul>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Overview</div>
              <h1 class="page-title">Inventory Dashboard</h1>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <span class="d-none d-sm-inline">
                  <button class="btn" onclick="exportProducts()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                    Export
                  </button>
                </span>
                <button class="btn btn-primary d-none d-sm-inline-block" onclick="showAddProductModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Product
                </button>
                <button class="btn btn-primary d-sm-none btn-icon" onclick="showAddProductModal()" aria-label="Add product">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
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
      </main>
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

  <!-- Theme Settings Offcanvas -->
  <form class="offcanvas offcanvas-start offcanvas-narrow" tabindex="-1" id="offcanvasTheme" role="dialog" aria-modal="true" aria-labelledby="offcanvasThemeLabel">
    <div class="offcanvas-header">
      <h2 class="offcanvas-title" id="offcanvasThemeLabel">Theme Settings</h2>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
      <div>
        <div class="mb-4">
          <label class="form-label">Color mode</label>
          <p class="form-hint">Choose the color mode for your app.</p>
          <label class="form-check">
            <div class="form-selectgroup-item">
              <input type="radio" name="theme" value="light" class="form-check-input" checked />
              <div class="form-check-label">Light</div>
            </div>
          </label>
          <label class="form-check">
            <div class="form-selectgroup-item">
              <input type="radio" name="theme" value="dark" class="form-check-input" />
              <div class="form-check-label">Dark</div>
            </div>
          </label>
        </div>
        <div class="mb-4">
          <label class="form-label">Color scheme</label>
          <p class="form-hint">The perfect color mode for your app.</p>
          <div class="row g-2">
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="blue" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-blue"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="azure" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-azure"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="indigo" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-indigo"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="purple" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-purple"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="pink" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-pink"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="red" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-red"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="orange" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-orange"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="yellow" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-yellow"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="lime" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-lime"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="green" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-green"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="teal" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-teal"></span>
              </label>
            </div>
            <div class="col-auto">
              <label class="form-colorinput">
                <input name="theme-primary" type="radio" value="cyan" class="form-colorinput-input" />
                <span class="form-colorinput-color bg-cyan"></span>
              </label>
            </div>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Font family</label>
          <p class="form-hint">Choose the font family that fits your app.</p>
          <div>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-font" value="sans-serif" class="form-check-input" checked />
                <div class="form-check-label">Sans-serif</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-font" value="serif" class="form-check-input" />
                <div class="form-check-label">Serif</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-font" value="monospace" class="form-check-input" />
                <div class="form-check-label">Monospace</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-font" value="comic" class="form-check-input" />
                <div class="form-check-label">Comic</div>
              </div>
            </label>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Theme base</label>
          <p class="form-hint">Choose the gray shade for your app.</p>
          <div>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="slate" class="form-check-input" />
                <div class="form-check-label">Slate</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="gray" class="form-check-input" checked />
                <div class="form-check-label">Gray</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="zinc" class="form-check-input" />
                <div class="form-check-label">Zinc</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="neutral" class="form-check-input" />
                <div class="form-check-label">Neutral</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-base" value="stone" class="form-check-input" />
                <div class="form-check-label">Stone</div>
              </div>
            </label>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label">Corner Radius</label>
          <p class="form-hint">Choose the border radius factor for your app.</p>
          <div>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="0" class="form-check-input" />
                <div class="form-check-label">0</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="0.5" class="form-check-input" />
                <div class="form-check-label">0.5</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="1" class="form-check-input" checked />
                <div class="form-check-label">1</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="1.5" class="form-check-input" />
                <div class="form-check-label">1.5</div>
              </div>
            </label>
            <label class="form-check">
              <div class="form-selectgroup-item">
                <input type="radio" name="theme-radius" value="2" class="form-check-input" />
                <div class="form-check-label">2</div>
              </div>
            </label>
          </div>
        </div>
      </div>
      <div class="mt-auto space-y">
        <button type="button" class="btn w-100" id="resetThemeBtn">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M19.95 11a8 8 0 1 0 -.5 4m.5 5v-5h-5" /></svg>
          Reset changes
        </button>
        <a href="#" class="btn btn-primary w-100" data-bs-dismiss="offcanvas">Save</a>
      </div>
    </div>
  </form>

  <script src="{{ asset('assets/tabler/js/tabler.min.js') }}"></script>
  <script src="{{ asset('assets/tabler/js/tabler-theme.min.js') }}"></script>
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

    // Theme settings from Tabler demo
    var themeConfig = {
      'theme': 'light',
      'theme-base': 'gray',
      'theme-font': 'sans-serif',
      'theme-primary': 'blue',
      'theme-radius': '1'
    };
    var form = document.getElementById('offcanvasTheme');
    var resetButton = document.getElementById('resetThemeBtn');

    var checkItems = function() {
      for (var key in themeConfig) {
        var value = window.localStorage['tabler-' + key] || themeConfig[key];
        if (!!value) {
          var radios = form.querySelectorAll(`[name="${key}"]`);
          if (!!radios) {
            radios.forEach((radio) => {
              radio.checked = radio.value === value;
            });
          }
        }
      }
    };

    form.addEventListener('change', function(event) {
      var target = event.target;
      var name = target.name;
      var value = target.value;
      for (var key in themeConfig) {
        if (name === key) {
          document.documentElement.setAttribute('data-bs-' + key, value);
          window.localStorage.setItem('tabler-' + key, value);
        }
      }
    });

    resetButton.addEventListener('click', function() {
      for (var key in themeConfig) {
        var value = themeConfig[key];
        document.documentElement.removeAttribute('data-bs-' + key);
        window.localStorage.removeItem('tabler-' + key);
      }
      checkItems();
      showNotification('Theme reset to defaults', 'info');
    });

    checkItems();

    if (authToken) {
      showApp();
      loadDashboard();
    } else {
      showLogin();
    }
  </script>
</body>
</html>