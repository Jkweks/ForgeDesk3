@extends('layouts.app')

@section('title', 'Material Check - ForgeDesk')

@section('content')
    <div class="page-wrapper">
      <div class="page-header d-print-none">
        <div class="container-xl">
          <div class="row g-2 align-items-center">
            <div class="col">
              <div class="page-pretitle">Fulfillment</div>
              <h1 class="page-title">Material Check</h1>
            </div>
          </div>
        </div>
      </div>

      <main id="content" class="page-body">
        <div class="container-xl">
          <!-- Upload Card -->
          <div class="row row-deck row-cards mb-3">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h3 class="card-title">Upload Estimate File</h3>
                </div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label">Configuration Preset</label>
                    <select class="form-select" id="configPreset" onchange="applyPreset()">
                      <option value="">Custom Configuration</option>
                      <option value="jde">EZ Estimate (JDE Sheet)</option>
                      <option value="generic">Generic Estimate</option>
                    </select>
                    <small class="form-hint">Select a preset or use custom configuration.</small>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Select Excel File (.xlsx or .xlsm)</label>
                    <input type="file" class="form-control" id="estimateFile" accept=".xlsx,.xlsm">
                    <small class="form-hint">Upload an estimate file to check material availability against inventory.</small>
                  </div>
                  <div class="row">
                    <div class="col-md-4 mb-3">
                      <label class="form-label">Sheet Name (Optional)</label>
                      <input type="text" class="form-control" id="sheetName" placeholder="Leave blank for active sheet">
                      <small class="form-hint">Specific sheet name (e.g., "JDE").</small>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label class="form-label">Header Row</label>
                      <input type="number" class="form-control" id="headerRow" value="1" min="1">
                      <small class="form-hint">Row number with column headers.</small>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label class="form-label">Data Start Row</label>
                      <input type="number" class="form-control" id="dataStartRow" value="2" min="1">
                      <small class="form-hint">First row with actual data.</small>
                    </div>
                  </div>
                  <div class="row">
                    <div class="col-md-4 mb-3">
                      <label class="form-label">Part Number Column</label>
                      <input type="text" class="form-control" id="partNumberColumn" value="Part Number" placeholder="Column name or letter (e.g., I)">
                      <small class="form-hint">Column name or letter (A, B, I, etc.).</small>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label class="form-label">Quantity Column</label>
                      <input type="text" class="form-control" id="quantityColumn" value="Quantity" placeholder="Column name or letter (e.g., G)">
                      <small class="form-hint">Column name or letter (A, B, G, etc.).</small>
                    </div>
                    <div class="col-md-4 mb-3">
                      <label class="form-label">Description (Optional)</label>
                      <input type="text" class="form-control" id="descriptionColumn" value="Description" placeholder="Column name or letter">
                      <small class="form-hint">Column name or letter (optional).</small>
                    </div>
                  </div>
                  <button class="btn btn-primary" onclick="checkMaterials()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon me-2"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 11l3 3l8 -8" /><path d="M20 12v6a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-12a2 2 0 0 1 2 -2h9" /></svg>
                    Check Materials
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Results Section -->
          <div id="resultsSection" style="display: none;">
            <!-- Summary Cards -->
            <div class="row row-deck row-cards mb-3">
              <div class="col-sm-6 col-lg-3">
                <div class="card">
                  <div class="card-body">
                    <div class="subheader">Total Items</div>
                    <div class="h1 mb-3 text-primary" id="statTotal">0</div>
                    <div>Items in estimate</div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-lg-3">
                <div class="card">
                  <div class="card-body">
                    <div class="subheader">Available</div>
                    <div class="h1 mb-3 text-success" id="statAvailable">0</div>
                    <div>Items in stock</div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-lg-3">
                <div class="card">
                  <div class="card-body">
                    <div class="subheader">Partial</div>
                    <div class="h1 mb-3 text-warning" id="statPartial">0</div>
                    <div>Items with partial stock</div>
                  </div>
                </div>
              </div>
              <div class="col-sm-6 col-lg-3">
                <div class="card">
                  <div class="card-body">
                    <div class="subheader">Unavailable</div>
                    <div class="h1 mb-3 text-danger" id="statUnavailable">0</div>
                    <div>Items out of stock</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Results Table -->
            <div class="row">
              <div class="col-12">
                <div class="card">
                  <div class="card-header">
                    <h3 class="card-title">Material Check Results</h3>
                    <div class="col-auto ms-auto">
                      <button class="btn btn-sm" onclick="exportResults()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" /><path d="M7 11l5 5l5 -5" /><path d="M12 4l0 12" /></svg>
                        Export Results
                      </button>
                    </div>
                  </div>
                  <div class="card-body">
                    <div class="mb-3">
                      <div class="row g-2">
                        <div class="col-md-4">
                          <input type="text" class="form-control" id="searchInput" placeholder="Search results..." onkeyup="filterResults()">
                        </div>
                        <div class="col-md-3">
                          <select class="form-select" id="statusFilter" onchange="filterResults()">
                            <option value="">All Status</option>
                            <option value="available">Available</option>
                            <option value="partial">Partial</option>
                            <option value="unavailable">Unavailable</option>
                            <option value="not_found">Not Found</option>
                          </select>
                        </div>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-vcenter" id="resultsTable">
                        <thead>
                          <tr>
                            <th>Status</th>
                            <th>Part Number</th>
                            <th>Description</th>
                            <th>Required Qty</th>
                            <th>Available Qty</th>
                            <th>Shortage</th>
                            <th>Location</th>
                          </tr>
                        </thead>
                        <tbody id="resultsTableBody">
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </main>
    </div>

    <script>
        let checkResults = [];
        let filteredResults = [];

        function applyPreset() {
            const preset = document.getElementById('configPreset').value;

            if (preset === 'jde') {
                // EZ Estimate JDE Sheet configuration
                document.getElementById('sheetName').value = 'JDE';
                document.getElementById('headerRow').value = '2';
                document.getElementById('dataStartRow').value = '3';
                document.getElementById('partNumberColumn').value = 'I';  // Column I = Part #
                document.getElementById('quantityColumn').value = 'G';    // Column G = Qty
                document.getElementById('descriptionColumn').value = '';  // No description in JDE output
            } else if (preset === 'generic') {
                // Generic estimate configuration
                document.getElementById('sheetName').value = '';
                document.getElementById('headerRow').value = '1';
                document.getElementById('dataStartRow').value = '2';
                document.getElementById('partNumberColumn').value = 'Part Number';
                document.getElementById('quantityColumn').value = 'Quantity';
                document.getElementById('descriptionColumn').value = 'Description';
            }
        }

        async function checkMaterials() {
            const fileInput = document.getElementById('estimateFile');
            const file = fileInput.files[0];

            if (!file) {
                alert('Please select a file');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            const sheetName = document.getElementById('sheetName').value;
            if (sheetName) {
                formData.append('sheet_name', sheetName);
            }

            const headerRow = document.getElementById('headerRow').value;
            if (headerRow) {
                formData.append('header_row', headerRow);
            }

            const dataStartRow = document.getElementById('dataStartRow').value;
            if (dataStartRow) {
                formData.append('data_start_row', dataStartRow);
            }

            formData.append('part_number_column', document.getElementById('partNumberColumn').value);
            formData.append('quantity_column', document.getElementById('quantityColumn').value);
            formData.append('description_column', document.getElementById('descriptionColumn').value);

            try {
                const authToken = localStorage.getItem('authToken');
                const response = await fetch('/api/v1/fulfillment/material-check', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${authToken}`,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        // Don't set Content-Type - browser will set it automatically with boundary for FormData
                    },
                    body: formData
                });

                if (response.ok) {
                    const data = await response.json();
                    checkResults = data.results;
                    filteredResults = [...checkResults];
                    displayResults(data.results, data.summary);
                } else {
                    const error = await response.json();
                    alert('Error checking materials: ' + (error.error || error.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error checking materials:', error);
                alert('Error checking materials: ' + error.message);
            }
        }

        function displayResults(results, summary) {
            // Update summary cards
            document.getElementById('statTotal').textContent = summary.total;
            document.getElementById('statAvailable').textContent = summary.available;
            document.getElementById('statPartial').textContent = summary.partial;
            document.getElementById('statUnavailable').textContent = summary.unavailable + summary.not_found;

            // Show results section
            document.getElementById('resultsSection').style.display = 'block';

            // Populate table
            updateResultsTable(results);
        }

        function updateResultsTable(results) {
            const tbody = document.getElementById('resultsTableBody');
            tbody.innerHTML = '';

            results.forEach(item => {
                const row = document.createElement('tr');

                let statusBadge = '';
                let statusClass = '';

                if (item.status === 'available') {
                    statusBadge = '<span class="badge bg-success">Available</span>';
                    statusClass = '';
                } else if (item.status === 'partial') {
                    statusBadge = '<span class="badge bg-warning">Partial</span>';
                    statusClass = 'table-warning';
                } else if (item.status === 'unavailable') {
                    statusBadge = '<span class="badge bg-danger">Out of Stock</span>';
                    statusClass = 'table-danger';
                } else if (item.status === 'not_found') {
                    statusBadge = '<span class="badge bg-secondary">Not Found</span>';
                    statusClass = 'table-secondary';
                }

                row.className = statusClass;
                row.innerHTML = `
                    <td>${statusBadge}</td>
                    <td><strong>${item.part_number}</strong></td>
                    <td>${item.description || '-'}</td>
                    <td>${item.required_quantity}</td>
                    <td>${item.available_quantity}</td>
                    <td>${item.shortage > 0 ? '<span class="text-danger">' + item.shortage + '</span>' : '-'}</td>
                    <td>${item.location || '-'}</td>
                `;

                tbody.appendChild(row);
            });
        }

        function filterResults() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;

            filteredResults = checkResults.filter(item => {
                const matchesSearch = !searchTerm ||
                    item.part_number.toLowerCase().includes(searchTerm) ||
                    (item.description && item.description.toLowerCase().includes(searchTerm));

                const matchesStatus = !statusFilter || item.status === statusFilter;

                return matchesSearch && matchesStatus;
            });

            updateResultsTable(filteredResults);
        }

        function exportResults() {
            if (checkResults.length === 0) {
                alert('No results to export');
                return;
            }

            // Create CSV content
            const headers = ['Status', 'Part Number', 'Description', 'Required Qty', 'Available Qty', 'Shortage', 'Location'];
            const rows = checkResults.map(item => [
                item.status,
                item.part_number,
                item.description || '',
                item.required_quantity,
                item.available_quantity,
                item.shortage,
                item.location || ''
            ]);

            let csvContent = headers.join(',') + '\n';
            rows.forEach(row => {
                csvContent += row.map(cell => `"${cell}"`).join(',') + '\n';
            });

            // Download file
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'material-check-results-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
@endsection
