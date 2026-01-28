<script>
  const API_BASE = '/api/v1';
  let authToken = localStorage.getItem('authToken');

  // Bootstrap Modal Helper - handles initialization safely
  function showModal(modalElement) {
    try {
      if (window.bootstrap && window.bootstrap.Modal) {
        const modal = new window.bootstrap.Modal(modalElement);
        modal.show();
        return true;
      }
    } catch (e) {
      console.warn('Bootstrap not available, using fallback:', e);
    }

    // Fallback: manually show modal without Bootstrap
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    backdrop.id = 'modal-backdrop-' + modalElement.id;
    document.body.appendChild(backdrop);

    modalElement.style.display = 'block';
    modalElement.classList.add('show');
    modalElement.removeAttribute('aria-hidden');
    modalElement.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');

    // Close on backdrop click
    backdrop.addEventListener('click', () => hideModal(modalElement));

    // Add close button listeners
    const closeButtons = modalElement.querySelectorAll('[data-bs-dismiss="modal"]');
    closeButtons.forEach(btn => {
      btn.onclick = () => hideModal(modalElement);
    });

    return false;
  }

  function hideModal(modalElement) {
    try {
      if (window.bootstrap && window.bootstrap.Modal) {
        const modal = window.bootstrap.Modal.getInstance(modalElement);
        if (modal) {
          modal.hide();
          return;
        }
      }
    } catch (e) {
      console.warn('Bootstrap not available for hide, using fallback:', e);
    }

    // Fallback: manually hide modal
    modalElement.style.display = 'none';
    modalElement.classList.remove('show');
    modalElement.setAttribute('aria-hidden', 'true');
    modalElement.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');

    const backdrop = document.getElementById('modal-backdrop-' + modalElement.id);
    if (backdrop) {
      backdrop.remove();
    }
  }

  // API Helper
  async function apiCall(endpoint, options = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      ...options.headers
    };

    if (authToken) {
      headers['Authorization'] = `Bearer ${authToken}`;
    }

    const response = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers
    });

    return response;
  }

  // Authenticated Fetch - returns JSON directly (convenience wrapper)
  async function authenticatedFetch(endpoint, options = {}) {
    const response = await apiCall(endpoint, options);

    if (!response.ok) {
      // Handle 401 Unauthorized - redirect to login
      if (response.status === 401) {
        localStorage.removeItem('authToken');
        authToken = null;
        showLogin();
        showNotification('Session expired. Please login again.', 'warning');
        throw new Error('Session expired');
      }

      const error = await response.json().catch(() => ({ message: 'Request failed' }));

      // Log full error details to console for debugging
      console.error('API Error Details:', {
        endpoint: endpoint,
        status: response.status,
        error: error.error,
        message: error.message,
        line: error.line,
        file: error.file,
        fullResponse: error
      });

      throw new Error(error.message || `HTTP ${response.status}`);
    }

    return response.json();
  }

  // Authentication
  function showApp() {
    document.getElementById('loginPage').classList.remove('active');
    document.getElementById('app').classList.add('active');
  }

  function showLogin() {
    document.getElementById('loginPage').classList.add('active');
    document.getElementById('app').classList.remove('active');
  }

  // Login Form
  document.getElementById('loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;

    try {
      const response = await fetch('/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password })
      });

      if (response.ok) {
        const data = await response.json();
        authToken = data.token;
        localStorage.setItem('authToken', authToken);
        location.reload(); // Reload to initialize the app
      } else {
        document.getElementById('loginError').textContent = 'Invalid credentials';
        document.getElementById('loginError').style.display = 'block';
      }
    } catch (error) {
      console.error('Login error:', error);
      document.getElementById('loginError').textContent = 'Login failed';
      document.getElementById('loginError').style.display = 'block';
    }
  });

  // Logout
  document.getElementById('logoutBtn')?.addEventListener('click', async (e) => {
    e.preventDefault();
    localStorage.removeItem('authToken');
    authToken = null;
    location.reload();
  });

  // Show app or login based on auth state
  if (authToken) {
    showApp();
  } else {
    showLogin();
  }

  // Notification helper
  function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.textContent = message;
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
  }
</script>
