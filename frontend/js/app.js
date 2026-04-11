/**
 * CitySync - Shared JavaScript Utilities
 * Loaded on every page via <script src="js/app.js">
 */

/**
 * Show a toast notification
 * @param {string} message
 * @param {string} type - 'success' | 'error' | 'warning' | ''
 * @param {number} duration - ms before auto-dismiss (default 4000)
 */
function showToast(message, type = '', duration = 4000) {
    const container = $('#toast-container');
    if (!container.length) return;

    const icons = {
        success: '✅',
        error:   '❌',
        warning: '⚠️',
        '':      'ℹ️',
    };

    const toast = $(`
        <div class="toast ${type}">
            <span>${icons[type] || ''}  </span>
            <span>${message}</span>
        </div>
    `);

    container.append(toast);

    // Auto dismiss
    setTimeout(() => {
        toast.css({ opacity: 0, transition: 'opacity .3s' });
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/**
 * Format a date string nicely
 * @param {string} dateStr
 * @returns {string}
 */
function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-IN', {
        day:   '2-digit',
        month: 'short',
        year:  'numeric',
    });
}

/**
 * Escape HTML for safe insertion
 * @param {string} str
 * @returns {string}
 */
function escHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}
