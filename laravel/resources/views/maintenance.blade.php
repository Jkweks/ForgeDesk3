<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ForgeDesk - Maintenance Hub</title>
  <link href="{{ asset('assets/tabler/css/tabler.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-flags.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-vendors.min.css') }}" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" rel="stylesheet">
  <style>
    @import url("https://rsms.me/inter/inter.css");
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
    .modal-body .form-label.required:after {
      content: " *";
      color: #d63939;
    }
    .priority-critical { background-color: #d63939; color: white; }
    .priority-high { background-color: #f76707; color: white; }
    .priority-medium { background-color: #fab005; color: white; }
    .priority-low { background-color: #74b816; color: white; }
    .overdue { background-color: #d63939; color: white; }
    .due-soon { background-color: #fab005; color: white; }
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
    <header class="navbar navbar-expand-md d-print-none">
      <div class="container-xl">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-menu">
          <span class="navbar-toggler-icon"></span>
        </button>
        <h1 class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3">
          <a href="/">
            <svg xmlns="http://www.w3.org/2000/svg" width="110" height="32" viewBox="0 0 232 68" class="navbar-brand-image">
              <text x="10" y="50" font-family="Arial, sans-serif" font-size="48" font-weight="bold" fill="var(--tblr-primary, #066fd1)">FD</text>
            </svg>
          </a>
        </h1>
        <div class="navbar-nav flex-row order-md-last">
          <div class="nav-item dropdown">
            <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown">
              <span class="avatar avatar-sm">A</span>
            </a>
            <div class="dropdown-menu dropdown-menu-end">
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
                    <li class="nav-item">
                      <a class="nav-link" href="/">
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
                    <li class="nav-item dropdown active">
                      <a class="nav-link dropdown-toggle" href="#navbar-maintenance" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                        <span class="nav-link-icon d-md-none d-lg-inline-block">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 10h3v-3l-3.5 -3.5a6 6 0 0 1 8 8l6 6a2 2 0 0 1 -3 3l-6 -6a6 6 0 0 1 -8 -8l3.5 3.5" /></svg>
                        </span>
                        <span class="nav-link-title">Maintenance</span>
                      </a>
                      <div class="dropdown-menu">
                        <a class="dropdown-item" href="/maintenance">Maintenance Hub</a>
                        <a class="dropdown-item" href="/maintenance#tab-machines">Machines</a>
                        <a class="dropdown-item" href="/maintenance#tab-tasks">Tasks</a>
                        <a class="dropdown-item" href="/maintenance#tab-records">Service Log</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/maintenance#tab-assets">Assets</a>
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
              <h2 class="page-title">Maintenance Hub</h2>
            </div>
          </div>
        </div>
      </div>

      <!-- Dashboard Stats -->
      <div class="page-body">
        <div class="container-xl">
          <div class="row row-deck row-cards mb-3">
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Machines</div>
                  </div>
                  <div class="h1 mb-0" id="dashMachineCount">0</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Active Tasks</div>
                  </div>
                  <div class="h1 mb-0" id="dashActiveTaskCount">0</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Overdue Tasks</div>
                  </div>
                  <div class="h1 mb-0 text-danger" id="dashOverdueCount">0</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex align-items-center">
                    <div class="subheader">Total Downtime</div>
                  </div>
                  <div class="h1 mb-0" id="dashTotalDowntime">0h</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tabs -->
          <div class="card">
            <div class="card-header">
              <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                  <a href="#tab-machines" class="nav-link active" data-bs-toggle="tab" role="tab">Machines</a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#tab-assets" class="nav-link" data-bs-toggle="tab" role="tab">Assets</a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#tab-tasks" class="nav-link" data-bs-toggle="tab" role="tab">Tasks</a>
                </li>
                <li class="nav-item" role="presentation">
                  <a href="#tab-records" class="nav-link" data-bs-toggle="tab" role="tab">Service Log</a>
                </li>
              </ul>
            </div>
            <div class="card-body">
              <div class="tab-content">
                <!-- Machines Tab -->
                <div class="tab-pane active" id="tab-machines" role="tabpanel">
                  <div class="d-flex mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#machineModal" onclick="openMachineModal()">
                      <i class="ti ti-plus icon"></i> Add Machine
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter">
                      <thead>
                        <tr>
                          <th>Name</th>
                          <th>Type</th>
                          <th>Manufacturer</th>
                          <th>Model</th>
                          <th>Location</th>
                          <th>Tasks</th>
                          <th>Last Service</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="machinesTable"></tbody>
                    </table>
                  </div>
                </div>

                <!-- Assets Tab -->
                <div class="tab-pane" id="tab-assets" role="tabpanel">
                  <div class="d-flex mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assetModal" onclick="openAssetModal()">
                      <i class="ti ti-plus icon"></i> Add Asset
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter">
                      <thead>
                        <tr>
                          <th>Name</th>
                          <th>Description</th>
                          <th>Machines</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="assetsTable"></tbody>
                    </table>
                  </div>
                </div>

                <!-- Tasks Tab -->
                <div class="tab-pane" id="tab-tasks" role="tabpanel">
                  <div class="d-flex mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal" onclick="openTaskModal()">
                      <i class="ti ti-plus icon"></i> Add Task
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter">
                      <thead>
                        <tr>
                          <th>Machine</th>
                          <th>Title</th>
                          <th>Priority</th>
                          <th>Frequency</th>
                          <th>Next Due</th>
                          <th>Status</th>
                          <th>Assigned To</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="tasksTable"></tbody>
                    </table>
                  </div>
                </div>

                <!-- Service Log Tab -->
                <div class="tab-pane" id="tab-records" role="tabpanel">
                  <div class="d-flex mb-3">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordModal" onclick="openRecordModal()">
                      <i class="ti ti-plus icon"></i> Add Service Record
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-vcenter">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Machine</th>
                          <th>Task</th>
                          <th>Performed By</th>
                          <th>Downtime</th>
                          <th>Labor Hours</th>
                          <th>Notes</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody id="recordsTable"></tbody>
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

  <!-- Machine Modal -->
  <div class="modal fade" id="machineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="machineModalTitle">Add Machine</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="machineForm">
          <div class="modal-body">
            <input type="hidden" id="machineId">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">Name</label>
                <input type="text" class="form-control" id="machineName" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Equipment Type</label>
                <input type="text" class="form-control" id="machineEquipmentType" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Manufacturer</label>
                <input type="text" class="form-control" id="machineManufacturer">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Model</label>
                <input type="text" class="form-control" id="machineModel">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Serial Number</label>
                <input type="text" class="form-control" id="machineSerialNumber">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Location</label>
                <input type="text" class="form-control" id="machineLocation">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="machineNotes" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Asset Modal -->
  <div class="modal fade" id="assetModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="assetModalTitle">Add Asset</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="assetForm">
          <div class="modal-body">
            <input type="hidden" id="assetId">
            <div class="mb-3">
              <label class="form-label required">Name</label>
              <input type="text" class="form-control" id="assetName" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="assetDescription" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Compatible Machines</label>
              <div id="assetMachinesList"></div>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="assetNotes" rows="2"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Task Modal -->
  <div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="taskModalTitle">Add Maintenance Task</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="taskForm">
          <div class="modal-body">
            <input type="hidden" id="taskId">
            <div class="mb-3">
              <label class="form-label required">Machine</label>
              <select class="form-select" id="taskMachineId" required></select>
            </div>
            <div class="mb-3">
              <label class="form-label required">Title</label>
              <input type="text" class="form-control" id="taskTitle" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="taskDescription" rows="2"></textarea>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Frequency</label>
                <input type="text" class="form-control" id="taskFrequency" placeholder="e.g., Weekly, Monthly">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Assigned To</label>
                <input type="text" class="form-control" id="taskAssignedTo">
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Interval Count</label>
                <input type="number" class="form-control" id="taskIntervalCount" min="1">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Interval Unit</label>
                <select class="form-select" id="taskIntervalUnit">
                  <option value="">Select...</option>
                  <option value="day">Day(s)</option>
                  <option value="week">Week(s)</option>
                  <option value="month">Month(s)</option>
                  <option value="year">Year(s)</option>
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" id="taskStartDate">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Priority</label>
                <select class="form-select" id="taskPriority">
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High</option>
                  <option value="critical">Critical</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Status</label>
                <select class="form-select" id="taskStatus">
                  <option value="active" selected>Active</option>
                  <option value="paused">Paused</option>
                  <option value="retired">Retired</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Service Record Modal -->
  <div class="modal fade" id="recordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="recordModalTitle">Add Service Record</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="recordForm">
          <div class="modal-body">
            <input type="hidden" id="recordId">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">Machine</label>
                <select class="form-select" id="recordMachineId" required></select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Related Task</label>
                <select class="form-select" id="recordTaskId">
                  <option value="">Unplanned Maintenance</option>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Asset Used</label>
                <select class="form-select" id="recordAssetId">
                  <option value="">None</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Performed By</label>
                <input type="text" class="form-control" id="recordPerformedBy">
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">Date Performed</label>
                <input type="date" class="form-control" id="recordPerformedAt">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Downtime (minutes)</label>
                <input type="number" class="form-control" id="recordDowntimeMinutes" min="0">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Labor Hours</label>
                <input type="number" class="form-control" id="recordLaborHours" step="0.25" min="0">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Parts Used (one per line)</label>
              <textarea class="form-control" id="recordPartsUsed" rows="3" placeholder="Part A&#10;Part B&#10;Part C"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea class="form-control" id="recordNotes" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="{{ asset('assets/tabler/js/tabler.min.js') }}"></script>
  <script src="/maintenance.js"></script>
</body>
</html>
