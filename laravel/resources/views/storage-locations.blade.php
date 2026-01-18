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

    document.addEventListener('DOMContentLoaded', () => {
      loadLocations();

      // Search functionality
      document.getElementById('searchInput').addEventListener('input', debounce(filterLocations, 300));
    });

    async function loadLocations() {
      try {
        const response = await authenticatedFetch(`/locations`);
        currentLocations = await response.json();

        // Calculate location stats
        const locationsMap = {};
        currentLocations.forEach(loc => {
          if (!locationsMap[loc.location]) {
            locationsMap[loc.location] = {
              name: loc.location,
              products: 0,
              totalQuantity: 0,
              totalValue: 0
            };
          }
          locationsMap[loc.location].products++;
          locationsMap[loc.location].totalQuantity += parseFloat(loc.quantity || 0);
          locationsMap[loc.location].totalValue += parseFloat(loc.quantity || 0) * parseFloat(loc.product?.unit_cost || 0);
        });

        const locationsList = Object.values(locationsMap);
        const totalLocations = locationsList.length;
        const inUse = locationsList.filter(l => l.products > 0).length;
        const totalCapacity = locationsList.reduce((sum, l) => sum + l.totalQuantity, 0);
        const utilization = totalLocations > 0 ? ((inUse / totalLocations) * 100).toFixed(1) : 0;

        document.getElementById('statTotalLocations').textContent = totalLocations.toLocaleString();
        document.getElementById('statInUse').textContent = inUse.toLocaleString();
        document.getElementById('statTotalCapacity').textContent = totalCapacity.toLocaleString();
        document.getElementById('statUtilization').textContent = `${utilization}%`;

        renderLocationsTable(locationsList);

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

      // Sort by name
      locations.sort((a, b) => a.name.localeCompare(b.name));

      locations.forEach(location => {
        const statusBadge = location.products > 0
          ? '<span class="badge text-bg-success">In Use</span>'
          : '<span class="badge text-bg-secondary">Empty</span>';

        const row = `
          <tr>
            <td>
              <div class="d-flex align-items-center">
                <span class="avatar avatar-sm me-2"><i class="ti ti-map-pin"></i></span>
                <strong>${location.name}</strong>
              </div>
            </td>
            <td class="text-end">${location.products.toLocaleString()}</td>
            <td class="text-end">${location.totalQuantity.toLocaleString()}</td>
            <td class="text-end">$${location.totalValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>${statusBadge}</td>
            <td class="table-actions">
              <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="viewLocationDetails('${location.name}')" title="View">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>
              </button>
            </td>
          </tr>
        `;
        tbody.innerHTML += row;
      });
    }

    function filterLocations() {
      const search = document.getElementById('searchInput').value.toLowerCase();

      // Re-aggregate from currentLocations
      const locationsMap = {};
      currentLocations.forEach(loc => {
        if (!locationsMap[loc.location]) {
          locationsMap[loc.location] = {
            name: loc.location,
            products: 0,
            totalQuantity: 0,
            totalValue: 0
          };
        }
        locationsMap[loc.location].products++;
        locationsMap[loc.location].totalQuantity += parseFloat(loc.quantity || 0);
        locationsMap[loc.location].totalValue += parseFloat(loc.quantity || 0) * parseFloat(loc.product?.unit_cost || 0);
      });

      let locationsList = Object.values(locationsMap);

      if (search) {
        locationsList = locationsList.filter(loc =>
          loc.name.toLowerCase().includes(search)
        );
      }

      renderLocationsTable(locationsList);
    }

    async function viewLocationDetails(locationName) {
      try {
        // Filter currentLocations for this specific location
        const locationItems = currentLocations.filter(loc => loc.location === locationName);

        document.getElementById('viewLocationModalTitle').textContent = `Location: ${locationName}`;

        const totalProducts = locationItems.length;
        const totalQuantity = locationItems.reduce((sum, item) => sum + parseFloat(item.quantity || 0), 0);
        const totalValue = locationItems.reduce((sum, item) =>
          sum + (parseFloat(item.quantity || 0) * parseFloat(item.product?.unit_cost || 0)), 0);

        document.getElementById('locationDetailsView').innerHTML = `
          <div class="row mb-3">
            <div class="col-md-3">
              <label class="form-label fw-bold">Location Name</label>
              <p><i class="ti ti-map-pin me-2"></i>${locationName}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Products Stored</label>
              <p>${totalProducts.toLocaleString()}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Total Quantity</label>
              <p>${totalQuantity.toLocaleString()}</p>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-bold">Total Value</label>
              <p>$${totalValue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
            </div>
          </div>
        `;

        // Render products table
        const tbody = document.getElementById('locationProductsTableBody');
        tbody.innerHTML = '';

        if (locationItems.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No products at this location</td></tr>';
        } else {
          locationItems.forEach(item => {
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
      document.getElementById('locationForm').reset();
      document.getElementById('locationModalTitle').textContent = 'Add Storage Location';
      showModal(document.getElementById('addLocationModal'));
    }

    document.getElementById('locationForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      showNotification('Location creation feature coming soon. Locations are currently auto-created when adding inventory.', 'info');
      hideModal(document.getElementById('addLocationModal'));
    });

    async function exportLocations() {
      try {
        const locationsMap = {};
        currentLocations.forEach(loc => {
          if (!locationsMap[loc.location]) {
            locationsMap[loc.location] = {
              name: loc.location,
              products: 0,
              totalQuantity: 0,
              totalValue: 0
            };
          }
          locationsMap[loc.location].products++;
          locationsMap[loc.location].totalQuantity += parseFloat(loc.quantity || 0);
          locationsMap[loc.location].totalValue += parseFloat(loc.quantity || 0) * parseFloat(loc.product?.unit_cost || 0);
        });

        const locationsList = Object.values(locationsMap);

        // Create CSV
        const headers = ['Location Name', 'Products Stored', 'Total Quantity', 'Total Value'];
        const rows = locationsList.map(loc => [
          loc.name,
          loc.products,
          loc.totalQuantity,
          loc.totalValue.toFixed(2)
        ]);

        const csv = [headers, ...rows].map(row => row.join(',')).join('\n');
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
