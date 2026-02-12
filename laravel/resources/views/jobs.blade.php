@extends('layouts.app')

@section('title', 'Jobs Management - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Production</div>
              <h1 class="page-title">Jobs & Projects</h1>
            </div>
            <div class="col-auto ms-auto d-print-none">
              <div class="btn-list">
                <button class="btn btn-primary" onclick="showAddJobModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  Add Job
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
                  <div class="subheader">Total Jobs</div>
                  <div class="h1 mb-3" id="statTotalJobs">-</div>
                  <div class="text-muted">All jobs in system</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Active Jobs</div>
                  <div class="h1 mb-3 text-success" id="statActiveJobs">-</div>
                  <div class="text-muted">In progress</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">On Hold</div>
                  <div class="h1 mb-3 text-warning" id="statOnHoldJobs">-</div>
                  <div class="text-muted">Pending action</div>
                </div>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="card">
                <div class="card-body">
                  <div class="subheader">Completed</div>
                  <div class="h1 mb-3 text-info" id="statCompletedJobs">-</div>
                  <div class="text-muted">Finished</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Job Table -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Jobs</h3>
                  <div class="ms-auto d-flex gap-2">
                    <select class="form-select form-select-sm" id="filterStatus" style="width: auto;">
                      <option value="">All Status</option>
                      <option value="active">Active</option>
                      <option value="on_hold">On Hold</option>
                      <option value="completed">Completed</option>
                      <option value="cancelled">Cancelled</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" placeholder="Search jobs..." id="searchInput" style="width: 250px;">
                  </div>
                </div>
                <div class="card-body">
                  <div class="loading" id="loadingIndicator">
                    <div class="text-muted">Loading jobs...</div>
                  </div>

                  <div id="jobsTable" style="display: none;">
                    <div class="table-responsive">
                      <table class="table table-vcenter card-table table-striped">
                        <thead>
                          <tr>
                            <th>Job Number</th>
                            <th>Job Name</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th>Reservations</th>
                            <th>Start Date</th>
                            <th>Target Completion</th>
                            <th>Days Remaining</th>
                            <th class="w-1">Actions</th>
                          </tr>
                        </thead>
                        <tbody id="jobsTableBody">
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div id="emptyState" style="display: none;">
                    <div class="empty">
                      <div class="empty-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /></svg>
                      </div>
                      <p class="empty-title">No jobs found</p>
                      <p class="empty-subtitle text-muted">
                        Get started by creating your first job
                      </p>
                      <div class="empty-action">
                        <button class="btn btn-primary" onclick="showAddJobModal()">
                          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                          Add Job
                        </button>
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

    <!-- Add/Edit Job Modal -->
    <div class="modal fade" id="jobModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="jobModalTitle">Add Job</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="jobForm">
              <input type="hidden" id="jobId">

              <!-- Basic Information -->
              <div class="card mb-3">
                <div class="card-header">
                  <h3 class="card-title">Basic Information</h3>
                </div>
                <div class="card-body">
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label required">Job Number</label>
                      <input type="text" class="form-control" id="jobNumber" required>
                      <small class="form-hint">Unique identifier for this job</small>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label required">Job Name</label>
                      <input type="text" class="form-control" id="jobName" required>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Customer Name</label>
                    <input type="text" class="form-control" id="customerName">
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Site Address</label>
                    <textarea class="form-control" id="siteAddress" rows="2"></textarea>
                  </div>
                </div>
              </div>

              <!-- Contact Information -->
              <div class="card mb-3">
                <div class="card-header">
                  <h3 class="card-title">Contact Information</h3>
                </div>
                <div class="card-body">
                  <div class="row mb-3">
                    <div class="col-md-6">
                      <label class="form-label">Contact Name</label>
                      <input type="text" class="form-control" id="contactName">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Contact Phone</label>
                      <input type="tel" class="form-control" id="contactPhone">
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Contact Email</label>
                    <input type="email" class="form-control" id="contactEmail">
                  </div>
                </div>
              </div>

              <!-- Project Details -->
              <div class="card mb-3">
                <div class="card-header">
                  <h3 class="card-title">Project Details</h3>
                </div>
                <div class="card-body">
                  <div class="row mb-3">
                    <div class="col-md-4">
                      <label class="form-label">Status</label>
                      <select class="form-select" id="status">
                        <option value="active">Active</option>
                        <option value="on_hold">On Hold</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Start Date</label>
                      <input type="date" class="form-control" id="startDate">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Target Completion</label>
                      <input type="date" class="form-control" id="targetCompletionDate">
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" rows="3"></textarea>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveJob()">Save Job</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Job Reservations Modal -->
    <div class="modal fade" id="reservationsModal" tabindex="-1">
      <div class="modal-dialog modal-xl">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="reservationsModalTitle">Job Reservations</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h3 id="reservationsJobInfo" class="mb-0"></h3>
              </div>
              <button class="btn btn-primary" onclick="showAddReservationModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                New Reservation
              </button>
            </div>

            <div id="reservationsLoading" class="text-center text-muted py-5">
              <div class="spinner-border" role="status"></div>
              <p class="mt-2">Loading reservations...</p>
            </div>

            <div id="reservationsContent" style="display: none;">
              <div class="table-responsive">
                <table class="table table-vcenter card-table table-striped">
                  <thead>
                    <tr>
                      <th>Res #</th>
                      <th>Status</th>
                      <th>Requested By</th>
                      <th>Needed By</th>
                      <th>Items</th>
                      <th>Committed</th>
                      <th>Consumed</th>
                      <th>Created</th>
                      <th class="w-1">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="reservationsTableBody">
                  </tbody>
                </table>
              </div>
            </div>

            <div id="reservationsEmpty" style="display: none;" class="empty">
              <div class="empty-icon">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /></svg>
              </div>
              <p class="empty-title">No reservations yet</p>
              <p class="empty-subtitle text-muted">
                Create your first reservation for this job
              </p>
              <div class="empty-action">
                <button class="btn btn-primary" onclick="showAddReservationModal()">
                  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                  New Reservation
                </button>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Reservation Modal -->
    <div class="modal fade" id="addReservationModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">New Reservation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="addReservationForm">
              <input type="hidden" id="reservationJobId">

              <div class="mb-3">
                <label class="form-label required">Requested By</label>
                <input type="text" class="form-control" id="reservationRequestedBy" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Needed By</label>
                <input type="date" class="form-control" id="reservationNeededBy">
              </div>

              <div class="mb-3">
                <label class="form-label">Notes</label>
                <textarea class="form-control" id="reservationNotes" rows="3"></textarea>
              </div>

              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Items to Reserve</h3>
                  <div class="card-actions">
                    <button type="button" class="btn btn-sm btn-primary" onclick="showAddItemToReservation()">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-sm"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 5l0 14" /><path d="M5 12l14 0" /></svg>
                      Add Item
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div id="reservationItemsContainer">
                    <p class="text-muted">No items added yet. Click "Add Item" to begin.</p>
                  </div>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveReservation()">Create Reservation</button>
          </div>
        </div>
      </div>
    </div>

    <script>
        let allJobs = [];
        let currentJob = null;

        document.addEventListener('DOMContentLoaded', function() {
            loadJobs();

            // Search and filter
            document.getElementById('searchInput').addEventListener('input', filterJobs);
            document.getElementById('filterStatus').addEventListener('change', filterJobs);
        });

        async function loadJobs() {
            try {
                document.getElementById('loadingIndicator').style.display = 'block';
                document.getElementById('jobsTable').style.display = 'none';
                document.getElementById('emptyState').style.display = 'none';

                const response = await fetch('/api/v1/business-jobs', {
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load jobs');
                }

                const data = await response.json();
                allJobs = data.jobs;

                updateStats();
                displayJobs(allJobs);

                document.getElementById('loadingIndicator').style.display = 'none';

                if (allJobs.length === 0) {
                    document.getElementById('emptyState').style.display = 'block';
                } else {
                    document.getElementById('jobsTable').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading jobs:', error);
                alert('Failed to load jobs');
                document.getElementById('loadingIndicator').style.display = 'none';
            }
        }

        function updateStats() {
            const total = allJobs.length;
            const active = allJobs.filter(j => j.status === 'active').length;
            const onHold = allJobs.filter(j => j.status === 'on_hold').length;
            const completed = allJobs.filter(j => j.status === 'completed').length;

            document.getElementById('statTotalJobs').textContent = total;
            document.getElementById('statActiveJobs').textContent = active;
            document.getElementById('statOnHoldJobs').textContent = onHold;
            document.getElementById('statCompletedJobs').textContent = completed;
        }

        function displayJobs(jobs) {
            const tbody = document.getElementById('jobsTableBody');
            tbody.innerHTML = '';

            jobs.forEach(job => {
                const tr = document.createElement('tr');
                const reservationCount = job.reservations_count || 0;
                tr.innerHTML = `
                    <td><strong>${escapeHtml(job.job_number)}</strong></td>
                    <td>${escapeHtml(job.job_name)}</td>
                    <td>${escapeHtml(job.customer_name || '-')}</td>
                    <td><span class="badge bg-${getStatusColor(job.status)}">${job.status_label}</span></td>
                    <td>
                        <button class="btn btn-sm ${reservationCount > 0 ? 'btn-info' : 'btn-ghost-secondary'}"
                                onclick="viewJobReservations(${job.id})"
                                title="View Reservations">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" /><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" /><path d="M9 12l2 2l4 -4" /></svg>
                            ${reservationCount}
                        </button>
                    </td>
                    <td>${job.start_date || '-'}</td>
                    <td>${job.target_completion_date || '-'}</td>
                    <td>${getDaysRemaining(job.days_until_completion)}</td>
                    <td>
                        <div class="btn-list flex-nowrap">
                            <button class="btn btn-sm btn-icon btn-primary" onclick="editJob(${job.id})" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1" /><path d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z" /><path d="M16 5l3 3" /></svg>
                            </button>
                            <button class="btn btn-sm btn-icon btn-danger" onclick="deleteJob(${job.id})" title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function filterJobs() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value;

            let filtered = allJobs;

            if (search) {
                filtered = filtered.filter(job =>
                    job.job_number.toLowerCase().includes(search) ||
                    job.job_name.toLowerCase().includes(search) ||
                    (job.customer_name && job.customer_name.toLowerCase().includes(search))
                );
            }

            if (statusFilter) {
                filtered = filtered.filter(job => job.status === statusFilter);
            }

            displayJobs(filtered);
        }

        function showAddJobModal() {
            currentJob = null;
            document.getElementById('jobModalTitle').textContent = 'Add Job';
            document.getElementById('jobForm').reset();
            document.getElementById('jobId').value = '';
            document.getElementById('status').value = 'active';

            const modal = new bootstrap.Modal(document.getElementById('jobModal'));
            modal.show();
        }

        async function editJob(id) {
            try {
                const response = await fetch(`/api/v1/business-jobs/${id}`, {
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load job');
                }

                const data = await response.json();
                currentJob = data.job;

                document.getElementById('jobModalTitle').textContent = 'Edit Job';
                document.getElementById('jobId').value = currentJob.id;
                document.getElementById('jobNumber').value = currentJob.job_number;
                document.getElementById('jobName').value = currentJob.job_name;
                document.getElementById('customerName').value = currentJob.customer_name || '';
                document.getElementById('siteAddress').value = currentJob.site_address || '';
                document.getElementById('contactName').value = currentJob.contact_name || '';
                document.getElementById('contactPhone').value = currentJob.contact_phone || '';
                document.getElementById('contactEmail').value = currentJob.contact_email || '';
                document.getElementById('status').value = currentJob.status;
                document.getElementById('startDate').value = currentJob.start_date || '';
                document.getElementById('targetCompletionDate').value = currentJob.target_completion_date || '';
                document.getElementById('notes').value = currentJob.notes || '';

                const modal = new bootstrap.Modal(document.getElementById('jobModal'));
                modal.show();
            } catch (error) {
                console.error('Error loading job:', error);
                alert('Failed to load job details');
            }
        }

        async function saveJob() {
            const jobId = document.getElementById('jobId').value;
            const isEdit = jobId !== '';

            const jobData = {
                job_number: document.getElementById('jobNumber').value,
                job_name: document.getElementById('jobName').value,
                customer_name: document.getElementById('customerName').value || null,
                site_address: document.getElementById('siteAddress').value || null,
                contact_name: document.getElementById('contactName').value || null,
                contact_phone: document.getElementById('contactPhone').value || null,
                contact_email: document.getElementById('contactEmail').value || null,
                status: document.getElementById('status').value,
                start_date: document.getElementById('startDate').value || null,
                target_completion_date: document.getElementById('targetCompletionDate').value || null,
                notes: document.getElementById('notes').value || null,
            };

            try {
                const url = isEdit ? `/api/v1/business-jobs/${jobId}` : '/api/v1/business-jobs';
                const method = isEdit ? 'PUT' : 'POST';

                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                    body: JSON.stringify(jobData),
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to save job');
                }

                const modal = bootstrap.Modal.getInstance(document.getElementById('jobModal'));
                modal.hide();

                await loadJobs();
                alert(isEdit ? 'Job updated successfully' : 'Job created successfully');
            } catch (error) {
                console.error('Error saving job:', error);
                alert(error.message);
            }
        }

        async function deleteJob(id) {
            if (!confirm('Are you sure you want to delete this job? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch(`/api/v1/business-jobs/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to delete job');
                }

                await loadJobs();
                alert('Job deleted successfully');
            } catch (error) {
                console.error('Error deleting job:', error);
                alert(error.message);
            }
        }

        function getStatusColor(status) {
            const colors = {
                'active': 'success',
                'on_hold': 'warning',
                'completed': 'info',
                'cancelled': 'danger',
            };
            return colors[status] || 'secondary';
        }

        function getDaysRemaining(days) {
            if (days === null || days === undefined) {
                return '-';
            }

            if (days < 0) {
                return `<span class="text-danger">${Math.abs(days)} days overdue</span>`;
            } else if (days === 0) {
                return '<span class="text-warning">Due today</span>';
            } else if (days <= 7) {
                return `<span class="text-warning">${days} days</span>`;
            } else {
                return `<span class="text-muted">${days} days</span>`;
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // ===== RESERVATION MANAGEMENT FUNCTIONS =====
        let currentJobForReservations = null;
        let jobReservations = [];
        let reservationItems = [];

        async function viewJobReservations(jobId) {
            currentJobForReservations = allJobs.find(j => j.id === jobId);
            if (!currentJobForReservations) return;

            document.getElementById('reservationsJobInfo').textContent =
                `${currentJobForReservations.job_number} - ${currentJobForReservations.job_name}`;

            // Show modal and loading state
            const modal = new bootstrap.Modal(document.getElementById('reservationsModal'));
            modal.show();

            document.getElementById('reservationsLoading').style.display = 'block';
            document.getElementById('reservationsContent').style.display = 'none';
            document.getElementById('reservationsEmpty').style.display = 'none';

            try {
                const response = await fetch(`/api/v1/business-jobs/${jobId}/reservations`, {
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load reservations');
                }

                const data = await response.json();
                jobReservations = data.reservations;

                displayJobReservations();
            } catch (error) {
                console.error('Error loading reservations:', error);
                alert('Failed to load reservations');
                document.getElementById('reservationsLoading').style.display = 'none';
            }
        }

        function displayJobReservations() {
            document.getElementById('reservationsLoading').style.display = 'none';

            if (jobReservations.length === 0) {
                document.getElementById('reservationsEmpty').style.display = 'block';
                return;
            }

            document.getElementById('reservationsContent').style.display = 'block';

            const tbody = document.getElementById('reservationsTableBody');
            tbody.innerHTML = jobReservations.map(res => `
                <tr>
                    <td><strong>#${res.reservation_id}</strong></td>
                    <td><span class="badge bg-${getReservationStatusColor(res.status)}">${res.status_label}</span></td>
                    <td>${escapeHtml(res.requested_by)}</td>
                    <td>${res.needed_by || '-'}</td>
                    <td>${res.items_count}</td>
                    <td>${res.total_committed}</td>
                    <td>${res.total_consumed}</td>
                    <td>${new Date(res.created_at).toLocaleDateString()}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-sm btn-info" onclick="viewReservationDetails(${res.reservation_id})" title="View Details">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" /></svg>
                            </button>
                            ${res.status !== 'fulfilled' && res.status !== 'cancelled' ? `
                                <button class="btn btn-sm btn-danger" onclick="deleteReservation(${res.reservation_id})" title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        }

        function getReservationStatusColor(status) {
            const colors = {
                'draft': 'secondary',
                'active': 'primary',
                'in_progress': 'info',
                'fulfilled': 'success',
                'on_hold': 'warning',
                'cancelled': 'dark',
            };
            return colors[status] || 'secondary';
        }

        function showAddReservationModal() {
            if (!currentJobForReservations) return;

            document.getElementById('reservationJobId').value = currentJobForReservations.id;
            document.getElementById('reservationRequestedBy').value = '';
            document.getElementById('reservationNeededBy').value = '';
            document.getElementById('reservationNotes').value = '';
            reservationItems = [];
            updateReservationItemsDisplay();

            const modal = new bootstrap.Modal(document.getElementById('addReservationModal'));
            modal.show();
        }

        function showAddItemToReservation() {
            const sku = prompt('Enter product SKU:');
            if (!sku) return;

            // Search for product by SKU
            fetch(`/api/v1/job-reservations/search-product?sku=${encodeURIComponent(sku)}`, {
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            })
            .then(response => response.json())
            .then(data => {
                if (!data.product) {
                    alert('Product not found with SKU: ' + sku);
                    return;
                }

                const product = data.product;
                const requestedQty = parseInt(prompt('Enter quantity needed:', '1')) || 1;

                // Check if already added
                if (reservationItems.some(item => item.product_id === product.id)) {
                    alert('This product is already in the reservation');
                    return;
                }

                reservationItems.push({
                    product_id: product.id,
                    sku: product.sku,
                    part_number: product.part_number,
                    finish: product.finish,
                    description: product.description,
                    requested_qty: requestedQty,
                    committed_qty: Math.min(requestedQty, product.quantity_available),
                    available: product.quantity_available,
                });

                updateReservationItemsDisplay();
            })
            .catch(error => {
                console.error('Error finding product:', error);
                alert('Error finding product');
            });
        }

        function updateReservationItemsDisplay() {
            const container = document.getElementById('reservationItemsContainer');

            if (reservationItems.length === 0) {
                container.innerHTML = '<p class="text-muted">No items added yet. Click "Add Item" to begin.</p>';
                return;
            }

            container.innerHTML = `
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Part #</th>
                            <th>Finish</th>
                            <th>Description</th>
                            <th>Requested</th>
                            <th>Available</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        ${reservationItems.map((item, index) => `
                            <tr>
                                <td><code>${item.sku}</code></td>
                                <td>${item.part_number}</td>
                                <td>${item.finish || '-'}</td>
                                <td>${item.description}</td>
                                <td>${item.requested_qty}</td>
                                <td><span class="badge bg-${item.available > 0 ? 'success' : 'warning'}">${item.available}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-ghost-danger" onclick="removeReservationItem(${index})">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-sm" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 7l16 0" /><path d="M10 11l0 6" /><path d="M14 11l0 6" /><path d="M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12" /><path d="M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3" /></svg>
                                    </button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }

        function removeReservationItem(index) {
            reservationItems.splice(index, 1);
            updateReservationItemsDisplay();
        }

        async function saveReservation() {
            const jobId = document.getElementById('reservationJobId').value;
            const requestedBy = document.getElementById('reservationRequestedBy').value;
            const neededBy = document.getElementById('reservationNeededBy').value;
            const notes = document.getElementById('reservationNotes').value;

            if (!requestedBy) {
                alert('Please enter who requested this reservation');
                return;
            }

            if (reservationItems.length === 0) {
                alert('Please add at least one item to the reservation');
                return;
            }

            try {
                const response = await fetch(`/api/v1/business-jobs/${jobId}/reservations`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                    body: JSON.stringify({
                        requested_by: requestedBy,
                        needed_by: neededBy || null,
                        notes: notes || null,
                        items: reservationItems.map(item => ({
                            product_id: item.product_id,
                            requested_qty: item.requested_qty,
                            committed_qty: item.committed_qty,
                        })),
                    }),
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to create reservation');
                }

                const data = await response.json();

                // Close add modal
                const addModal = bootstrap.Modal.getInstance(document.getElementById('addReservationModal'));
                addModal.hide();

                // Refresh reservations list
                await viewJobReservations(jobId);

                alert(`Reservation #${data.reservation.reservation_id} created successfully!`);
            } catch (error) {
                console.error('Error creating reservation:', error);
                alert(error.message);
            }
        }

        async function viewReservationDetails(reservationId) {
            if (!currentJobForReservations) return;

            try {
                const response = await fetch(`/api/v1/business-jobs/${currentJobForReservations.id}/reservations/${reservationId}`, {
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load reservation details');
                }

                const data = await response.json();
                alert(`Reservation #${data.reservation.reservation_id}\n\nStatus: ${data.reservation.status_label}\nRequested By: ${data.reservation.requested_by}\nItems: ${data.items.length}\n\nClick OK to view full details in the Job Reservations page.`);
            } catch (error) {
                console.error('Error loading reservation details:', error);
                alert('Failed to load reservation details');
            }
        }

        async function deleteReservation(reservationId) {
            if (!currentJobForReservations) return;

            if (!confirm(`Are you sure you want to delete Reservation #${reservationId}? This action cannot be undone.`)) {
                return;
            }

            try {
                const response = await fetch(`/api/v1/business-jobs/${currentJobForReservations.id}/reservations/${reservationId}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                    },
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.message || 'Failed to delete reservation');
                }

                // Refresh reservations list
                await viewJobReservations(currentJobForReservations.id);
                await loadJobs(); // Refresh main jobs list to update counts

                alert('Reservation deleted successfully');
            } catch (error) {
                console.error('Error deleting reservation:', error);
                alert(error.message);
            }
        }
    </script>
@endsection
