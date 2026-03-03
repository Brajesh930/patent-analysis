/**
 * Patent Analysis MVP - Frontend JavaScript
 * Vanilla JS - no frameworks
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize
    initializeElements();
    setupEventListeners();
});

function initializeElements() {
    // Auto-focus first form field
    const firstInput = document.querySelector('input:not([type="hidden"]), textarea');
    if (firstInput) {
        firstInput.focus();
    }
}

function setupEventListeners() {
    // Clear alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success')) {
            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }
    });

    // Prevent double form submission
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submit = form.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Processing...';
            }
        });
    });

    // Confirm before destructive actions
    const deleteLinks = document.querySelectorAll('[data-confirm]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Utility: Copy text to clipboard
 */
function copyToClipboard(text, feedback = 'Copied!') {
    navigator.clipboard.writeText(text).then(() => {
        alert(feedback);
    });
}

/**
 * Utility: Format JSON nicely
 */
function formatJson(obj) {
    return JSON.stringify(obj, null, 2);
}

/**
 * Utility: Show/hide loading indicator
 */
function showLoading(show = true) {
    let loader = document.getElementById('loading-indicator');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'loading-indicator';
        loader.innerHTML = '<p>Processing...</p>';
        loader.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 20px 40px;
            border-radius: 8px;
            z-index: 9999;
            display: none;
        `;
        document.body.appendChild(loader);
    }
    loader.style.display = show ? 'block' : 'none';
}

/**
 * Utility: Validate JSON string
 */
function isValidJson(str) {
    try {
        JSON.parse(str);
        return true;
    } catch (e) {
        return false;
    }
}

/**
 * Export table to CSV
 */
function exportTableToCsv(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;

    let csv = [];
    const rows = table.querySelectorAll('tr');

    rows.forEach(row => {
        const cols = row.querySelectorAll('td, th');
        const csvRow = [];
        cols.forEach(col => {
            csvRow.push('"' + col.textContent.replace(/"/g, '""') + '"');
        });
        csv.push(csvRow.join(','));
    });

    downloadCsv(csv.join('\n'), filename);
}

/**
 * Download CSV file
 */
function downloadCsv(csv, filename) {
    const link = document.createElement('a');
    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    link.download = filename;
    link.click();
}

/**
 * Utility: Debounce function
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
 * Utility: Format number as percentage
 */
function formatPercent(num, decimals = 1) {
    return (num * 100).toFixed(decimals) + '%';
}

console.log('Patent Analysis MVP - Ready');
