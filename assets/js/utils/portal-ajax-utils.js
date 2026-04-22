/**
 * Portal AJAX Utilities - Exportable for Testing
 * Utility functions for AJAX request handling and transformation
 */

/**
 * Build an AJAX request object
 * @param {string} method - HTTP method (GET, POST, etc)
 * @param {string} action - WordPress AJAX action
 * @param {object} data - Request data
 * @returns {object} - Request configuration
 */
function buildRequest(method, action, data = {}) {
  return {
    method: method.toUpperCase(),
    action,
    data: { ...data, _wpnonce: data._wpnonce || 'nonce' },
    headers: method.toUpperCase() === 'POST' ? {
      'Content-Type': 'application/json'
    } : {}
  };
}

/**
 * Handle AJAX errors
 * @param {object} error - Error object
 * @returns {object} - Error response
 */
function handleAjaxError(error) {
  if (!error) {
    return { success: false, message: 'Unknown error' };
  }

  if (error.type === 'network') {
    return {
      success: false,
      message: 'Network error. Please check your connection.',
      type: 'network'
    };
  }

  if (error.status === 401) {
    return {
      success: false,
      message: 'You do not have permission to perform this action.',
      status: 401
    };
  }

  if (error.status === 403) {
    return {
      success: false,
      message: 'Forbidden. Please try again or contact support.',
      status: 403
    };
  }

  if (error.status === 404) {
    return {
      success: false,
      message: 'Resource not found.',
      status: 404
    };
  }

  if (error.status === 500) {
    return {
      success: false,
      message: 'Server error. Please try again later.',
      status: 500
    };
  }

  return { success: false, message: error.message || 'An error occurred' };
}

/**
 * Validate AJAX response format
 * @param {object} response - Response to validate
 * @returns {object} - Validation result
 */
function validateAjaxResponse(response) {
  if (!response || typeof response !== 'object') {
    return { valid: false, error: 'Invalid response format' };
  }

  if (!('success' in response)) {
    return { valid: false, error: 'Response missing success field' };
  }

  return { valid: true };
}

/**
 * Transform booking data from API format to internal format
 * @param {object} raw - Raw API response
 * @returns {object} - Transformed booking
 */
function transformBooking(raw) {
  if (!raw) {
    return null;
  }

  return {
    id: raw.booking_id || raw.id,
    customer: raw.customer_name || '',
    room: raw.room_name || '',
    start: raw.start_date && raw.start_time ? `${raw.start_date} ${raw.start_time}` : '',
    end: raw.end_date && raw.end_time ? `${raw.end_date} ${raw.end_time}` : '',
    status: raw.booking_status || raw.status || '',
    price: parseFloat(raw.price || 0)
  };
}

/**
 * Format booking data for API submission
 * @param {object} data - Booking data
 * @returns {object} - API-formatted data
 */
function formatBookingForAPI(data) {
  const result = { ...data };

  if (data.start) {
    const [date, time] = data.start.split(' ');
    result.start_date = date;
    result.start_time = time;
  }

  if (data.end) {
    const [date, time] = data.end.split(' ');
    result.end_date = date;
    result.end_time = time;
  }

  return result;
}

/**
 * Parse list response from API
 * @param {array} response - API response array
 * @returns {array} - Parsed items
 */
function parseList(response) {
  if (!Array.isArray(response)) {
    return [];
  }

  return response.map(item => ({
    id: item.id,
    name: item.name,
    active: item.active === 1 || item.active === true
  }));
}

/**
 * Parse error response from API
 * @param {object} response - Error response
 * @returns {object} - Parsed error
 */
function parseErrorResponse(response) {
  if (!response) {
    return { message: 'Unknown error' };
  }

  if (typeof response === 'string') {
    return { message: response };
  }

  return {
    message: response.message || response.error || 'An error occurred',
    errors: response.errors || [],
    code: response.code || null
  };
}

// For Jest/Node.js module compatibility
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    buildRequest,
    handleAjaxError,
    validateAjaxResponse,
    transformBooking,
    formatBookingForAPI,
    parseList,
    parseErrorResponse,
  };
}
