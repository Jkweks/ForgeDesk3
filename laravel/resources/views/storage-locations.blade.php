@extends('layouts.app')

@section('title', 'Storage Locations')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Storage Locations</h1>
            <p class="text-gray-600 mt-1">Manage hierarchical warehouse locations (Aisle → Rack → Shelf → Bin)</p>
        </div>
        <button onclick="openAddLocationModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add Location
        </button>
    </div>

    <!-- Search Bar -->
    <div class="mb-6">
        <input type="text" id="searchInput" placeholder="Search locations..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <h2 class="text-lg font-semibold mb-3">Quick Create</h2>
        <div class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Path (e.g., 1.2.3.4)</label>
                <input type="text" id="quickCreatePath" placeholder="Enter location path" class="w-full px-3 py-2 border border-gray-300 rounded">
            </div>
            <button onclick="bulkCreateFromPath()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                Create Hierarchy
            </button>
        </div>
        <p class="text-sm text-gray-500 mt-2">Creates nested locations: 1 = Aisle 1, 2 = Rack 2, 3 = Shelf 3, 4 = Bin 4</p>
    </div>

    <!-- Location Tree -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h2 class="text-lg font-semibold mb-4">Location Hierarchy</h2>
        <div id="locationTree" class="space-y-2">
            <div class="text-center text-gray-500 py-8">Loading locations...</div>
        </div>
    </div>
</div>

<!-- Add/Edit Location Modal -->
<div id="locationModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modalTitle" class="text-2xl font-bold">Add Location</h2>
                <button onclick="closeLocationModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="locationForm" class="space-y-4">
                <input type="hidden" id="locationId">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" id="locationName" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Aisle</label>
                        <input type="text" id="locationAisle" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rack</label>
                        <input type="text" id="locationRack" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Shelf</label>
                        <input type="text" id="locationShelf" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bin</label>
                        <input type="text" id="locationBin" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Parent Location</label>
                    <select id="locationParent" class="w-full px-3 py-2 border border-gray-300 rounded">
                        <option value="">None (Root Level)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="locationDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" id="locationIsActive" checked class="mr-2">
                            <span class="text-sm font-medium text-gray-700">Active</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" id="locationSortOrder" value="0" class="w-full px-3 py-2 border border-gray-300 rounded">
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeLocationModal()" class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        Save Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let locationsData = [];

// Load locations on page load
document.addEventListener('DOMContentLoaded', function() {
    loadLocations();
    setupSearch();
});

// Load all locations
async function loadLocations() {
    try {
        const response = await fetch('/api/v1/storage-locations-tree', {
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`,
                'Accept': 'application/json'
            }
        });

        if (!response.ok) throw new Error('Failed to load locations');

        locationsData = await response.json();
        renderLocationTree(locationsData);
        populateParentDropdown();
    } catch (error) {
        console.error('Error loading locations:', error);
        document.getElementById('locationTree').innerHTML = '<div class="text-red-600 text-center py-4">Error loading locations</div>';
    }
}

// Render location tree
function renderLocationTree(locations, level = 0) {
    if (!locations || locations.length === 0) {
        return '<div class="text-gray-500 text-center py-4">No locations found</div>';
    }

    return locations.map(location => {
        const indent = level * 24;
        const stats = location.total_quantity ? `<span class="text-sm text-gray-600">(${location.total_quantity} units)</span>` : '';
        const hasChildren = location.children && location.children.length > 0;

        return `
            <div class="border-l-2 border-gray-200" style="margin-left: ${indent}px">
                <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded">
                    <div class="flex items-center gap-2">
                        ${hasChildren ? '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>' : '<svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>'}
                        <span class="font-medium">${location.name}</span>
                        ${stats}
                        ${location.slug ? `<span class="text-xs text-gray-400">[${location.slug}]</span>` : ''}
                        ${!location.is_active ? '<span class="text-xs bg-gray-200 px-2 py-1 rounded">Inactive</span>' : ''}
                    </div>
                    <div class="flex gap-2">
                        <button onclick="viewLocationDetails(${location.id})" class="text-blue-600 hover:text-blue-800" title="View Details">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                        </button>
                        <button onclick="editLocation(${location.id})" class="text-green-600 hover:text-green-800" title="Edit">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </button>
                        <button onclick="deleteLocation(${location.id})" class="text-red-600 hover:text-red-800" title="Delete">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                </div>
                ${hasChildren ? renderLocationTree(location.children, level + 1) : ''}
            </div>
        `;
    }).join('');
}

// Update the tree display
function updateTreeDisplay() {
    document.getElementById('locationTree').innerHTML = renderLocationTree(locationsData);
}

// Populate parent dropdown
function populateParentDropdown() {
    const select = document.getElementById('locationParent');
    select.innerHTML = '<option value="">None (Root Level)</option>';

    function addOptions(locations, prefix = '') {
        locations.forEach(location => {
            const option = document.createElement('option');
            option.value = location.id;
            option.textContent = prefix + location.name;
            select.appendChild(option);

            if (location.children && location.children.length > 0) {
                addOptions(location.children, prefix + '  ');
            }
        });
    }

    addOptions(locationsData);
}

// Open add location modal
function openAddLocationModal() {
    document.getElementById('modalTitle').textContent = 'Add Location';
    document.getElementById('locationForm').reset();
    document.getElementById('locationId').value = '';
    document.getElementById('locationModal').classList.remove('hidden');
}

// Close location modal
function closeLocationModal() {
    document.getElementById('locationModal').classList.add('hidden');
}

// Edit location
async function editLocation(id) {
    try {
        const response = await fetch(`/api/v1/storage-locations/${id}`, {
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`,
                'Accept': 'application/json'
            }
        });

        if (!response.ok) throw new Error('Failed to load location');

        const location = await response.json();

        document.getElementById('modalTitle').textContent = 'Edit Location';
        document.getElementById('locationId').value = location.id;
        document.getElementById('locationName').value = location.name;
        document.getElementById('locationAisle').value = location.aisle || '';
        document.getElementById('locationRack').value = location.rack || '';
        document.getElementById('locationShelf').value = location.shelf || '';
        document.getElementById('locationBin').value = location.bin || '';
        document.getElementById('locationParent').value = location.parent_id || '';
        document.getElementById('locationDescription').value = location.description || '';
        document.getElementById('locationIsActive').checked = location.is_active;
        document.getElementById('locationSortOrder').value = location.sort_order;

        document.getElementById('locationModal').classList.remove('hidden');
    } catch (error) {
        alert('Error loading location: ' + error.message);
    }
}

// Save location (create or update)
document.getElementById('locationForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const id = document.getElementById('locationId').value;
    const data = {
        name: document.getElementById('locationName').value,
        aisle: document.getElementById('locationAisle').value || null,
        rack: document.getElementById('locationRack').value || null,
        shelf: document.getElementById('locationShelf').value || null,
        bin: document.getElementById('locationBin').value || null,
        parent_id: document.getElementById('locationParent').value || null,
        description: document.getElementById('locationDescription').value || null,
        is_active: document.getElementById('locationIsActive').checked,
        sort_order: parseInt(document.getElementById('locationSortOrder').value)
    };

    try {
        const url = id ? `/api/v1/storage-locations/${id}` : '/api/v1/storage-locations';
        const method = id ? 'PUT' : 'POST';

        const response = await fetch(url, {
            method: method,
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to save location');
        }

        closeLocationModal();
        loadLocations();
        alert('Location saved successfully!');
    } catch (error) {
        alert('Error saving location: ' + error.message);
    }
});

// Delete location
async function deleteLocation(id) {
    if (!confirm('Are you sure you want to delete this location?')) return;

    try {
        const response = await fetch(`/api/v1/storage-locations/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`,
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to delete location');
        }

        loadLocations();
        alert('Location deleted successfully!');
    } catch (error) {
        alert('Error deleting location: ' + error.message);
    }
}

// Bulk create from path
async function bulkCreateFromPath() {
    const path = document.getElementById('quickCreatePath').value.trim();
    if (!path) {
        alert('Please enter a path');
        return;
    }

    try {
        const response = await fetch('/api/v1/storage-locations-bulk-create', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ path: path })
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Failed to create locations');
        }

        document.getElementById('quickCreatePath').value = '';
        loadLocations();
        alert('Location hierarchy created successfully!');
    } catch (error) {
        alert('Error creating locations: ' + error.message);
    }
}

// View location details
async function viewLocationDetails(id) {
    try {
        const response = await fetch(`/api/v1/storage-locations/${id}/statistics`, {
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`,
                'Accept': 'application/json'
            }
        });

        if (!response.ok) throw new Error('Failed to load location details');

        const stats = await response.json();

        alert(`Location: ${stats.location_name}\n\nFull Path: ${stats.full_path}\n\nTotal Quantity: ${stats.total_quantity}\nTotal Committed: ${stats.total_committed}\nTotal Available: ${stats.total_available}\n\nProducts: ${stats.product_count}\nChild Locations: ${stats.child_location_count}\nLevel: ${stats.level}`);
    } catch (error) {
        alert('Error loading details: ' + error.message);
    }
}

// Search functionality
function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', debounce(performSearch, 300));
}

async function performSearch() {
    const query = document.getElementById('searchInput').value.trim();

    if (query.length < 2) {
        updateTreeDisplay();
        return;
    }

    try {
        const response = await fetch('/api/v1/storage-locations-search', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${getAuthToken()}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ query: query })
        });

        if (!response.ok) throw new Error('Search failed');

        const results = await response.json();
        document.getElementById('locationTree').innerHTML = renderLocationTree(results);
    } catch (error) {
        console.error('Search error:', error);
    }
}

// Utility functions
function getAuthToken() {
    return localStorage.getItem('auth_token') || '';
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
@endsection
