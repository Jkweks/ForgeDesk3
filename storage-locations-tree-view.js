// Hierarchical Storage Locations Tree View JavaScript
// Add this to storage-locations.blade.php

// Add these CSS styles in a <style> tag
const treeViewStyles = `
<style>
  .tree-node {
    padding: 4px 0;
  }
  .tree-node-content {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    border-radius: 4px;
    transition: background 0.2s;
  }
  .tree-node-content:hover {
    background: #f8f9fa;
  }
  .tree-node-toggle {
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
    cursor: pointer;
    user-select: none;
    font-weight: bold;
  }
  .tree-node-toggle.empty {
    visibility: hidden;
  }
  .tree-node-icon {
    margin-right: 8px;
    color: #6c757d;
  }
  .tree-children {
    margin-left: 24px;
    border-left: 1px dashed #dee2e6;
    padding-left: 8px;
  }
  .tree-children.collapsed {
    display: none;
  }
  .tree-node-actions {
    margin-left: auto;
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.2s;
  }
  .tree-node-content:hover .tree-node-actions {
    opacity: 1;
  }
  .type-badge {
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 8px;
  }
</style>
`;

// Add to your existing JavaScript variables
let currentViewMode = 'tree';
let locationTree = [];

// Replace your loadLocations function with this:
async function loadLocationsWithTree() {
  try {
    currentLocations = await authenticatedFetch(`/storage-locations-stats`);
    locationTree = await authenticatedFetch(`/storage-locations-tree`);

    // Calculate aggregated stats
    const totalLocations = currentLocations.length;
    const inUse = currentLocations.filter(l => l.stats && l.stats.products_count > 0).length;
    const totalCapacity = currentLocations.reduce((sum, l) => sum + ((l.stats && l.stats.total_quantity) || 0), 0);
    const utilization = totalLocations > 0 ? ((inUse / totalLocations) * 100).toFixed(1) : 0;

    document.getElementById('statTotalLocations').textContent = totalLocations.toLocaleString();
    document.getElementById('statInUse').textContent = inUse.toLocaleString();
    document.getElementById('statTotalCapacity').textContent = totalCapacity.toLocaleString();
    document.getElementById('statUtilization').textContent = `${utilization}%`;

    renderCurrentView();
    populateParentDropdown();

    document.getElementById('loadingIndicator').style.display = 'none';
  } catch (error) {
    console.error('Error loading locations:', error);
    showNotification('Failed to load locations', 'danger');
  }
}

// Add view mode toggle event listeners in DOMContentLoaded:
document.querySelectorAll('input[name="view-mode"]').forEach(radio => {
  radio.addEventListener('change', (e) => {
    currentViewMode = e.target.id === 'view-tree' ? 'tree' : 'list';
    renderCurrentView();
  });
});

// New function: Render current view
function renderCurrentView() {
  if (currentViewMode === 'tree') {
    document.getElementById('treeViewContainer').style.display = 'block';
    document.getElementById('locationsTableContainer').style.display = 'none';
    renderTreeView(locationTree);
  } else {
    document.getElementById('treeViewContainer').style.display = 'none';
    document.getElementById('locationsTableContainer').style.display = 'block';
    renderLocationsTable(currentLocations);
  }
}

// New function: Render tree view
function renderTreeView(nodes) {
  const container = document.getElementById('locationsTree');

  if (!nodes || nodes.length === 0) {
    container.innerHTML = '<div class="text-center text-muted py-5">No locations found. Click "Add Location" to create one.</div>';
    return;
  }

  container.innerHTML = nodes.map(node => renderTreeNode(node)).join('');
}

// New function: Render individual tree node
function renderTreeNode(node) {
  const hasChildren = node.children && node.children.length > 0;
  const location = currentLocations.find(l => l.id === node.id);
  const stats = location?.stats || { products_count: 0, total_quantity: 0, total_value: 0 };

  const toggleIcon = hasChildren ? '▼' : '';
  const typeIcons = {
    aisle: 'ti-road',
    rack: 'ti-building-warehouse',
    shelf: 'ti-layout-rows',
    bin: 'ti-box',
    warehouse: 'ti-building',
    zone: 'ti-map-pin',
    other: 'ti-dots'
  };
  const icon = typeIcons[node.type] || 'ti-map-pin';

  const statusBadge = stats.products_count > 0
    ? '<span class="badge bg-success-lt">In Use</span>'
    : '<span class="badge bg-secondary-lt">Empty</span>';

  return `
    <div class="tree-node" data-id="${node.id}">
      <div class="tree-node-content">
        <span class="tree-node-toggle ${!hasChildren ? 'empty' : ''}" onclick="toggleTreeNode(event, ${node.id})">
          ${toggleIcon}
        </span>
        <i class="ti ${icon} tree-node-icon"></i>
        <div class="flex-fill">
          <strong>${escapeHtml(node.name)}</strong>
          <span class="type-badge badge bg-azure-lt">${node.type}</span>
          ${node.code ? `<span class="text-muted ms-2">${escapeHtml(node.code)}</span>` : ''}
          <div class="small text-muted">
            ${stats.products_count} products | ${stats.total_quantity} units | $${stats.total_value.toLocaleString(undefined, {minimumFractionDigits: 2})}
          </div>
        </div>
        ${statusBadge}
        <div class="tree-node-actions">
          <button class="btn btn-sm btn-icon btn-ghost-primary" onclick="addChildLocation(${node.id}, '${escapeHtml(node.name)}')" title="Add Child">
            <i class="ti ti-plus"></i>
          </button>
          <button class="btn btn-sm btn-icon btn-ghost-secondary" onclick="editLocation(${node.id})" title="Edit">
            <i class="ti ti-edit"></i>
          </button>
          <button class="btn btn-sm btn-icon btn-ghost-info" onclick="viewLocationDetails(${node.id})" title="View">
            <i class="ti ti-eye"></i>
          </button>
          <button class="btn btn-sm btn-icon btn-ghost-danger" onclick="deleteLocation(${node.id}, '${escapeHtml(node.name)}')" title="Delete">
            <i class="ti ti-trash"></i>
          </button>
        </div>
      </div>
      ${hasChildren ? `
        <div class="tree-children" id="children-${node.id}">
          ${node.children.map(child => renderTreeNode(child)).join('')}
        </div>
      ` : ''}
    </div>
  `;
}

// New function: Toggle tree node
function toggleTreeNode(event, nodeId) {
  event.stopPropagation();
  const childrenEl = document.getElementById(`children-${nodeId}`);
  const toggle = event.target;

  if (childrenEl) {
    childrenEl.classList.toggle('collapsed');
    toggle.textContent = childrenEl.classList.contains('collapsed') ? '▶' : '▼';
  }
}

// New function: Add child location
function addChildLocation(parentId, parentName) {
  editingLocationId = null;
  document.getElementById('locationForm').reset();
  document.getElementById('locationModalTitle').textContent = `Add Child Location under "${parentName}"`;
  document.getElementById('locationName').value = '';
  document.getElementById('locationParent').value = parentId;
  document.getElementById('locationActive').checked = true;

  // Suggest type based on parent
  const parent = currentLocations.find(l => l.id === parentId);
  if (parent) {
    const typeHierarchy = { aisle: 'rack', rack: 'shelf', shelf: 'bin' };
    const suggestedType = typeHierarchy[parent.type] || 'bin';
    document.getElementById('locationType').value = suggestedType;
  }

  showModal(document.getElementById('addLocationModal'));
}

// New function: Populate parent dropdown
function populateParentDropdown() {
  const select = document.getElementById('locationParent');
  const currentValue = select.value;

  select.innerHTML = '<option value="">None (Root Level)</option>';

  // Flatten tree for dropdown
  function addOptions(nodes, indent = '') {
    nodes.forEach(node => {
      const option = document.createElement('option');
      option.value = node.id;
      option.textContent = `${indent}${node.name} (${node.type})`;
      select.appendChild(option);

      if (node.children && node.children.length > 0) {
        addOptions(node.children, indent + '  ');
      }
    });
  }

  addOptions(locationTree);

  if (currentValue) {
    select.value = currentValue;
  }
}

// Update your existing renderLocationsTable to show full_path:
// Replace the name cell with:
// `<td>
//   <div class="d-flex align-items-center">
//     <span class="avatar avatar-sm me-2"><i class="ti ti-map-pin"></i></span>
//     <div>
//       <strong>${location.full_path || location.name}</strong>
//       ${location.code ? `<br><small class="text-muted">${location.code}</small>` : ''}
//     </div>
//   </div>
// </td>
// <td><span class="badge bg-azure-lt">${location.type}</span></td>`

// Update handleLocationFormSubmit to include parent_id:
// Add this line when building the data object:
// parent_id: formData.get('parent_id') || null,
