@extends('layouts.app')

@section('title', 'Admin Panel - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">System Administration</div>
              <h1 class="page-title">Admin Panel</h1>
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
                  <div class="subheader">Total Users</div>
                  <div class="h1 mb-3" id="statTotalUsers">-</div>
                  <div>All user accounts</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Active Users</div>
                  <div class="h1 mb-3" id="statActiveUsers">-</div>
                  <div>Currently active</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Roles</div>
                  <div class="h1 mb-3" id="statTotalRoles">-</div>
                  <div>Permission groups</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Admin Users</div>
                  <div class="h1 mb-3" id="statAdminUsers">-</div>
                  <div>Users with admin access</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Navigation Tabs -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <ul class="nav nav-tabs card-header-tabs" data-bs-toggle="tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                      <a href="#tab-users" class="nav-link active" data-bs-toggle="tab" aria-selected="true" role="tab">
                        <i class="ti ti-users me-2"></i>User Management
                      </a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a href="#tab-permissions" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1">
                        <i class="ti ti-shield-lock me-2"></i>Permissions & Roles
                      </a>
                    </li>
                    <li class="nav-item" role="presentation">
                      <a href="#tab-settings" class="nav-link" data-bs-toggle="tab" aria-selected="false" role="tab" tabindex="-1">
                        <i class="ti ti-settings me-2"></i>System Settings
                      </a>
                    </li>
                  </ul>
                </div>

                <div class="card-body">
                  <div class="tab-content">
                    <!-- User Management Tab -->
                    <div class="tab-pane active show" id="tab-users" role="tabpanel">
                      <div class="mb-3 d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Users</h3>
                        <button class="btn btn-primary" onclick="showAddUserModal()">
                          <i class="ti ti-plus me-1"></i>Add User
                        </button>
                      </div>

                      <div class="row mb-3">
                        <div class="col-md-3">
                          <select class="form-select" id="filterRole">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="fabricator">Fabricator</option>
                            <option value="viewer">Viewer</option>
                          </select>
                        </div>
                        <div class="col-md-3">
                          <select class="form-select" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                          </select>
                        </div>
                        <div class="col-md-6">
                          <input type="text" class="form-control" placeholder="Search users..." id="searchUsers">
                        </div>
                      </div>

                      <div class="loading" id="loadingUsers">
                        <div class="text-muted">Loading users...</div>
                      </div>

                      <div class="table-responsive" id="usersTableContainer" style="display: none;">
                        <table class="table table-vcenter card-table table-striped">
                          <thead>
                            <tr>
                              <th>Name</th>
                              <th>Email</th>
                              <th>Role</th>
                              <th>Status</th>
                              <th>Last Login</th>
                              <th>Created</th>
                              <th class="w-1">Actions</th>
                            </tr>
                          </thead>
                          <tbody id="usersTableBody">
                            <!-- Populated by JavaScript -->
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <!-- Permissions & Roles Tab -->
                    <div class="tab-pane" id="tab-permissions" role="tabpanel">
                      <div class="mb-3 d-flex justify-content-between align-items-center">
                        <h3 class="mb-0">Roles & Permissions</h3>
                        <button class="btn btn-primary" onclick="showAddRoleModal()">
                          <i class="ti ti-plus me-1"></i>Add Role
                        </button>
                      </div>

                      <div class="loading" id="loadingRoles">
                        <div class="text-muted">Loading roles...</div>
                      </div>

                      <div class="row row-cards" id="rolesContainer" style="display: none;">
                        <!-- Populated by JavaScript -->
                      </div>
                    </div>

                    <!-- System Settings Tab -->
                    <div class="tab-pane" id="tab-settings" role="tabpanel">
                      <h3 class="mb-4">System Settings</h3>

                      <div class="row">
                        <div class="col-md-6">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title">Application Settings</h4>
                            </div>
                            <div class="card-body">
                              <div class="mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" class="form-control" value="ForgeDesk" placeholder="Company name">
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Default Currency</label>
                                <select class="form-select">
                                  <option value="USD" selected>USD ($)</option>
                                  <option value="EUR">EUR (€)</option>
                                  <option value="GBP">GBP (£)</option>
                                </select>
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Timezone</label>
                                <select class="form-select">
                                  <option value="America/New_York" selected>Eastern Time (ET)</option>
                                  <option value="America/Chicago">Central Time (CT)</option>
                                  <option value="America/Denver">Mountain Time (MT)</option>
                                  <option value="America/Los_Angeles">Pacific Time (PT)</option>
                                </select>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="col-md-6">
                          <div class="card">
                            <div class="card-header">
                              <h4 class="card-title">Security Settings</h4>
                            </div>
                            <div class="card-body">
                              <div class="mb-3">
                                <label class="form-label">Session Timeout (minutes)</label>
                                <input type="number" class="form-control" value="120" placeholder="Minutes">
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Password Requirements</label>
                                <div class="form-selectgroup">
                                  <label class="form-selectgroup-item">
                                    <input type="checkbox" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label">Minimum 8 characters</span>
                                  </label>
                                  <label class="form-selectgroup-item">
                                    <input type="checkbox" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label">Require uppercase</span>
                                  </label>
                                  <label class="form-selectgroup-item">
                                    <input type="checkbox" class="form-selectgroup-input" checked>
                                    <span class="form-selectgroup-label">Require numbers</span>
                                  </label>
                                  <label class="form-selectgroup-item">
                                    <input type="checkbox" class="form-selectgroup-input">
                                    <span class="form-selectgroup-label">Require special characters</span>
                                  </label>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="row mt-3">
                        <div class="col-12">
                          <button class="btn btn-primary">Save Settings</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Add User Modal -->
    <div class="modal modal-blur fade" id="addUserModal" tabindex="-1" style="display: none;" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add New User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">First Name</label>
                <input type="text" class="form-control" id="addUserFirstName" placeholder="First name">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Last Name</label>
                <input type="text" class="form-control" id="addUserLastName" placeholder="Last name">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label required">Email</label>
              <input type="email" class="form-control" id="addUserEmail" placeholder="user@example.com">
            </div>
            <div class="mb-3">
              <label class="form-label required">Password</label>
              <input type="password" class="form-control" id="addUserPassword" placeholder="Password" autocomplete="new-password">
            </div>
            <div class="mb-3">
              <label class="form-label required">Role</label>
              <select class="form-select" id="addUserRole">
                <option value="">Select role...</option>
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="fabricator">Fabricator</option>
                <option value="viewer">Viewer</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" id="addUserActive" checked>
                <span class="form-check-label">Active</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveNewUser()">Create User</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal modal-blur fade" id="editUserModal" tabindex="-1" style="display: none;" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="editUserId">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label required">First Name</label>
                <input type="text" class="form-control" id="editUserFirstName">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label required">Last Name</label>
                <input type="text" class="form-control" id="editUserLastName">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label required">Email</label>
              <input type="email" class="form-control" id="editUserEmail">
            </div>
            <div class="mb-3">
              <label class="form-label">New Password (leave blank to keep current)</label>
              <input type="password" class="form-control" id="editUserPassword" placeholder="New password" autocomplete="new-password">
            </div>
            <div class="mb-3">
              <label class="form-label required">Role</label>
              <select class="form-select" id="editUserRole">
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="fabricator">Fabricator</option>
                <option value="viewer">Viewer</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" id="editUserActive">
                <span class="form-check-label">Active</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveEditUser()">Save Changes</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Role Modal -->
    <div class="modal modal-blur fade" id="addRoleModal" tabindex="-1" style="display: none;" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add New Role</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Role Name</label>
              <input type="text" class="form-control" id="addRoleName" placeholder="Role name">
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="addRoleDescription" rows="3" placeholder="Role description"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Permissions</label>
              <div class="row">
                <div class="col-md-6">
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">View Products</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">Manage Products</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">View Pricing</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">Manage Pricing</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">View Inventory</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">Manage Inventory</span>
                  </label>
                </div>
                <div class="col-md-6">
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">View Purchase Orders</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">Create Purchase Orders</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">Approve Purchase Orders</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">View Reports</span>
                  </label>
                  <label class="form-check">
                    <input class="form-check-input" type="checkbox">
                    <span class="form-check-label">Admin Access</span>
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn me-auto" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveNewRole()">Create Role</button>
          </div>
        </div>
      </div>
    </div>

    <script>
      // Placeholder data for demonstration
      let users = [];
      let roles = [];

      // Bootstrap Modal helpers
      function showModal(element) {
        const modal = new bootstrap.Modal(element);
        modal.show();
      }

      function hideModal(element) {
        const modal = bootstrap.Modal.getInstance(element);
        if (modal) modal.hide();
      }

      // Notification helper
      function showNotification(message, type = 'success') {
        // TODO: Implement notification system
        console.log(`[${type}] ${message}`);
        alert(message);
      }

      // User Management Functions
      function showAddUserModal() {
        document.getElementById('addUserFirstName').value = '';
        document.getElementById('addUserLastName').value = '';
        document.getElementById('addUserEmail').value = '';
        document.getElementById('addUserPassword').value = '';
        document.getElementById('addUserRole').value = '';
        document.getElementById('addUserActive').checked = true;
        showModal(document.getElementById('addUserModal'));
      }

      function saveNewUser() {
        const firstName = document.getElementById('addUserFirstName').value;
        const lastName = document.getElementById('addUserLastName').value;
        const email = document.getElementById('addUserEmail').value;
        const password = document.getElementById('addUserPassword').value;
        const role = document.getElementById('addUserRole').value;
        const active = document.getElementById('addUserActive').checked;

        if (!firstName || !lastName || !email || !password || !role) {
          showNotification('Please fill in all required fields', 'danger');
          return;
        }

        // TODO: Implement API call to create user
        showNotification('User created successfully (placeholder)', 'success');
        hideModal(document.getElementById('addUserModal'));
        loadUsers();
      }

      function editUser(userId) {
        // TODO: Load user data and show edit modal
        showNotification('Edit user functionality (placeholder)', 'info');
      }

      function deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user?')) return;

        // TODO: Implement API call to delete user
        showNotification('User deleted (placeholder)', 'success');
        loadUsers();
      }

      function saveEditUser() {
        // TODO: Implement API call to update user
        showNotification('User updated successfully (placeholder)', 'success');
        hideModal(document.getElementById('editUserModal'));
        loadUsers();
      }

      // Role Management Functions
      function showAddRoleModal() {
        document.getElementById('addRoleName').value = '';
        document.getElementById('addRoleDescription').value = '';
        showModal(document.getElementById('addRoleModal'));
      }

      function saveNewRole() {
        const name = document.getElementById('addRoleName').value;
        const description = document.getElementById('addRoleDescription').value;

        if (!name) {
          showNotification('Please enter a role name', 'danger');
          return;
        }

        // TODO: Implement API call to create role
        showNotification('Role created successfully (placeholder)', 'success');
        hideModal(document.getElementById('addRoleModal'));
        loadRoles();
      }

      function editRole(roleId) {
        // TODO: Load role data and show edit modal
        showNotification('Edit role functionality (placeholder)', 'info');
      }

      function deleteRole(roleId) {
        if (!confirm('Are you sure you want to delete this role?')) return;

        // TODO: Implement API call to delete role
        showNotification('Role deleted (placeholder)', 'success');
        loadRoles();
      }

      // Data Loading Functions
      function loadUsers() {
        document.getElementById('loadingUsers').style.display = 'block';
        document.getElementById('usersTableContainer').style.display = 'none';

        // TODO: Replace with actual API call
        setTimeout(() => {
          // Placeholder data
          users = [
            { id: 1, name: 'John Doe', email: 'john@example.com', role: 'admin', status: 'active', last_login: '2026-01-31', created_at: '2026-01-01' },
            { id: 2, name: 'Jane Smith', email: 'jane@example.com', role: 'manager', status: 'active', last_login: '2026-01-30', created_at: '2026-01-05' },
            { id: 3, name: 'Bob Johnson', email: 'bob@example.com', role: 'fabricator', status: 'active', last_login: '2026-01-29', created_at: '2026-01-10' },
          ];

          renderUsers();
          document.getElementById('loadingUsers').style.display = 'none';
          document.getElementById('usersTableContainer').style.display = 'block';
        }, 500);
      }

      function renderUsers() {
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = '';

        users.forEach(user => {
          const statusBadge = user.status === 'active'
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';

          const roleBadge = {
            admin: '<span class="badge bg-red">Admin</span>',
            manager: '<span class="badge bg-blue">Manager</span>',
            fabricator: '<span class="badge bg-green">Fabricator</span>',
            viewer: '<span class="badge bg-gray">Viewer</span>'
          }[user.role] || '<span class="badge bg-gray">' + user.role + '</span>';

          const row = `
            <tr>
              <td>${user.name}</td>
              <td>${user.email}</td>
              <td>${roleBadge}</td>
              <td>${statusBadge}</td>
              <td>${user.last_login || 'Never'}</td>
              <td>${user.created_at}</td>
              <td>
                <button class="btn btn-sm btn-icon btn-ghost-secondary" onclick="editUser(${user.id})" title="Edit">
                  <i class="ti ti-edit"></i>
                </button>
                <button class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteUser(${user.id})" title="Delete">
                  <i class="ti ti-trash"></i>
                </button>
              </td>
            </tr>
          `;
          tbody.innerHTML += row;
        });

        // Update stats
        document.getElementById('statTotalUsers').textContent = users.length;
        document.getElementById('statActiveUsers').textContent = users.filter(u => u.status === 'active').length;
        document.getElementById('statAdminUsers').textContent = users.filter(u => u.role === 'admin').length;
      }

      function loadRoles() {
        document.getElementById('loadingRoles').style.display = 'block';
        document.getElementById('rolesContainer').style.display = 'none';

        // TODO: Replace with actual API call
        setTimeout(() => {
          // Placeholder data
          roles = [
            { id: 1, name: 'Admin', description: 'Full system access', user_count: 2 },
            { id: 2, name: 'Manager', description: 'Manage inventory and orders', user_count: 5 },
            { id: 3, name: 'Fabricator', description: 'View and fulfill orders', user_count: 10 },
            { id: 4, name: 'Viewer', description: 'Read-only access', user_count: 3 },
          ];

          renderRoles();
          document.getElementById('loadingRoles').style.display = 'none';
          document.getElementById('rolesContainer').style.display = 'flex';
        }, 500);
      }

      function renderRoles() {
        const container = document.getElementById('rolesContainer');
        container.innerHTML = '';

        roles.forEach(role => {
          const card = `
            <div class="col-md-6 col-lg-4">
              <div class="card">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                      <h3 class="card-title mb-1">${role.name}</h3>
                      <div class="text-muted">${role.description}</div>
                    </div>
                    <div class="dropdown">
                      <button class="btn btn-icon btn-sm" type="button" data-bs-toggle="dropdown">
                        <i class="ti ti-dots-vertical"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="#" onclick="editRole(${role.id}); return false;">
                          <i class="ti ti-edit me-2"></i>Edit
                        </a>
                        <a class="dropdown-item text-danger" href="#" onclick="deleteRole(${role.id}); return false;">
                          <i class="ti ti-trash me-2"></i>Delete
                        </a>
                      </div>
                    </div>
                  </div>
                  <div class="mt-3">
                    <div class="d-flex align-items-center">
                      <i class="ti ti-users me-2 text-muted"></i>
                      <span class="text-muted">${role.user_count} user${role.user_count !== 1 ? 's' : ''}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `;
          container.innerHTML += card;
        });

        // Update stats
        document.getElementById('statTotalRoles').textContent = roles.length;
      }

      // Initialize on page load
      document.addEventListener('DOMContentLoaded', function() {
        loadUsers();
        loadRoles();
      });

      // Filter handlers
      document.getElementById('filterRole')?.addEventListener('change', loadUsers);
      document.getElementById('filterStatus')?.addEventListener('change', loadUsers);
      document.getElementById('searchUsers')?.addEventListener('input', loadUsers);
    </script>

    <style>
      .form-check {
        margin-bottom: 0.5rem;
      }

      .loading {
        padding: 2rem;
        text-align: center;
      }
    </style>
@endsection
