@extends('layouts.app')

@section('title', 'Storage Locations - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Operations Management</div>
              <h1 class="page-title">Storage Locations</h1>
              <p class="text-muted">Manage warehouse locations and inventory distribution</p>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn" onclick="exportLocations()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                  Export
                </button>
                <button class="btn btn-primary d-none d-sm-inline-block" onclick="showAddLocationModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Location
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
                  <div class="subheader">Total Locations</div>
                  <div class="h1 mb-3" id="statTotalLocations">-</div>
                  <div>Active storage locations</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Locations in Use</div>
                  <div class="h1 mb-3 text-success" id="statInUse">-</div>
                  <div>With inventory assigned</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Total Capacity</div>
                  <div class="h1 mb-3" id="statTotalCapacity">-</div>
                  <div>Units across all locations</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Capacity Utilization</div>
                  <div class="h1 mb-3 text-info" id="statUtilization">-</div>
                  <div>Percentage in use</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Locations Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Storage Locations</h3>
                  <div class="ms-auto d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" placeholder="Search locations..." id="searchInput" style="min-width: 200px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="loadingIndicator">
                    <div class="spinner-border" role="status"></div>
                    <div>Loading locations...</div>
                  </div>

                  <div class="table-responsive" id="locationsTableContainer" style="display: none;">
                    <table class="table table-vcenter card-table table-striped">
                      <thead>
                        <tr>
                          <th>Location Name</th>
                          <th class="text-end">Products Stored</th>
                          <th class="text-end">Total Quantity</th>
                          <th class="text-end">Total Value</th>
                          <th>Status</th>
                          <th class="w-1"></th>
                        </tr>
                      </thead>
                      <tbody id="locationsTableBody"></tbody>
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

  <!-- Add/Edit Location Modal -->
  <div class="modal modal-blur fade" id="addLocationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="locationModalTitle">Add Storage Location</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="locationForm">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label required">Location Name</label>
              <input type="text" class="form-control" id="locationName" name="name" placeholder="e.g., Warehouse A - Bin 23" required>
              <small class="form-hint">Enter a unique name for this storage location</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="locationDescription" name="description" rows="2" placeholder="Optional description or notes about this location"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Location Type</label>
              <select class="form-select" id="locationType" name="type">
                <option value="warehouse">Warehouse</option>
                <option value="shelf">Shelf</option>
                <option value="bin">Bin</option>
                <option value="rack">Rack</option>
                <option value="zone">Zone</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="row">
              <div class="col-md-6">
                <label class="form-label">Aisle</label>
                <input type="text" class="form-control" id="locationAisle" name="aisle" placeholder="e.g., A1">
              </div>
              <div class="col-md-6">
                <label class="form-label">Bay</label>
                <input type="text" class="form-control" id="locationBay" name="bay" placeholder="e.g., 05">
              </div>
            </div>
            <div class="row mt-3">
              <div class="col-md-6">
                <label class="form-label">Level</label>
                <input type="text" class="form-control" id="locationLevel" name="level" placeholder="e.g., 2">
              </div>
              <div class="col-md-6">
                <label class="form-label">Position</label>
                <input type="text" class="form-control" id="locationPosition" name="position" placeholder="e.g., 03">
              </div>
            </div>
            <div class="mt-3">
              <label class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="locationActive" name="is_active" checked>
                <span class="form-check-label">Active Location</span>
              </label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-link" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Location</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Location Details Modal -->
  <div class="modal modal-blur fade" id="viewLocationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewLocationModalTitle">Location Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="locationDetailsView"></div>

          <hr class="my-4">

          <h5 class="mb-3">Products at this Location</h5>
          <div class="table-responsive">
            <table class="table table-vcenter">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Description</th>
                  <th class="text-end">Quantity</th>
                  <th class="text-end">Committed</th>
                  <th class="text-end">Available</th>
                  <th class="text-end">Value</th>
                  <th>Primary</th>
                </tr>
              </thead>
              <tbody id="locationProductsTableBody">
                <tr>
                  <td colspan="7" class="text-center text-muted">Loading...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-link" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

@endsection


@push('scripts')
  <script>
    let currentLocations = [];
    let editingLocationId = null;

    document.addEventListener('DOMContentLoaded', () => {
      loadLocations();

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', debounce(filterLocations, 300));

      // Form submission
      document.getElementById('locationForm').addEventListener('submit', handleLocationFormSubmit);
    });

    async function loadLocations() {
      try {
        currentLocations = await authenticatedFetch(`/storage-locations-stats`);

        // Calculate aggregated stats
        const totalLocations = currentLocations.length;
        const inUse = currentLocations.filter(l => l.stats.products_count > 0).length;
        const totalCapacity = currentLocations.reduce((sum, l) => sum + (l.stats.total_quantity || 0), 0);
        const utilization = totalLocations > 0 ? ((inUse / totalLocations) * 100).toFixed(1) : 0;

        document.getElementById('statTotalLocations').textContent = totalLocations.toLocaleString();
        document.getElementById('statInUse').textContent = inUse.toLocaleString();
        document.getElementById('statTotalCapacity').textContent = totalCapacity.toLocaleString();
        document.getElementById('statUtilization').textContent = `${utilization}%`;

        renderLocationsTable(currentLocations);

        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('locationsTableContainer').style.display = 'block';
      } catch (error) {
        console.error('Error loading locations:', error);
        showNotification('Failed to load locations', 'danger');
      }
    }

    function renderLocationsTable(locations) {
      const tbody = document.getElementById('locationsTableBody');
      tbody.innerHTML = '';

      if (locations.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5">No locations found</td></tr>';
        return;
      }

      // Sort by sort_order then name
      locations.sort((a, b) => {
        if (a.sort_order !== b.sort_order) return a.sort_order - b.sort_order;
        return a.name.localeCompare(b.name);
      });

      locations.forEach(location => {
        const statusBadge = location.stats.products_count > 0
          ? '<span class="badge text-bg-success">In Use</span>'
          : '<span class="badge text-bg-secondary">Empty</span>';

        const row = `
          <tr>
            <td>
              <div class="d-flex align-items-center">
                <span class="avatar avatar-sm me-2"><i class="ti ti-map-pin"></i></span>
                <div>
                  <strong>${location.name}</strong>
                  ${location.code ? `<br><small class="text-muted">${location.code}</small>` : ''}
                  ${location.full_address ? `<br><small class="text-muted">${location.full_address}</small>` : ''}
                </div>
              </div>
            </td>
            <td class="text-end">${location.stats.products_count.toLocaleString()}</td>
            <td class="text-end">${location.stats.total_quantity.toLocaleString()}</td>
            <td class="text-end">$${location.stats.total_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>${statusBadge}</td>
            <td class="table-actions">
              <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="viewLocationDetails(${location.id})" title="View">
                <i class="ti ti-eye"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-ghost-secondary" onclick="editLocation(${location.id})" title="Edit">
                <i class="ti ti-edit"></i>
              </button>
              <button class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteLocation(${location.id}, '${location.name.replace(/'/g, "\\'")}')" title="Delete">
                <i class="ti ti-trash"></i>
              </button>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });
    }

    function filterLocations() {
      const search = document.getElementById('searchInput').value.toLowerCase();

      let filteredLocations = currentLocations;

      if (search) {
        filteredLocations = currentLocations.filter(loc =>
          loc.name.toLowerCase().includes(search) ||
          (loc.code && loc.code.toLowerCase().includes(search)) ||
          (loc.description && loc.description.toLowerCase().includes(search))
        );
      }

      renderLocationsTable(filteredLocations);
    }

    async function viewLocationDetails(locationId) {
      try {
        const location = currentLocations.find(l => l.id === locationId);
        if (!location) return;

        document.getElementById('viewLocationModalTitle').textContent = `Location: ${location.name}`;

        document.getElementById('locationDetailsView').innerHTML = `
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label fw-bold">Location Name</label>
              <p><i class="ti ti-map-pin me-2"></i>${location.name}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Code</label>
              <p>${location.code || '-'}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Type</label>
              <p><span class="badge text-bg-azure">${location.type}</span></p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Status</label>
              <p>${location.is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>'}</p>
            </div>
          </div>
          ${location.full_address ? `
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Full Address</label>
              <p>${location.full_address}</p>
            </div>
          </div>
          ` : ''}
          ${location.description ? `
          <div class="row mb-3">
            <div class="col-md-12">
              <label class="form-label fw-bold">Description</label>
              <p>${location.description}</p>
            </div>
          </div>
          ` : ''}
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label fw-bold">Products Stored</label>
              <p>${location.stats.products_count.toLocaleString()}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Total Quantity</label>
              <p>${location.stats.total_quantity.toLocaleString()}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Available</label>
              <p class="text-success">${location.stats.total_available.toLocaleString()}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Total Value</label>
              <p>$${location.stats.total_value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
            </div>
          </div>
        `;

        // Render products table (would need to fetch inventory_locations for this location)
        const tbody = document.getElementById('locationProductsTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Loading products...</td></tr>';

        // Fetch detailed inventory for this location
        const inventoryLocs = await authenticatedFetch(`/locations?filter[location]=${encodeURIComponent(location.name)}`);

        if (!inventoryLocs || inventoryLocs.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No products at this location</td></tr>';
        } else {
          tbody.innerHTML = '';
          inventoryLocs.forEach(item => {
            const product = item.product;
            const available = parseFloat(item.quantity || 0) - parseFloat(item.quantity_committed || 0);
            const value = parseFloat(item.quantity || 0) * parseFloat(product?.unit_cost || 0);
            const isPrimary = item.is_primary ? '<i class="ti ti-check text-success"></i>' : '';

            const row = `
              <tr>
                <td><span class="text-muted">${product?.sku || '-'}</span></td>
                <td>${product?.description || '-'}</td>
                <td class="text-end">${parseFloat(item.quantity || 0).toLocaleString()}</td>
                <td class="text-end">${parseFloat(item.quantity_committed || 0).toLocaleString()}</td>
                <td class="text-end text-success">${available.toLocaleString()}</td>
                <td class="text-end">$${value.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td class="text-center">${isPrimary}</td>
              </tr>
            `;
            tbody.innerHTML += row;
          });
        }

        showModal(document.getElementById('viewLocationModal'));
      } catch (error) {
        console.error('Error loading location details:', error);
        showNotification('Failed to load location details', 'danger');
      }
    }

    function showAddLocationModal() {
      editingLocationId = null;
      document.getElementById('locationForm').reset();
      document.getElementById('locationModalTitle').textContent = 'Add Storage Location';
      document.getElementById('locationName').value = '';
      document.getElementById('locationActive').checked = true;
      showModal(document.getElementById('addLocationModal'));
    }

    async function editLocation(locationId) {
      try {
        const location = currentLocations.find(l => l.id === locationId);
        if (!location) return;

        editingLocationId = locationId;
        document.getElementById('locationModalTitle').textContent = 'Edit Storage Location';

        // Populate form
        document.getElementById('locationName').value = location.name || '';
        document.getElementById('locationDescription').value = location.description || '';
        document.getElementById('locationType').value = location.type || 'bin';
        document.getElementById('locationAisle').value = location.aisle || '';
        document.getElementById('locationBay').value = location.bay || '';
        document.getElementById('locationLevel').value = location.level || '';
        document.getElementById('locationPosition').value = location.position || '';
        document.getElementById('locationActive').checked = location.is_active;

        showModal(document.getElementById('addLocationModal'));
      } catch (error) {
        console.error('Error loading location for edit:', error);
        showNotification('Failed to load location', 'danger');
      }
    }

    async function handleLocationFormSubmit(e) {
      e.preventDefault();

      const formData = new FormData(e.target);
      const data = {
        name: formData.get('name'),
        type: formData.get('type'),
        description: formData.get('description'),
        aisle: formData.get('aisle'),
        bay: formData.get('bay'),
        level: formData.get('level'),
        position: formData.get('position'),
        is_active: formData.get('is_active') === 'on',
      };

      try {
        let response;
        if (editingLocationId) {
          // Update existing location
          response = await apiCall(`/storage-locations/${editingLocationId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
          });
        } else {
          // Create new location
          response = await apiCall(`/storage-locations`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
          });
        }

        if (response.ok) {
          showNotification(editingLocationId ? 'Location updated successfully' : 'Location created successfully', 'success');
          hideModal(document.getElementById('addLocationModal'));
          loadLocations(); // Reload the list
        } else {
          const error = await response.json();
          throw new Error(error.message || 'Failed to save location');
        }
      } catch (error) {
        console.error('Error saving location:', error);
        showNotification('Failed to save location: ' + error.message, 'danger');
      }
    }

    async function deleteLocation(locationId, locationName) {
      if (!confirm(`Are you sure you want to delete location "${locationName}"? This action cannot be undone.`)) {
        return;
      }

      try {
        const response = await apiCall(`/storage-locations/${locationId}`, {
          method: 'DELETE'
        });

        if (response.ok) {
          showNotification('Location deleted successfully', 'success');
          loadLocations(); // Reload the list
        } else {
          const error = await response.json();
          throw new Error(error.message || 'Failed to delete location');
        }
      } catch (error) {
        console.error('Error deleting location:', error);
        showNotification('Failed to delete location: ' + error.message, 'danger');
      }
    }

    async function exportLocations() {
      try {
        // Create CSV
        const headers = ['Name', 'Code', 'Type', 'Aisle', 'Bay', 'Level', 'Position', 'Products Stored', 'Total Quantity', 'Total Value', 'Status'];
        const rows = currentLocations.map(loc => [
          loc.name,
          loc.code || '',
          loc.type,
          loc.aisle || '',
          loc.bay || '',
          loc.level || '',
          loc.position || '',
          loc.stats.products_count,
          loc.stats.total_quantity,
          loc.stats.total_value.toFixed(2),
          loc.is_active ? 'Active' : 'Inactive'
        ]);

        const csv = [headers, ...rows].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `storage_locations_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        showNotification('Locations exported successfully', 'success');
      } catch (error) {
        console.error('Export failed:', error);
        showNotification('Export failed: ' + error.message, 'danger');
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
  </script>
@endpush
