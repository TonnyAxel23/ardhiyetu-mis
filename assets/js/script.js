/**
 * ArdhiYetu - Main JavaScript File
 * Contains all interactive functionality for the system
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            this.querySelector('i').classList.toggle('fa-bars');
            this.querySelector('i').classList.toggle('fa-times');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!navLinks.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                navLinks.classList.remove('active');
                mobileMenuBtn.querySelector('i').classList.remove('fa-times');
                mobileMenuBtn.querySelector('i').classList.add('fa-bars');
            }
        });
    }
    
    // Password Visibility Toggle
    const passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    // Form Validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    
                    // Create error message if not exists
                    if (!field.nextElementSibling?.classList.contains('error-message')) {
                        const errorMsg = document.createElement('span');
                        errorMsg.className = 'error-message';
                        errorMsg.textContent = 'This field is required';
                        errorMsg.style.color = '#e74c3c';
                        errorMsg.style.fontSize = '0.875rem';
                        errorMsg.style.display = 'block';
                        errorMsg.style.marginTop = '0.25rem';
                        field.parentNode.insertBefore(errorMsg, field.nextSibling);
                    }
                } else {
                    field.classList.remove('error');
                    const errorMsg = field.nextElementSibling;
                    if (errorMsg?.classList.contains('error-message')) {
                        errorMsg.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showToast('Please fill all required fields', 'error');
            }
        });
    });
    
    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Dynamic Counter Animation
    const counters = document.querySelectorAll('.counter');
    if (counters.length > 0) {
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'));
            const increment = target / 100;
            let current = 0;
            
            const updateCounter = () => {
                if (current < target) {
                    current += increment;
                    counter.textContent = Math.ceil(current);
                    setTimeout(updateCounter, 20);
                } else {
                    counter.textContent = target;
                }
            };
            
            // Start when element is in viewport
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        updateCounter();
                        observer.unobserve(entry.target);
                    }
                });
            });
            
            observer.observe(counter);
        });
    }
    
    // Tab System
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and panes
            tabButtons.forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            
            // Add active class to clicked button and corresponding pane
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // File Upload Preview
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files[0]?.name;
            if (fileName) {
                let preview = this.nextElementSibling;
                if (!preview || !preview.classList.contains('file-preview')) {
                    preview = document.createElement('div');
                    preview.className = 'file-preview';
                    preview.style.marginTop = '0.5rem';
                    preview.style.fontSize = '0.875rem';
                    preview.style.color = '#666';
                    this.parentNode.insertBefore(preview, this.nextSibling);
                }
                preview.textContent = `Selected: ${fileName}`;
            }
        });
    });
    
    // Date Picker Initialization
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = new Date().toISOString().split('T')[0];
        }
    });
    
    // Search Functionality
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('input', debounce(function() {
            const searchTerm = this.value.toLowerCase();
            const container = this.closest('.search-container');
            const items = container?.querySelectorAll('.search-item');
            
            if (items) {
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            }
        }, 300));
    });
    
    // Print Functionality
    const printButtons = document.querySelectorAll('.print-btn');
    printButtons.forEach(button => {
        button.addEventListener('click', function() {
            window.print();
        });
    });
    
    // Export Functionality
    const exportButtons = document.querySelectorAll('.export-btn');
    exportButtons.forEach(button => {
        button.addEventListener('click', function() {
            const format = this.getAttribute('data-format') || 'csv';
            const tableId = this.getAttribute('data-table');
            const table = tableId ? document.getElementById(tableId) : this.closest('table');
            
            if (table) {
                exportTable(table, format);
            }
        });
    });
    
    // Notification Bell
    const notificationBell = document.querySelector('.notification-bell');
    if (notificationBell) {
        notificationBell.addEventListener('click', function() {
            const dropdown = this.nextElementSibling;
            dropdown.classList.toggle('show');
            
            // Mark as read
            fetch('mark-notifications-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            });
        });
        
        // Close when clicking outside
        document.addEventListener('click', function(event) {
            if (!notificationBell.contains(event.target)) {
                const dropdown = notificationBell.nextElementSibling;
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        });
    }
    
    // Map Integration (if available)
    if (typeof google !== 'undefined') {
        initializeMaps();
    }
    
    // Real-time Updates (WebSocket simulation)
    if (typeof EventSource !== 'undefined') {
        setupEventSource();
    }
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize modals
    initializeModals();
});

// Helper Functions

/**
 * Show toast notification
 * @param {string} message - Message to display
 * @param {string} type - Type of toast (success, error, warning, info)
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${getToastIcon(type)}"></i>
        <span>${message}</span>
        <button class="toast-close">&times;</button>
    `;
    
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem;
        background: ${getToastColor(type)};
        color: white;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(toast);
    
    // Close button
    toast.querySelector('.toast-close').addEventListener('click', () => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    });
    
    // Auto remove
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 5000);
    
    // Add CSS for animations if not exists
    if (!document.querySelector('#toast-animations')) {
        const style = document.createElement('style');
        style.id = 'toast-animations';
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * Get toast icon based on type
 */
function getToastIcon(type) {
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };
    return icons[type] || 'info-circle';
}

/**
 * Get toast color based on type
 */
function getToastColor(type) {
    const colors = {
        success: '#27ae60',
        error: '#e74c3c',
        warning: '#f39c12',
        info: '#3498db'
    };
    return colors[type] || '#3498db';
}

/**
 * Debounce function for performance
 */
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

/**
 * Export table data
 */
function exportTable(table, format) {
    const rows = table.querySelectorAll('tr');
    const data = [];
    
    rows.forEach(row => {
        const rowData = [];
        row.querySelectorAll('th, td').forEach(cell => {
            rowData.push(cell.textContent.trim());
        });
        data.push(rowData);
    });
    
    let content = '';
    if (format === 'csv') {
        content = data.map(row => row.join(',')).join('\n');
        downloadFile(content, 'export.csv', 'text/csv');
    } else if (format === 'json') {
        const headers = data[0];
        const jsonData = data.slice(1).map(row => {
            const obj = {};
            headers.forEach((header, index) => {
                obj[header] = row[index];
            });
            return obj;
        });
        content = JSON.stringify(jsonData, null, 2);
        downloadFile(content, 'export.json', 'application/json');
    }
}

/**
 * Download file
 */
function downloadFile(content, filename, mimeType) {
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

/**
 * Initialize Google Maps
 */
function initializeMaps() {
    const mapContainers = document.querySelectorAll('.map-container');
    mapContainers.forEach(container => {
        const lat = parseFloat(container.getAttribute('data-lat'));
        const lng = parseFloat(container.getAttribute('data-lng'));
        
        if (!isNaN(lat) && !isNaN(lng)) {
            const map = new google.maps.Map(container, {
                center: { lat, lng },
                zoom: 15
            });
            
            new google.maps.Marker({
                position: { lat, lng },
                map: map,
                title: 'Land Location'
            });
        }
    });
}

/**
 * Setup EventSource for real-time updates
 */
function setupEventSource() {
    const eventSource = new EventSource('updates.php');
    
    eventSource.onmessage = function(event) {
        const data = JSON.parse(event.data);
        
        if (data.type === 'notification') {
            showToast(data.message, 'info');
            updateNotificationCount();
        } else if (data.type === 'transfer_update') {
            showToast(`Transfer ${data.reference} status updated to ${data.status}`, 'info');
        }
    };
    
    eventSource.onerror = function() {
        console.error('EventSource failed.');
        eventSource.close();
    };
}

/**
 * Update notification count in bell
 */
function updateNotificationCount() {
    const bell = document.querySelector('.notification-bell');
    if (bell) {
        const count = bell.querySelector('.notification-count');
        if (count) {
            const current = parseInt(count.textContent) || 0;
            count.textContent = current + 1;
        }
        
        // Add animation
        bell.classList.add('pulse');
        setTimeout(() => bell.classList.remove('pulse'), 1000);
    }
}

/**
 * Initialize tooltips
 */
function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 0.5rem;
                border-radius: 4px;
                font-size: 0.875rem;
                z-index: 1000;
                white-space: nowrap;
            `;
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - 40) + 'px';
            tooltip.style.left = (rect.left + rect.width / 2) + 'px';
            tooltip.style.transform = 'translateX(-50%)';
            
            document.body.appendChild(tooltip);
            this._tooltip = tooltip;
        });
        
        element.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                this._tooltip = null;
            }
        });
    });
}

/**
 * Initialize modals
 */
function initializeModals() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                
                // Close button
                const closeBtn = modal.querySelector('.modal-close');
                if (closeBtn) {
                    closeBtn.onclick = () => modal.style.display = 'none';
                }
                
                // Close when clicking outside
                modal.onclick = (e) => {
                    if (e.target === modal) {
                        modal.style.display = 'none';
                    }
                };
            }
        });
    });
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-KE', {
        style: 'currency',
        currency: 'KES'
    }).format(amount);
}

/**
 * Format date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-KE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

/**
 * Validate email
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validate phone number (Kenyan format)
 */
function validatePhone(phone) {
    const re = /^(\+?254|0)[17]\d{8}$/;
    return re.test(phone);
}

/**
 * Show loading state
 */
function showLoading(element) {
    const originalContent = element.innerHTML;
    element.innerHTML = '<div class="loading"></div>';
    element._originalContent = originalContent;
    element.disabled = true;
}

/**
 * Hide loading state
 */
function hideLoading(element) {
    if (element._originalContent) {
        element.innerHTML = element._originalContent;
        element.disabled = false;
    }
}

/**
 * Copy to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Copied to clipboard', 'success');
    }).catch(err => {
        console.error('Failed to copy: ', err);
        showToast('Failed to copy', 'error');
    });
}

// Add CSS for additional components
const additionalStyles = `
    .pulse {
        animation: pulse 1s;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); }
        100% { transform: scale(1); }
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .modal-content {
        background-color: white;
        margin: 5% auto;
        padding: 2rem;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        position: relative;
    }
    
    .modal-close {
        position: absolute;
        right: 1rem;
        top: 1rem;
        font-size: 1.5rem;
        cursor: pointer;
        background: none;
        border: none;
        color: #666;
    }
    
    .dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-radius: 4px;
        min-width: 200px;
        display: none;
        z-index: 1000;
    }
    
    .dropdown.show {
        display: block;
    }
    
    .notification-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #e74c3c;
        color: white;
        font-size: 0.75rem;
        padding: 0.125rem 0.375rem;
        border-radius: 10px;
        min-width: 1rem;
        text-align: center;
    }
`;

// Add styles to document
if (!document.querySelector('#additional-styles')) {
    const style = document.createElement('style');
    style.id = 'additional-styles';
    style.textContent = additionalStyles;
    document.head.appendChild(style);
}