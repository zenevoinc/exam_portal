// Global Exam Portal JavaScript
console.log('Exam Portal script loaded.');

// Exam protection - prevent accidental page refresh during exam
window.addEventListener('beforeunload', function (e) {
  const flag = document.body.getAttribute('data-in-exam');
  if (flag === '1') {
    e.preventDefault();
    e.returnValue = 'Are you sure you want to leave? Your progress will be saved but you may lose time.';
  }
});

// Toast notification system
const showToast = (message, type = 'info', duration = 3000) => {
  // Remove existing toasts
  const existingToasts = document.querySelectorAll('.toast-notification');
  existingToasts.forEach(toast => toast.remove());
  
  const toast = document.createElement('div');
  toast.className = `toast-notification alert alert-${type} position-fixed`;
  toast.style.cssText = `
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    animation: slideInRight 0.3s ease;
  `;
  
  toast.innerHTML = `
    <div class="d-flex align-items-center">
      <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
      <span>${message}</span>
      <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
    </div>
  `;
  
  document.body.appendChild(toast);
  
  // Auto-remove after duration
  setTimeout(() => {
    if (toast.parentElement) {
      toast.style.animation = 'slideOutRight 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }
  }, duration);
};

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
  @keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
  @keyframes slideOutRight {
    from { transform: translateX(0); opacity: 1; }
    to { transform: translateX(100%); opacity: 0; }
  }
`;
document.head.appendChild(style);

// Enhanced error handling for AJAX requests
const handleAjaxError = (error) => {
  console.error('AJAX Error:', error);
  if (navigator.onLine) {
    showToast('Connection error. Please check your internet connection.', 'danger');
  } else {
    showToast('You are offline. Changes are saved locally.', 'warning');
  }
};

// Network status monitoring
let wasOffline = false;
const updateNetworkStatus = () => {
  if (navigator.onLine) {
    if (wasOffline) {
      showToast('Connection restored!', 'success');
      wasOffline = false;
    }
  } else {
    showToast('Connection lost. Working offline.', 'warning', 5000);
    wasOffline = true;
  }
};

window.addEventListener('online', updateNetworkStatus);
window.addEventListener('offline', updateNetworkStatus);

// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', () => {
  const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
  alerts.forEach(alert => {
    setTimeout(() => {
      if (alert.parentElement) {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 500);
      }
    }, 5000);
  });
});

// Utility function for formatting time
const formatTime = (seconds) => {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;
  
  if (hours > 0) {
    return `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
  }
  return `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
};

// Make functions globally available
window.showToast = showToast;
window.handleAjaxError = handleAjaxError;
window.formatTime = formatTime;