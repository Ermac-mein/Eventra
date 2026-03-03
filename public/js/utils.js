// Utility functions

// Format currency
function formatCurrency(amount, currency = '₦') {
  return `${currency} ${amount.toLocaleString()}`;
}

// Format date
function formatDate(date) {
  const options = { year: 'numeric', month: 'long', day: 'numeric' };
  return new Date(date).toLocaleDateString('en-US', options);
}

// Debounce function for search
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

// Validate email
function isValidEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

// Show notification
function showNotification(message, type = 'info') {
  if (typeof Swal !== 'undefined') {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: type === 'error' ? 'error' : type === 'success' ? 'success' : 'info',
      title: message,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      background: '#ffffff',
      color: '#000000',
      customClass: {
        container: 'eventra-toast-container'
      },
      didOpen: (toast) => {
        toast.style.zIndex = '999999'; // Ensure above Google iframe
      }
    });
    return;
  }

  // Fallback to legacy notification if Swal is not loaded
  const notification = document.createElement('div');
  notification.className = `notification notification-${type}`;
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem 1.5rem;
    background-color: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3'};
    color: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 999999;
    animation: slideIn 0.3s ease;
  `;

  document.body.appendChild(notification);

  // Remove after 3 seconds
  setTimeout(() => {
    notification.style.animation = 'slideOut 0.3s ease';
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}



// Auth helpers - Rely on window.storage for consistency
function getRoleKeys() {
    return window.storage ? window.storage.getRoleKeys() : { user: 'user', token: 'auth_token' };
}

function getBasePath() {
    const path = window.location.pathname;
    // Current detection: if in /public/pages/ or /client/pages/ or /admin/pages/
    if (path.includes('/pages/')) return '../../';
    // If in /admin/ or /client/ root
    if (path.includes('/admin/') || path.includes('/client/')) return '../';
    // If in root or /public/ root
    return './';
}

function isAuthenticated() {
  if (!window.storage) return false;
  const user = window.storage.getUser();
  const token = window.storage.getToken();
  return !!(user && token);
}

// Trigger sync on load - Moved to AuthController.init() in main.js
// document.addEventListener('DOMContentLoaded', syncSession);

function handleAuthRedirect(targetURL) {
  if (!isAuthenticated()) {
    const effectiveTarget = targetURL || window.location.href;
    window.storage.set('redirect_after_login', effectiveTarget);
    
    // Use origin-based absolute URLs to avoid broken relative path resolution
    const origin = window.location.origin;
    if (effectiveTarget.includes('/admin/')) {
      window.location.href = origin + '/admin/pages/adminLogin.html';
    } else if (effectiveTarget.includes('/client/')) {
      window.location.href = origin + '/client/pages/clientLogin.html';
    } else {
      window.location.href = origin + '/public/pages/index.html?trigger=login';
    }
    return false;
  }
  return true;
}

// Centralized API Wrapper
async function apiFetch(url, options = {}) {
  // Ensure credentials are included by default for session support
  if (!options.credentials) options.credentials = 'include';
  
  // Add Portal Identity Header for unambiguous session resolution
  const path = window.location.pathname;
  let portal = 'user';
  if (path.includes('/admin/')) portal = 'admin';
  else if (path.includes('/client/')) portal = 'client';
  
  // Prepare headers
  const headers = {
    'X-Eventra-Portal': portal,
    ...options.headers
  };

  // Add Authorization header if token exists (Phase 1.5 Audit Fix)
  const token = window.storage ? window.storage.getToken() : null;
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }
  
  options.headers = headers;
  
  try {
    const response = await fetch(url, options);
    
    // Handle 401 (Unauthorized) or 403 (Forbidden) indicating session expiration
    if (response.status === 401 || response.status === 403) {
      // Skip redirect for login endpoints themselves to avoid infinite loops
      if (!url.includes('login.php') && !url.includes('google-handler.php') && !url.includes('check-session.php')) {
        const path = window.location.pathname;
        const origin = window.location.origin;
        
        let loginPage;
        if (path.includes('/admin/')) {
          loginPage = origin + '/admin/pages/adminLogin.html';
        } else if (path.includes('/client/')) {
          loginPage = origin + '/client/pages/clientLogin.html';
        } else {
          loginPage = origin + '/public/pages/index.html';
        }
        
        // Prevent redirect loop if we are already on the potential login/portal page
        if (path === new URL(loginPage).pathname || (path.includes('index.html') && loginPage.includes('index.html'))) {
           if (window.storage) window.storage.clearRoleSessions();
           return response; // Return response so caller can handle it or let it fail gracefully
        }

        // Add error param for feedback
        const finalRedirect = loginPage + (loginPage.includes('?') ? '&' : '?') + 'error=session_timeout' + (loginPage.includes('index.html') ? '&trigger=login' : '');
        
        // Clear stale local data
        if (window.storage) window.storage.clearRoleSessions();
        
        window.location.href = finalRedirect;
        return null;
      }
    }
    
    return response;
  } catch (error) {
    console.error('API Fetch Error:', error);
    throw error;
  }
}

// Export utilities
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    formatCurrency,
    formatDate,
    debounce,
    isValidEmail,
    showNotification,
    getRoleKeys,
    isAuthenticated,
    handleAuthRedirect,
    apiFetch
  };
}
