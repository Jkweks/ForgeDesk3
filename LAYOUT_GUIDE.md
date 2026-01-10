# ForgeDesk Layout System

## Overview

The ForgeDesk application uses **Blade Layouts** to maintain consistent navigation, header, and authentication across all pages. This is Laravel's equivalent to PHP includes.

## Structure

```
resources/views/
├── layouts/
│   └── app.blade.php          # Master layout with common structure
├── partials/
│   ├── header.blade.php       # Top navigation bar
│   ├── navigation.blade.php   # Main menu navigation
│   └── auth-scripts.blade.php # Shared authentication & modal helpers
├── dashboard.blade.php         # Inventory dashboard page
└── maintenance.blade.php       # Maintenance hub page
```

## How It Works

### 1. Master Layout (`layouts/app.blade.php`)
The master layout contains:
- HTML structure (head, body)
- CSS and JavaScript includes
- Login page structure
- Shared header and navigation (via @include)
- Content area (via @yield)
- Script area (via @stack)

### 2. Partials (Reusable Components)
- **header.blade.php**: Top bar with logo, notifications, user menu
- **navigation.blade.php**: Main navigation menu (automatically highlights active page)
- **auth-scripts.blade.php**: Login/logout logic, modal helpers, API helper

### 3. Page Views (Extend the Layout)
Each page extends the master layout and provides its specific content.

## Creating a New Page

Here's how to create a new page using the layout:

```blade
@extends('layouts.app')

@section('title', 'My Page Title')

@section('styles')
/* Custom CSS for this page only */
.my-custom-class { color: blue; }
@endsection

@section('content')
  <div class="page-wrapper">
    <div class="page-header">
      <div class="container-xl">
        <h1>My Page</h1>
      </div>
    </div>
    <div class="page-body">
      <div class="container-xl">
        <!-- Your page content here -->
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  @include('partials.auth-scripts')
  <script>
    // Your page-specific JavaScript here
    if (authToken) {
      // Load page data
    }
  </script>
@endpush
```

## Key Blade Directives

- `@extends('layouts.app')` - Use the master layout
- `@section('name')...@endsection` - Define content for a section
- `@yield('name')` - Output a section (used in layouts)
- `@include('partial.name')` - Include a partial template
- `@push('name')...@endpush` - Add to a stack (for scripts)
- `@stack('name')` - Output stacked content (used in layouts)

## Benefits

✅ **Single Source of Truth**: Header, navigation, and authentication logic in one place
✅ **Consistent UI**: All pages automatically have the same look and feel
✅ **Easy Updates**: Change navigation once, updates everywhere
✅ **Auto-Active States**: Navigation automatically highlights the current page
✅ **Shared Authentication**: One login works across all modules
✅ **DRY Principle**: Don't Repeat Yourself - write common code once

## Auto-Active Navigation

The navigation automatically highlights the active page using Laravel's `Request::is()` helper:

```blade
<li class="nav-item {{ Request::is('/') ? 'active' : '' }}">
  <a class="nav-link" href="/">Dashboard</a>
</li>

<li class="nav-item {{ Request::is('maintenance*') ? 'active' : '' }}">
  <a class="nav-link" href="/maintenance">Maintenance</a>
</li>
```

## Shared Authentication

All pages use the same `authToken` stored in `localStorage`. The auth-scripts partial handles:
- Login form submission
- Logout functionality
- Token storage and retrieval
- Showing/hiding login vs app views
- API calls with authentication headers

## Adding to the Navigation

To add a new menu item, edit `partials/navigation.blade.php`:

```blade
<li class="nav-item {{ Request::is('reports*') ? 'active' : '' }}">
  <a class="nav-link" href="/reports">
    <span class="nav-link-icon d-md-none d-lg-inline-block">
      <!-- SVG icon here -->
    </span>
    <span class="nav-link-title">Reports</span>
  </a>
</li>
```

This automatically appears on ALL pages that extend the layout!

## Migration Path

To convert existing full-page views to use the layout:

1. Create new view file (or edit existing)
2. Remove everything except the page-specific content
3. Wrap in `@extends('layouts.app')` and `@section('content')`
4. Move page-specific scripts to `@push('scripts')`
5. Include `@include('partials.auth-scripts')` in scripts section

The layout system makes ForgeDesk scalable - you can add inventory, maintenance, reports, analytics, and any future modules while maintaining a consistent experience across the entire application!
