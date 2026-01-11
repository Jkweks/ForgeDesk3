@extends('layouts.app')

@section('title', 'Maintenance Hub - ForgeDesk')

@section('styles')
.priority-critical { background-color: #d63939; color: white; }
.priority-high { background-color: #f76707; color: white; }
.priority-medium { background-color: #fab005; color: white; }
.priority-low { background-color: #74b816; color: white; }
.overdue { background-color: #d63939; color: white; }
.due-soon { background-color: #fab005; color: white; }
@endsection

@section('content')
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

@endsection

@push('scripts')
  <script src="/maintenance.js"></script>
@endpush
