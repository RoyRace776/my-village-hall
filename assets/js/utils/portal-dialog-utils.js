/**
 * Portal Dialog Utilities - Exportable for Testing
 * Extracts testable functions from MyvhPortalDialog IIFE
 */

/**
 * Get site name from various sources
 * @returns {string} - Site name or default 'Message'
 */
function getSiteName() {
  if (typeof window !== 'undefined') {
    if (window.myvhPortal && window.myvhPortal.site_name) {
      return String(window.myvhPortal.site_name);
    }

    if (window.myvhPortal && window.myvhPortal.siteName) {
      return String(window.myvhPortal.siteName);
    }

    if (window.myvhCal && window.myvhCal.site_name) {
      return String(window.myvhCal.site_name);
    }

    if (window.myvhCal && window.myvhCal.siteName) {
      return String(window.myvhCal.siteName);
    }

    return document.title || 'Message';
  }

  return 'Message';
}

/**
 * Get CSS for portal dialogs
 * @returns {string} - CSS rules for dialog styling
 */
function getDialogStyles() {
  return [
    '.myvh-portal-dialog-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100000;display:flex;align-items:center;justify-content:center;padding:16px;}',
    '.myvh-portal-dialog{width:min(480px,100%);background:#fff;border-radius:12px;box-shadow:0 16px 40px rgba(0,0,0,.25);overflow:hidden;font-family:inherit;}',
    '.myvh-portal-dialog__head{padding:14px 18px;border-bottom:1px solid #e5e7eb;font-weight:600;color:#111827;}',
    '.myvh-portal-dialog__body{padding:18px;color:#1f2937;line-height:1.5;white-space:pre-wrap;}',
    '.myvh-portal-dialog__actions{padding:12px 18px;display:flex;gap:8px;justify-content:flex-end;border-top:1px solid #e5e7eb;}',
    '.myvh-portal-dialog__btn{appearance:none;border:1px solid #d1d5db;background:#fff;color:#111827;border-radius:8px;padding:8px 14px;cursor:pointer;font:inherit;}',
    '.myvh-portal-dialog__btn:focus{outline:2px solid #2271b1;outline-offset:2px;}',
    '.myvh-portal-dialog__btn--primary{background:#2271b1;border-color:#2271b1;color:#fff;}'
  ].join('');
}

/**
 * Create an alert dialog object
 * @param {string} message - Dialog message
 * @param {object} options - Dialog options
 * @returns {object} - Dialog configuration
 */
function createAlertDialog(message, options = {}) {
  return {
    type: 'alert',
    message: String(message || ''),
    title: String(options.title || getSiteName()),
    buttons: [{ label: 'OK', primary: true }]
  };
}

/**
 * Create a confirm dialog object
 * @param {string} message - Dialog message
 * @param {object} options - Dialog options
 * @returns {object} - Dialog configuration
 */
function createConfirmDialog(message, options = {}) {
  return {
    type: 'confirm',
    message: String(message || ''),
    title: String(options.title || getSiteName()),
    buttons: [
      { label: options.cancelLabel || 'Cancel', primary: false },
      { label: options.okLabel || 'OK', primary: true }
    ]
  };
}

/**
 * Validate dialog response
 * @param {object} response - Response to validate
 * @returns {object} - Validation result
 */
function validateDialogResponse(response) {
  if (!response || typeof response !== 'object') {
    return { valid: false, error: 'Invalid response format' };
  }

  if (!('type' in response)) {
    return { valid: false, error: 'Response missing type field' };
  }

  return { valid: true };
}

// For Jest/Node.js module compatibility
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    getSiteName,
    getDialogStyles,
    createAlertDialog,
    createConfirmDialog,
    validateDialogResponse,
  };
}
