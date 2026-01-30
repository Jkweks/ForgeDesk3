<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'ForgeDesk')</title>
  <link href="{{ asset('assets/tabler/css/tabler.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-flags.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-socials.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-payments.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-vendors.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-marketing.min.css') }}" rel="stylesheet">
  <link href="{{ asset('assets/tabler/css/tabler-themes.min.css') }}" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css" rel="stylesheet">
  <style>
    @import url("https://rsms.me/inter/inter.css");
    .status-badge { font-size: 0.75rem; padding: 0.25rem 0.5rem; }
    .table-actions { white-space: nowrap; }
    .login-container {
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    #app { display: none; }
    #app.active { display: block; }
    #loginPage { display: none; }
    #loginPage.active { display: flex; }
    .loading { text-align: center; padding: 2rem; }
    .modal-body .form-label.required:after {
      content: " *";
      color: #d63939;
    }
    .alert.position-fixed {
      animation: slideIn 0.3s ease-out;
    }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }

    /* Modal scrolling styles */
    .modal-content {
      max-height: 90vh;
      display: flex;
      flex-direction: column;
    }
    .modal-body {
      overflow-y: auto;
      max-height: calc(90vh - 120px);
      scrollbar-width: thin;
      scrollbar-color: rgba(99, 102, 241, 0.5) rgba(0, 0, 0, 0.1);
    }
    .modal-body::-webkit-scrollbar {
      width: 8px;
    }
    .modal-body::-webkit-scrollbar-track {
      background: rgba(0, 0, 0, 0.05);
      border-radius: 4px;
    }
    .modal-body::-webkit-scrollbar-thumb {
      background: rgba(99, 102, 241, 0.5);
      border-radius: 4px;
      transition: background 0.2s ease;
    }
    .modal-body::-webkit-scrollbar-thumb:hover {
      background: rgba(99, 102, 241, 0.8);
    }
    .modal-header,
    .modal-footer {
      flex-shrink: 0;
    }

    /* iPad-specific modal widths - make modals wider on iPad devices */
    @media only screen
      and (min-width: 768px)
      and (max-width: 1024px) {
      /* Target all modal sizes on iPad */
      .modal-dialog {
        max-width: 90% !important;
      }

      .modal-dialog.modal-sm {
        max-width: 70% !important;
      }

      .modal-dialog.modal-lg {
        max-width: 90% !important;
      }

      .modal-dialog.modal-xl {
        max-width: 95% !important;
      }
    }

    /* iPad Pro specific adjustments */
    @media only screen
      and (min-width: 1024px)
      and (max-width: 1366px)
      and (-webkit-min-device-pixel-ratio: 1.5) {
      .modal-dialog {
        max-width: 85% !important;
      }

      .modal-dialog.modal-lg {
        max-width: 85% !important;
      }

      .modal-dialog.modal-xl {
        max-width: 90% !important;
      }
    }

    @yield('styles')
  </style>
</head>
<body>
  <script src="{{ asset('assets/tabler/js/tabler-theme.min.js') }}"></script>

  <!-- Login Page -->
  <div id="loginPage" class="login-container">
    <div class="card" style="width: 100%; max-width: 400px;">
      <div class="card-body">
        <h2 class="text-center mb-4">ForgeDesk</h2>
        <form id="loginForm">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="loginEmail" value="admin@forgedesk.local" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" id="loginPassword" value="password" required>
          </div>
          <div id="loginError" class="alert alert-danger" style="display: none;"></div>
          <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Main Application -->
  <div id="app" class="page">
    @include('partials.header')
    @include('partials.navigation')

    <!-- Page Content -->
    @yield('content')
  </div>

  <!-- Scripts -->
  <script src="{{ asset('assets/tabler/js/tabler.min.js') }}"></script>

  @include('partials.auth-scripts')

  @stack('scripts')
</body>
</html>
