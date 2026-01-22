// Machine Tooling Management
// Requires: API_BASE, authToken, authenticatedFetch from auth-scripts.blade.php

let machineTooling = [];
let toolingStatistics = {};
let compatibleTools = [];

// Load machine tooling data
async function loadMachineTooling() {
  try {
    const machineId = document.getElementById('toolingMachineFilter')?.value;
    const status = document.getElementById('toolingStatusFilter')?.value;

    let url = '/machine-tooling/all';
    const params = new URLSearchParams();

    if (machineId) {
      url = `/machines/${machineId}/tooling`;
    }

    if (status) {
      params.append('status', status);
    }

    if (params.toString()) {
      url += '?' + params.toString();
    }

    const data = await authenticatedFetch(url);
    machineTooling = data.tooling || [];

    renderMachineTooling();
    await loadToolingStatistics();
  } catch (error) {
    console.error('Failed to load machine tooling:', error);
  }
}

// Load tooling statistics
async function loadToolingStatistics() {
  try {
    toolingStatistics = await authenticatedFetch('/machine-tooling/statistics');

    document.getElementById('toolingActiveCount').textContent = toolingStatistics.total_active_tools || 0;
    document.getElementById('toolingWarningCount').textContent = toolingStatistics.tools_warning || 0;
    document.getElementById('toolingReplacementCount').textContent = toolingStatistics.tools_needs_replacement || 0;
    document.getElementById('toolingTotalCount').textContent =
      (toolingStatistics.total_active_tools || 0) + (toolingStatistics.tools_warning || 0) + (toolingStatistics.tools_needs_replacement || 0);
  } catch (error) {
    console.error('Failed to load tooling statistics:', error);
  }
}

// Render machine tooling table
function renderMachineTooling() {
  const tbody = document.getElementById('toolingTable');

  if (!machineTooling || machineTooling.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No tools installed</td></tr>';
    return;
  }

  tbody.innerHTML = machineTooling.map(t => {
    const statusBadge = getToolingStatusBadge(t.status);
    const progressBar = t.product?.tool_type === 'consumable_tool'
      ? getToolLifeProgressBar(t.tool_life_percentage)
      : 'N/A';

    const lifeDisplay = t.product?.tool_type === 'consumable_tool'
      ? `${formatNumber(t.tool_life_used)} / ${formatNumber(t.product.tool_life_max)} ${t.product.tool_life_unit}`
      : 'N/A';

    const percentage = t.product?.tool_type === 'consumable_tool' && t.tool_life_percentage !== null
      ? `${t.tool_life_percentage}%`
      : 'N/A';

    return `
      <tr>
        <td>${escapeHtml(t.machine?.name || 'Unknown')}</td>
        <td>${escapeHtml(t.location_on_machine)}</td>
        <td>
          <div>${escapeHtml(t.product?.description || 'Unknown')}</div>
          <small class="text-muted">${escapeHtml(t.product?.sku || '')}</small>
        </td>
        <td>${getToolTypeBadge(t.product?.tool_type)}</td>
        <td>${lifeDisplay}</td>
        <td>${progressBar} ${percentage}</td>
        <td>${statusBadge}</td>
        <td>${t.installed_at ? new Date(t.installed_at).toLocaleDateString() : '-'}</td>
        <td class="table-actions">
          <button class="btn btn-sm btn-ghost-primary" onclick="viewToolDetails(${t.id})" title="View Details">
            <i class="ti ti-eye"></i>
          </button>
          <button class="btn btn-sm btn-warning" onclick="openReplaceToolModal(${t.id})" title="Replace Tool">
            <i class="ti ti-refresh"></i>
          </button>
          <button class="btn btn-sm btn-danger" onclick="removeTool(${t.id})" title="Remove Tool">
            <i class="ti ti-trash"></i>
          </button>
        </td>
      </tr>
    `;
  }).join('');
}

// Get status badge HTML
function getToolingStatusBadge(status) {
  const badges = {
    'active': '<span class="badge bg-success">Active</span>',
    'warning': '<span class="badge bg-warning">Warning</span>',
    'needs_replacement': '<span class="badge bg-danger">Needs Replacement</span>',
    'replaced': '<span class="badge bg-secondary">Replaced</span>',
  };
  return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

// Get tool type badge HTML
function getToolTypeBadge(toolType) {
  const badges = {
    'consumable_tool': '<span class="badge bg-azure">Machine Tooling</span>',
    'asset_tool': '<span class="badge bg-purple">Maintenance Asset</span>',
  };
  return badges[toolType] || '<span class="badge bg-secondary">Standard Product</span>';
}

// Get tool life progress bar
function getToolLifeProgressBar(percentage) {
  if (percentage === null || percentage === undefined) return '';

  let colorClass = 'bg-success';
  if (percentage >= 80) colorClass = 'bg-danger';
  else if (percentage >= 60) colorClass = 'bg-warning';

  return `
    <div class="progress" style="width: 100px; height: 20px;">
      <div class="progress-bar ${colorClass}" role="progressbar" style="width: ${percentage}%;" aria-valuenow="${percentage}" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
  `;
}

// Initialize machine filter dropdown
async function initializeToolingMachineFilter() {
  try {
    const select = document.getElementById('toolingMachineFilter');
    if (!select || !machines) return;

    select.innerHTML = '<option value="">All Machines</option>' +
      machines.map(m => `<option value="${m.id}">${escapeHtml(m.name)}</option>`).join('');
  } catch (error) {
    console.error('Failed to initialize machine filter:', error);
  }
}

// Open install tool modal
function openInstallToolModal(machineId = null) {
  const modal = document.getElementById('installToolModal');
  const form = document.getElementById('installToolForm');
  form.reset();

  // Populate machine dropdown
  const machineSelect = document.getElementById('installToolMachine');
  machineSelect.innerHTML = '<option value="">Select Machine</option>' +
    machines.map(m => `<option value="${m.id}">${escapeHtml(m.name)}</option>`).join('');

  if (machineId) {
    machineSelect.value = machineId;
    document.getElementById('installToolMachineId').value = machineId;
    loadCompatibleTools();
  }

  // Set default date to today
  document.getElementById('installToolDate').valueAsDate = new Date();

  showModal(modal);
}

// Load compatible tools for selected machine
async function loadCompatibleTools() {
  const machineId = document.getElementById('installToolMachine').value;
  const productSelect = document.getElementById('installToolProduct');

  if (!machineId) {
    productSelect.innerHTML = '<option value="">Select Machine First</option>';
    return;
  }

  try {
    const data = await authenticatedFetch(`/machines/${machineId}/tooling/compatible-tools`);
    compatibleTools = data.tools || [];

    if (compatibleTools.length === 0) {
      productSelect.innerHTML = '<option value="">No compatible tools found</option>';
      return;
    }

    productSelect.innerHTML = '<option value="">Select Tool</option>' +
      compatibleTools.map(t => {
        const category = t.categories && t.categories.length > 0 ? ` [${t.categories[0].name}]` : '';
        const qty = t.quantity_available > 0 ? ` (${t.quantity_available} available)` : ' (Out of Stock)';
        return `<option value="${t.id}" ${t.quantity_available <= 0 ? 'disabled' : ''}>${escapeHtml(t.description)} - ${escapeHtml(t.sku)}${category}${qty}</option>`;
      }).join('');
  } catch (error) {
    console.error('Failed to load compatible tools:', error);
    productSelect.innerHTML = '<option value="">Error loading tools</option>';
  }
}

// Handle install tool form submission
document.getElementById('installToolForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();

  const machineId = document.getElementById('installToolMachine').value;
  const data = {
    product_id: parseInt(document.getElementById('installToolProduct').value),
    location_on_machine: document.getElementById('installToolLocation').value,
    installed_at: document.getElementById('installToolDate').value,
    installed_by: document.getElementById('installToolBy').value || null,
    notes: document.getElementById('installToolNotes').value || null,
  };

  try {
    await authenticatedFetch(`/machines/${machineId}/tooling`, {
      method: 'POST',
      body: JSON.stringify(data)
    });

    hideModal(document.getElementById('installToolModal'));
    await loadMachineTooling();
    showNotification('Tool installed successfully', 'success');
  } catch (error) {
    console.error('Failed to install tool:', error);
    showNotification('Failed to install tool: ' + (error.message || 'Unknown error'), 'danger');
  }
});

// Open replace tool modal
async function openReplaceToolModal(toolingId) {
  const tooling = machineTooling.find(t => t.id === toolingId);
  if (!tooling) {
    showNotification('Tool not found', 'danger');
    return;
  }

  const modal = document.getElementById('replaceToolModal');
  const form = document.getElementById('replaceToolForm');
  form.reset();

  document.getElementById('replaceToolId').value = toolingId;

  // Show current tool info
  const currentInfo = document.getElementById('replaceToolCurrentInfo');
  const lifeInfo = tooling.product?.tool_type === 'consumable_tool'
    ? `<strong>Current Life Used:</strong> ${formatNumber(tooling.tool_life_used)} / ${formatNumber(tooling.product.tool_life_max)} ${tooling.product.tool_life_unit} (${tooling.tool_life_percentage || 0}%)`
    : 'Asset tool (no life tracking)';

  currentInfo.innerHTML = `
    <p><strong>Machine:</strong> ${escapeHtml(tooling.machine?.name || 'Unknown')}</p>
    <p><strong>Location:</strong> ${escapeHtml(tooling.location_on_machine)}</p>
    <p><strong>Tool:</strong> ${escapeHtml(tooling.product?.description || 'Unknown')} (${escapeHtml(tooling.product?.sku || '')})</p>
    <p>${lifeInfo}</p>
  `;

  // Set tool life unit
  document.getElementById('replaceToolLifeUnit').textContent = tooling.product?.tool_life_unit || 'units';

  // Load compatible tools for replacement
  try {
    const data = await authenticatedFetch(`/machines/${tooling.machine_id}/tooling/compatible-tools`);
    const tools = data.tools || [];

    const select = document.getElementById('replaceToolNewProduct');
    select.innerHTML = '<option value="">Select Replacement Tool</option>' +
      tools.map(t => {
        const qty = t.quantity_available > 0 ? ` (${t.quantity_available} available)` : ' (Out of Stock)';
        return `<option value="${t.id}" ${t.quantity_available <= 0 ? 'disabled' : ''}>${escapeHtml(t.description)} - ${escapeHtml(t.sku)}${qty}</option>`;
      }).join('');

    // Pre-select same product if available
    if (tooling.product_id) {
      select.value = tooling.product_id;
    }
  } catch (error) {
    console.error('Failed to load replacement tools:', error);
  }

  showModal(modal);
}

// Handle replace tool form submission
document.getElementById('replaceToolForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();

  const toolingId = document.getElementById('replaceToolId').value;
  const data = {
    tool_life_used: parseFloat(document.getElementById('replaceToolLifeUsed').value),
    new_product_id: parseInt(document.getElementById('replaceToolNewProduct').value),
    reason: document.getElementById('replaceToolReason').value,
    notes: document.getElementById('replaceToolNotes').value || null,
  };

  try {
    await authenticatedFetch(`/machine-tooling/${toolingId}/replace`, {
      method: 'POST',
      body: JSON.stringify(data)
    });

    hideModal(document.getElementById('replaceToolModal'));
    await loadMachineTooling();
    showNotification('Tool replaced successfully', 'success');
  } catch (error) {
    console.error('Failed to replace tool:', error);
    showNotification('Failed to replace tool: ' + (error.message || 'Unknown error'), 'danger');
  }
});

// View tool details
async function viewToolDetails(toolingId) {
  try {
    const tooling = await authenticatedFetch(`/machine-tooling/${toolingId}`);

    const modal = document.getElementById('toolDetailsModal');
    const content = document.getElementById('toolDetailsContent');

    const lifeInfo = tooling.product?.tool_type === 'consumable_tool' ? `
      <h5>Tool Life Information</h5>
      <table class="table table-sm">
        <tr>
          <td><strong>Maximum Life:</strong></td>
          <td>${formatNumber(tooling.product.tool_life_max)} ${tooling.product.tool_life_unit}</td>
        </tr>
        <tr>
          <td><strong>Life Used:</strong></td>
          <td>${formatNumber(tooling.tool_life_used)} ${tooling.product.tool_life_unit}</td>
        </tr>
        <tr>
          <td><strong>Life Remaining:</strong></td>
          <td>${formatNumber(tooling.tool_life_remaining)} ${tooling.product.tool_life_unit}</td>
        </tr>
        <tr>
          <td><strong>Percentage Used:</strong></td>
          <td>${tooling.tool_life_percentage || 0}%</td>
        </tr>
        <tr>
          <td><strong>Warning Threshold:</strong></td>
          <td>${tooling.product.tool_life_warning_threshold || 20}%</td>
        </tr>
      </table>
    ` : '<p><em>Asset tool - No life tracking</em></p>';

    content.innerHTML = `
      <h5>General Information</h5>
      <table class="table table-sm">
        <tr>
          <td><strong>Machine:</strong></td>
          <td>${escapeHtml(tooling.machine?.name || 'Unknown')}</td>
        </tr>
        <tr>
          <td><strong>Location on Machine:</strong></td>
          <td>${escapeHtml(tooling.location_on_machine)}</td>
        </tr>
        <tr>
          <td><strong>Tool:</strong></td>
          <td>${escapeHtml(tooling.product?.description || 'Unknown')}</td>
        </tr>
        <tr>
          <td><strong>SKU:</strong></td>
          <td>${escapeHtml(tooling.product?.sku || '')}</td>
        </tr>
        <tr>
          <td><strong>Tool Type:</strong></td>
          <td>${getToolTypeBadge(tooling.product?.tool_type)}</td>
        </tr>
        <tr>
          <td><strong>Status:</strong></td>
          <td>${getToolingStatusBadge(tooling.status)}</td>
        </tr>
      </table>

      ${lifeInfo}

      <h5>Installation Details</h5>
      <table class="table table-sm">
        <tr>
          <td><strong>Installed At:</strong></td>
          <td>${tooling.installed_at ? new Date(tooling.installed_at).toLocaleString() : '-'}</td>
        </tr>
        <tr>
          <td><strong>Installed By:</strong></td>
          <td>${escapeHtml(tooling.installed_by || '-')}</td>
        </tr>
        ${tooling.removed_at ? `
          <tr>
            <td><strong>Removed At:</strong></td>
            <td>${new Date(tooling.removed_at).toLocaleString()}</td>
          </tr>
          <tr>
            <td><strong>Removed By:</strong></td>
            <td>${escapeHtml(tooling.removed_by || '-')}</td>
          </tr>
        ` : ''}
      </table>

      ${tooling.notes ? `
        <h5>Notes</h5>
        <p>${escapeHtml(tooling.notes)}</p>
      ` : ''}
    `;

    showModal(modal);
  } catch (error) {
    console.error('Failed to load tool details:', error);
    showNotification('Failed to load tool details', 'danger');
  }
}

// Remove tool from machine
async function removeTool(toolingId) {
  if (!confirm('Are you sure you want to remove this tool from the machine?')) return;

  const reason = prompt('Please provide a reason for removal (optional):');

  try {
    await authenticatedFetch(`/machine-tooling/${toolingId}/remove`, {
      method: 'POST',
      body: JSON.stringify({ reason })
    });

    await loadMachineTooling();
    showNotification('Tool removed successfully', 'success');
  } catch (error) {
    console.error('Failed to remove tool:', error);
    showNotification('Failed to remove tool: ' + (error.message || 'Unknown error'), 'danger');
  }
}

// Helper function to format numbers
function formatNumber(num) {
  if (num === null || num === undefined) return '-';
  return parseFloat(num).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

// Helper function to escape HTML
function escapeHtml(text) {
  if (!text) return '';
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// Initialize tooling when tab is activated
document.addEventListener('DOMContentLoaded', function() {
  // Listen for tab shown event
  const toolingTab = document.querySelector('a[href="#tab-tooling"]');
  if (toolingTab) {
    toolingTab.addEventListener('shown.bs.tab', async function() {
      await initializeToolingMachineFilter();
      await loadMachineTooling();
    });
  }
});

// ============================================================================
// ADD TOOLING PRODUCT FUNCTIONS
// ============================================================================

let categories = [];
let suppliers = [];
let machineTypes = [];

// Open add tooling product modal
async function openAddToolingProductModal() {
  const form = document.getElementById('addToolingProductForm');
  form.reset();

  // Load categories, suppliers, and machine types
  await Promise.all([
    loadCategoriesForTooling(),
    loadSuppliersForTooling(),
    loadMachineTypesForTooling()
  ]);

  // Reset tool life section visibility
  document.getElementById('toolLifeSection').style.display = 'none';
}

// Load categories for dropdown
async function loadCategoriesForTooling() {
  try {
    categories = await authenticatedFetch('/categories');
    const select = document.getElementById('newToolCategory');

    select.innerHTML = '<option value="">No Category</option>' +
      categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
  } catch (error) {
    console.error('Failed to load categories:', error);
  }
}

// Load suppliers for dropdown
async function loadSuppliersForTooling() {
  try {
    suppliers = await authenticatedFetch('/suppliers');
    const select = document.getElementById('newToolSupplier');

    select.innerHTML = '<option value="">No Supplier</option>' +
      suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
  } catch (error) {
    console.error('Failed to load suppliers:', error);
  }
}

// Load machine types for checkboxes
async function loadMachineTypesForTooling() {
  try {
    machineTypes = await authenticatedFetch('/machine-types');
    const container = document.getElementById('newToolMachineTypes');

    if (machineTypes && machineTypes.length > 0) {
      container.innerHTML = machineTypes.map(mt => `
        <div class="form-check">
          <input class="form-check-input" type="checkbox" value="${mt.id}" id="machineType_${mt.id}">
          <label class="form-check-label" for="machineType_${mt.id}">
            ${escapeHtml(mt.name)}
          </label>
        </div>
      `).join('');
    } else {
      container.innerHTML = '<em class="text-muted">No machine types available</em>';
    }
  } catch (error) {
    console.error('Failed to load machine types:', error);
    document.getElementById('newToolMachineTypes').innerHTML = '<em class="text-muted">Error loading machine types</em>';
  }
}

// Toggle tool life fields based on tool type
function toggleToolLifeFields() {
  const toolType = document.getElementById('newToolType').value;
  const toolLifeSection = document.getElementById('toolLifeSection');

  if (toolType === 'consumable_tool') {
    toolLifeSection.style.display = 'block';
    // Make tool life fields required
    document.getElementById('newToolLifeMax').required = true;
    document.getElementById('newToolLifeUnit').required = true;
  } else {
    toolLifeSection.style.display = 'none';
    // Make tool life fields optional
    document.getElementById('newToolLifeMax').required = false;
    document.getElementById('newToolLifeUnit').required = false;
  }
}

// Handle add tooling product form submission
document.getElementById('addToolingProductForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();

  const toolType = document.getElementById('newToolType').value;

  // Build tool specifications object
  const toolSpecs = {};
  if (document.getElementById('newToolSpecDiameter').value) {
    toolSpecs.diameter = document.getElementById('newToolSpecDiameter').value;
  }
  if (document.getElementById('newToolSpecLength').value) {
    toolSpecs.length = document.getElementById('newToolSpecLength').value;
  }
  if (document.getElementById('newToolSpecMaterial').value) {
    toolSpecs.material = document.getElementById('newToolSpecMaterial').value;
  }
  if (document.getElementById('newToolSpecCoating').value) {
    toolSpecs.coating = document.getElementById('newToolSpecCoating').value;
  }

  // Parse additional specs if provided
  const otherSpecs = document.getElementById('newToolSpecOther').value;
  if (otherSpecs) {
    try {
      const parsed = JSON.parse(otherSpecs);
      Object.assign(toolSpecs, parsed);
    } catch (err) {
      showNotification('Invalid JSON format for additional specifications', 'warning');
    }
  }

  // Get selected machine types
  const compatibleMachineTypes = [];
  document.querySelectorAll('#newToolMachineTypes input[type="checkbox"]:checked').forEach(cb => {
    compatibleMachineTypes.push(parseInt(cb.value));
  });

  // Build product data
  const data = {
    // Basic info
    part_number: document.getElementById('newToolPartNumber').value,
    sku: document.getElementById('newToolSKU').value || null,
    description: document.getElementById('newToolDescription').value,
    category_id: document.getElementById('newToolCategory').value || null,

    // Inventory
    quantity_on_hand: parseInt(document.getElementById('newToolQuantity').value) || 0,
    unit_cost: parseFloat(document.getElementById('newToolUnitCost').value) || 0,
    unit_price: parseFloat(document.getElementById('newToolUnitPrice').value) || 0,
    minimum_quantity: parseInt(document.getElementById('newToolMinQuantity').value) || 0,
    reorder_point: parseInt(document.getElementById('newToolReorderPoint').value) || 0,
    supplier_id: document.getElementById('newToolSupplier').value || null,
    location: document.getElementById('newToolLocation').value || null,

    // Tool-specific fields
    tool_type: toolType,

    // Tool life (only for consumable_tool)
    tool_life_max: toolType === 'consumable_tool' ? parseFloat(document.getElementById('newToolLifeMax').value) : null,
    tool_life_unit: toolType === 'consumable_tool' ? document.getElementById('newToolLifeUnit').value : null,
    tool_life_warning_threshold: toolType === 'consumable_tool' ? parseInt(document.getElementById('newToolWarningThreshold').value) : null,

    // Machine compatibility
    compatible_machine_types: compatibleMachineTypes.length > 0 ? compatibleMachineTypes : null,

    // Tool specifications
    tool_specifications: Object.keys(toolSpecs).length > 0 ? toolSpecs : null,

    // Status
    is_active: true,
    unit_of_measure: 'EA',
  };

  try {
    const response = await authenticatedFetch('/products', {
      method: 'POST',
      body: JSON.stringify(data)
    });

    hideModal(document.getElementById('addToolingProductModal'));
    showNotification('Tool product created successfully! SKU: ' + response.sku, 'success');

    // Reload tooling data to show new product
    await loadMachineTooling();
  } catch (error) {
    console.error('Failed to create tool product:', error);
    showNotification('Failed to create tool product: ' + (error.message || 'Unknown error'), 'danger');
  }
});
