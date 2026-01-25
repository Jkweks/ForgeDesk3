@extends('layouts.app')

@section('title', 'Job Reservations - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Fulfillment</div>
              <h1 class="page-title">Job Reservations</h1>
            </div>
            <div class="col-auto ms-auto">
              <a href="/fulfillment/material-check" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                New Material Check
              </a>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <!-- Status Filter Cards -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-md-6 col-lg-3">
              <div class="card card-link" onclick="filterByStatus('')">
                <div class="card-body">
                  <div class="subheader">Total Reservations</div>
                  <div class="h2 mb-0" id="statTotal">0</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="card card-link" onclick="filterByStatus('active')">
                <div class="card-body">
                  <div class="subheader">Active</div>
                  <div class="h2 mb-0 text-primary" id="statActive">0</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="card card-link" onclick="filterByStatus('in_progress')">
                <div class="card-body">
                  <div class="subheader">In Progress</div>
                  <div class="h2 mb-0 text-info" id="statInProgress">0</div>
                </div>
              </div>
            </div>
            <div class="col-md-6 col-lg-3">
              <div class="card card-link" onclick="filterByStatus('fulfilled')">
                <div class="card-body">
                  <div class="subheader">Fulfilled</div>
                  <div class="h2 mb-0 text-success" id="statFulfilled">0</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Reservations List -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Job Reservations</h3>
                  <div class="col-auto ms-auto">
                    <div class="d-flex gap-2">
                      <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search..." onkeyup="filterReservations()">
                      <select class="form-select form-select-sm" id="statusFilter" onchange="filterReservations()">
                        <option value="">All Status</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="in_progress">In Progress</option>
                        <option value="fulfilled">Fulfilled</option>
                        <option value="on_hold">On Hold</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-vcenter card-table">
                    <thead>
                      <tr>
                        <th>Job #</th>
                        <th>Release</th>
                        <th>Job Name</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Needed By</th>
                        <th>Items</th>
                        <th>Committed</th>
                        <th>Consumed</th>
                        <th>Created</th>
                        <th class="w-1"></th>
                      </tr>
                    </thead>
                    <tbody id="reservationsTableBody">
                      <tr>
                        <td colspan="11" class="text-center text-muted">Loading...</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <!-- Reservation Detail Modal -->
    <div class="modal modal-blur fade" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="detailModalTitle">Reservation Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="detailModalBody">
            <!-- Content loaded dynamically -->
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Status Change Modal -->
    <div class="modal modal-blur fade" id="statusModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Change Status</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="statusChangeReservationId">
            <div class="mb-3">
              <label class="form-label">New Status</label>
              <select class="form-select" id="newStatus">
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="in_progress">In Progress</option>
                <option value="fulfilled">Fulfilled</option>
                <option value="on_hold">On Hold</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            <div id="statusWarnings" class="alert alert-warning" style="display: none;"></div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmStatusChange()">Change Status</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Complete Job Modal -->
    <div class="modal modal-blur fade" id="completeModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Complete Job</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="completeReservationId">
            <div class="alert alert-info">
              Enter the actual consumed quantities for each item. Items will be deducted from inventory.
            </div>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Part #</th>
                    <th>Finish</th>
                    <th>Committed</th>
                    <th>Already Consumed</th>
                    <th>Actual Consumed</th>
                    <th>To Release</th>
                  </tr>
                </thead>
                <tbody id="completeItemsTable">
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-success" onclick="confirmComplete()">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
              Complete Job
            </button>
          </div>
        </div>
      </div>
    </div>

    <script>
        let reservations = [];
        let filteredReservations = [];
        let completeItems = [];

        // Load reservations on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadReservations();
        });

        async function loadReservations() {
            try {
                const response = await fetch('/api/v1/job-reservations', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    reservations = data.reservations;
                    filteredReservations = [...reservations];
                    displayReservations();
                    updateStats();
                } else {
                    const error = await response.json();
                    console.error('Error loading reservations:', error);
                    document.getElementById('reservationsTableBody').innerHTML = '<tr><td colspan="11" class="text-center text-danger">Error loading reservations</td></tr>';
                }
            } catch (error) {
                console.error('Error loading reservations:', error);
                document.getElementById('reservationsTableBody').innerHTML = '<tr><td colspan="11" class="text-center text-danger">Error loading reservations</td></tr>';
            }
        }

        function displayReservations() {
            const tbody = document.getElementById('reservationsTableBody');

            if (filteredReservations.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted">No reservations found</td></tr>';
                return;
            }

            tbody.innerHTML = filteredReservations.map(res => {
                const statusBadge = getStatusBadge(res.status);
                const rowClass = res.status === 'fulfilled' ? 'table-success' : res.status === 'cancelled' ? 'table-secondary' : '';

                return `
                    <tr class="${rowClass}">
                        <td><strong>${res.job_number}</strong></td>
                        <td>${res.release_number}</td>
                        <td>${res.job_name}</td>
                        <td>${statusBadge}</td>
                        <td>${res.requested_by}</td>
                        <td>${res.needed_by || '-'}</td>
                        <td>${res.items_count}</td>
                        <td>${res.total_committed}</td>
                        <td>${res.total_consumed}</td>
                        <td><small class="text-muted">${new Date(res.created_at).toLocaleDateString()}</small></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-sm btn-primary" onclick="viewDetails(${res.id})" title="View Details">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-sm"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>
                                </button>
                                ${res.status === 'in_progress' ? `
                                    <button class="btn btn-sm btn-success" onclick="showCompleteModal(${res.id})" title="Complete Job">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-sm"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l5 5l10 -10" /></svg>
                                    </button>
                                ` : ''}
                                ${res.status !== 'fulfilled' && res.status !== 'cancelled' ? `
                                    <button class="btn btn-sm btn-secondary" onclick="showStatusModal(${res.id}, '${res.status}')" title="Change Status">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-sm"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /><path d="M9 12l2 2l4 -4" /></svg>
                                    </button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function getStatusBadge(status) {
            const badges = {
                'draft': '<span class="badge bg-secondary">Draft</span>',
                'active': '<span class="badge bg-primary">Active</span>',
                'in_progress': '<span class="badge bg-info">In Progress</span>',
                'fulfilled': '<span class="badge bg-success">Fulfilled</span>',
                'on_hold': '<span class="badge bg-warning">On Hold</span>',
                'cancelled': '<span class="badge bg-dark">Cancelled</span>',
            };
            return badges[status] || `<span class="badge">${status}</span>`;
        }

        function updateStats() {
            const stats = {
                total: reservations.length,
                active: reservations.filter(r => r.status === 'active').length,
                in_progress: reservations.filter(r => r.status === 'in_progress').length,
                fulfilled: reservations.filter(r => r.status === 'fulfilled').length,
            };

            document.getElementById('statTotal').textContent = stats.total;
            document.getElementById('statActive').textContent = stats.active;
            document.getElementById('statInProgress').textContent = stats.in_progress;
            document.getElementById('statFulfilled').textContent = stats.fulfilled;
        }

        function filterReservations() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;

            filteredReservations = reservations.filter(res => {
                const matchesSearch = !searchTerm ||
                    res.job_number.toLowerCase().includes(searchTerm) ||
                    res.job_name.toLowerCase().includes(searchTerm) ||
                    res.requested_by.toLowerCase().includes(searchTerm);

                const matchesStatus = !statusFilter || res.status === statusFilter;

                return matchesSearch && matchesStatus;
            });

            displayReservations();
        }

        function filterByStatus(status) {
            document.getElementById('statusFilter').value = status;
            filterReservations();
        }

        async function viewDetails(id) {
            try {
                const response = await fetch(`/api/v1/job-reservations/${id}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    showDetailsModal(data);
                } else {
                    alert('Error loading reservation details');
                }
            } catch (error) {
                console.error('Error loading details:', error);
                alert('Error loading reservation details');
            }
        }

        function showDetailsModal(data) {
            const res = data.reservation;
            const items = data.items;

            document.getElementById('detailModalTitle').textContent = `Reservation #${res.id} - ${res.job_number} Release ${res.release_number}`;

            const itemsTable = items.map(item => `
                <tr>
                    <td><code>${item.product.sku || '-'}</code></td>
                    <td><strong>${item.product.part_number}</strong></td>
                    <td>${item.product.finish || '-'}</td>
                    <td>${item.product.description || '-'}</td>
                    <td>${item.requested_qty}</td>
                    <td>${item.committed_qty}</td>
                    <td>${item.consumed_qty}</td>
                    <td>${item.released_qty}</td>
                    <td>${item.product.quantity_on_hand}</td>
                    <td>${item.product.quantity_available}</td>
                    <td>${item.product.location || '-'}</td>
                </tr>
            `).join('');

            document.getElementById('detailModalBody').innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-5">Job Number:</dt>
                            <dd class="col-7"><strong>${res.job_number}</strong></dd>
                            <dt class="col-5">Release Number:</dt>
                            <dd class="col-7">${res.release_number}</dd>
                            <dt class="col-5">Job Name:</dt>
                            <dd class="col-7">${res.job_name}</dd>
                            <dt class="col-5">Status:</dt>
                            <dd class="col-7">${getStatusBadge(res.status)}</dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row">
                            <dt class="col-5">Requested By:</dt>
                            <dd class="col-7">${res.requested_by}</dd>
                            <dt class="col-5">Needed By:</dt>
                            <dd class="col-7">${res.needed_by || '-'}</dd>
                            <dt class="col-5">Created:</dt>
                            <dd class="col-7">${new Date(res.created_at).toLocaleString()}</dd>
                            <dt class="col-5">Updated:</dt>
                            <dd class="col-7">${new Date(res.updated_at).toLocaleString()}</dd>
                        </dl>
                    </div>
                </div>
                ${res.notes ? `<div class="mb-3"><strong>Notes:</strong> ${res.notes}</div>` : ''}
                <h4>Line Items</h4>
                <div class="table-responsive">
                    <table class="table table-sm table-vcenter">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Part #</th>
                                <th>Finish</th>
                                <th>Description</th>
                                <th>Requested</th>
                                <th>Committed</th>
                                <th>Consumed</th>
                                <th>Released</th>
                                <th>On Hand</th>
                                <th>Available</th>
                                <th>Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsTable}
                        </tbody>
                    </table>
                </div>
            `;

            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        }

        function showStatusModal(id, currentStatus) {
            document.getElementById('statusChangeReservationId').value = id;
            document.getElementById('newStatus').value = currentStatus;
            document.getElementById('statusWarnings').style.display = 'none';

            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }

        async function confirmStatusChange() {
            const id = document.getElementById('statusChangeReservationId').value;
            const status = document.getElementById('newStatus').value;

            try {
                const response = await fetch(`/api/v1/job-reservations/${id}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status })
                });

                if (response.ok) {
                    const data = await response.json();

                    // Show warnings if any
                    if (data.warnings && data.warnings.length > 0) {
                        const warningsDiv = document.getElementById('statusWarnings');
                        warningsDiv.innerHTML = data.warnings.join('<br>');
                        warningsDiv.style.display = 'block';

                        if (data.insufficient_items && data.insufficient_items.length > 0) {
                            const items = data.insufficient_items.map(item =>
                                `${item.part_number}-${item.finish}: need ${item.shortage} more`
                            ).join(', ');
                            warningsDiv.innerHTML += `<br><strong>Insufficient items:</strong> ${items}`;
                        }

                        // Ask for confirmation
                        if (!confirm('There are warnings. Do you want to proceed anyway?')) {
                            return;
                        }
                    }

                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('statusModal'));
                    modal.hide();

                    // Reload reservations
                    await loadReservations();

                    alert(`✅ Status updated to: ${data.reservation.new_status}`);
                } else {
                    const error = await response.json();
                    alert('Error updating status: ' + (error.message || error.error));
                }
            } catch (error) {
                console.error('Error updating status:', error);
                alert('Error updating status: ' + error.message);
            }
        }

        async function showCompleteModal(id) {
            try {
                const response = await fetch(`/api/v1/job-reservations/${id}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    const items = data.items;

                    document.getElementById('completeReservationId').value = id;
                    completeItems = items;

                    const tbody = document.getElementById('completeItemsTable');
                    tbody.innerHTML = items.map((item, index) => {
                        const toRelease = item.committed_qty - item.consumed_qty;
                        return `
                            <tr>
                                <td><strong>${item.product.part_number}</strong></td>
                                <td>${item.product.finish || '-'}</td>
                                <td>${item.committed_qty}</td>
                                <td>${item.consumed_qty}</td>
                                <td>
                                    <input type="number"
                                        class="form-control form-control-sm"
                                        id="consumed_${item.product_id}"
                                        data-product-id="${item.product_id}"
                                        data-committed="${item.committed_qty}"
                                        data-already-consumed="${item.consumed_qty}"
                                        value="${item.consumed_qty}"
                                        min="${item.consumed_qty}"
                                        max="${item.committed_qty}"
                                        onchange="updateToRelease(${item.product_id})"
                                        style="width: 100px;">
                                </td>
                                <td>
                                    <span id="release_${item.product_id}" class="badge bg-info">${toRelease}</span>
                                </td>
                            </tr>
                        `;
                    }).join('');

                    const modal = new bootstrap.Modal(document.getElementById('completeModal'));
                    modal.show();
                } else {
                    alert('Error loading reservation details');
                }
            } catch (error) {
                console.error('Error loading reservation:', error);
                alert('Error loading reservation details');
            }
        }

        function updateToRelease(productId) {
            const input = document.getElementById(`consumed_${productId}`);
            const consumed = parseInt(input.value) || 0;
            const committed = parseInt(input.dataset.committed);
            const alreadyConsumed = parseInt(input.dataset.alreadyConsumed);

            // Validate constraints
            if (consumed < alreadyConsumed) {
                alert(`Cannot reduce consumed quantity below already consumed (${alreadyConsumed})`);
                input.value = alreadyConsumed;
                return;
            }

            if (consumed > committed) {
                alert(`Cannot consume more than committed quantity (${committed})`);
                input.value = committed;
                return;
            }

            // Update to release badge
            const toRelease = committed - consumed;
            document.getElementById(`release_${productId}`).textContent = toRelease;
        }

        async function confirmComplete() {
            const id = document.getElementById('completeReservationId').value;
            const consumedQuantities = {};

            // Gather all consumed quantities
            completeItems.forEach(item => {
                const input = document.getElementById(`consumed_${item.product_id}`);
                consumedQuantities[item.product_id] = parseInt(input.value) || 0;
            });

            try {
                const response = await fetch(`/api/v1/job-reservations/${id}/complete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ consumed_quantities: consumedQuantities })
                });

                if (response.ok) {
                    const data = await response.json();

                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('completeModal'));
                    modal.hide();

                    // Reload reservations
                    await loadReservations();

                    // Show success message
                    const summary = data.items.map(item =>
                        `${item.part_number}-${item.finish}: Consumed ${item.consumed}, Released ${item.released}`
                    ).join('\n');

                    alert(`✅ Job completed successfully!\n\nJob: ${data.reservation.job_number} Release ${data.reservation.release_number}\nTotal Consumed: ${data.reservation.total_consumed}\nTotal Released: ${data.reservation.total_released}\n\n${summary}`);
                } else {
                    const error = await response.json();
                    alert('Error completing job: ' + (error.message || error.error));
                }
            } catch (error) {
                console.error('Error completing job:', error);
                alert('Error completing job: ' + error.message);
            }
        }
    </script>
@endsection
