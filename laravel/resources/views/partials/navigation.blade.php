<div class="navbar-expand-md">
  <div class="collapse navbar-collapse" id="navbar-menu">
    <div class="navbar">
      <div class="container-xl">
        <div class="row flex-column flex-md-row flex-fill align-items-center">
          <div class="col">
            <nav aria-label="Primary">
              <ul class="navbar-nav">
                <li class="nav-item {{ Request::is('/') ? 'active' : '' }}">
                  <a class="nav-link" href="/" {{ Request::is('/') ? 'aria-current=page' : '' }}>
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M5 12l-2 0l9 -9l9 9l-2 0" /><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7" /><path d="M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6" /></svg>
                    </span>
                    <span class="nav-link-title">Dashboard</span>
                  </a>
                </li>
                <li class="nav-item dropdown">
                  <a class="nav-link dropdown-toggle" href="#navbar-inventory" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /></svg>
                    </span>
                    <span class="nav-link-title">Inventory</span>
                  </a>
                  <div class="dropdown-menu">
                    <a class="dropdown-item" href="/">All Products</a>
                    <a class="dropdown-item" href="#">Low Stock</a>
                    <a class="dropdown-item" href="#">Critical Stock</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/categories">Categories</a>
                    <a class="dropdown-item" href="/suppliers">Suppliers</a>
                  </div>
                </li>
                <li class="nav-item {{ Request::is('reports') ? 'active' : '' }}">
                  <a class="nav-link" href="/reports" {{ Request::is('reports') ? 'aria-current=page' : '' }}>
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 12m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M9 8m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M15 4m0 1a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" /><path d="M4 20l14 0" /></svg>
                    </span>
                    <span class="nav-link-title">Reports</span>
                  </a>
                </li>
                <li class="nav-item dropdown {{ Request::is('maintenance*') ? 'active' : '' }}">
                  <a class="nav-link dropdown-toggle" href="#navbar-maintenance" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                    <span class="nav-link-icon d-md-none d-lg-inline-block">
                      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 10h3v-3l-3.5 -3.5a6 6 0 0 1 8 8l6 6a2 2 0 0 1 -3 3l-6 -6a6 6 0 0 1 -8 -8l3.5 3.5" /></svg>
                    </span>
                    <span class="nav-link-title">Maintenance</span>
                  </a>
                  <div class="dropdown-menu">
                    <a class="dropdown-item" href="/maintenance">Maintenance Hub</a>
                    <a class="dropdown-item" href="/maintenance#tab-machines">Machines</a>
                    <a class="dropdown-item" href="/maintenance#tab-tasks">Tasks</a>
                    <a class="dropdown-item" href="/maintenance#tab-records">Service Log</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/maintenance#tab-assets">Assets</a>
                  </div>
                </li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
