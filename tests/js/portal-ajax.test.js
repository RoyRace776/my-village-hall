/**
 * Portal AJAX Tests
 * Tests for portal AJAX communication
 * Imports from portal-ajax-utils.js
 */

const {
  buildRequest,
  handleAjaxError,
  validateAjaxResponse,
  transformBooking,
  formatBookingForAPI,
  parseList,
  parseErrorResponse
} = require('../../assets/js/utils/portal-ajax-utils.js');

describe('Portal AJAX - Request Building', () => {
  test('should build GET request', () => {
    const req = buildRequest('GET', 'list_bookings', { filter: 'status=confirmed' });

    expect(req.method).toBe('GET');
    expect(req.action).toBe('list_bookings');
    expect(req.data._wpnonce).toBeTruthy();
  });

  test('should build POST request with data', () => {
    const req = buildRequest('POST', 'create_booking', {
      room_id: 1,
      start_date: '2026-04-24'
    });

    expect(req.method).toBe('POST');
    expect(req.data.room_id).toBe(1);
    expect(req.headers['Content-Type']).toBe('application/json');
  });

  test('should include security nonce in all requests', () => {
    const req = buildRequest('GET', 'action', { custom: 'data' });
    expect(req.data._wpnonce).toBeTruthy();
  });

  test('should handle case-insensitive method', () => {
    const req1 = buildRequest('get', 'action');
    const req2 = buildRequest('POST', 'action');

    expect(req1.method).toBe('GET');
    expect(req2.method).toBe('POST');
  });
});

describe('Portal AJAX - Error Handling', () => {
  test('should handle network errors', () => {
    const result = handleAjaxError({ type: 'network' });

    expect(result.success).toBe(false);
    expect(result.message).toContain('Network error');
  });

  test('should handle server errors', () => {
    const result = handleAjaxError({ status: 500 });

    expect(result.success).toBe(false);
    expect(result.status).toBe(500);
  });

  test('should handle authorization errors', () => {
    const result = handleAjaxError({ status: 401 });

    expect(result.success).toBe(false);
    expect(result.message).toContain('permission');
    expect(result.status).toBe(401);
  });

  test('should handle 403 Forbidden', () => {
    const result = handleAjaxError({ status: 403 });
    expect(result.status).toBe(403);
  });

  test('should handle 404 Not Found', () => {
    const result = handleAjaxError({ status: 404 });
    expect(result.status).toBe(404);
    expect(result.message).toContain('not found');
  });

  test('should handle generic errors', () => {
    const result = handleAjaxError({ message: 'Custom error' });
    expect(result.success).toBe(false);
    expect(result.message).toBe('Custom error');
  });
});

describe('Portal AJAX - Response Validation', () => {
  test('should validate correct response format', () => {
    const result = validateAjaxResponse({ success: true });
    expect(result.valid).toBe(true);
  });

  test('should reject null response', () => {
    const result = validateAjaxResponse(null);
    expect(result.valid).toBe(false);
  });

  test('should reject non-object response', () => {
    const result = validateAjaxResponse('string');
    expect(result.valid).toBe(false);
  });

  test('should reject response missing success field', () => {
    const result = validateAjaxResponse({ data: [] });
    expect(result.valid).toBe(false);
    expect(result.error).toContain('success');
  });
});

describe('Portal AJAX - Data Transformation', () => {
  test('should transform booking from API format', () => {
    const raw = {
      booking_id: 123,
      customer_name: 'John Doe',
      room_name: 'Room A',
      start_date: '2026-04-22',
      start_time: '09:00',
      end_date: '2026-04-22',
      end_time: '10:00',
      booking_status: 'confirmed'
    };

    const transformed = transformBooking(raw);

    expect(transformed.id).toBe(123);
    expect(transformed.customer).toBe('John Doe');
    expect(transformed.start).toBe('2026-04-22 09:00');
    expect(transformed.status).toBe('confirmed');
  });

  test('should handle incomplete booking data', () => {
    const transformed = transformBooking({ booking_id: 1 });
    expect(transformed.id).toBe(1);
    expect(transformed.customer).toBe('');
  });

  test('should format booking for API submission', () => {
    const data = {
      start: '2026-04-22 09:00',
      end: '2026-04-22 10:00'
    };

    const result = formatBookingForAPI(data);

    expect(result.start_date).toBe('2026-04-22');
    expect(result.start_time).toBe('09:00');
    expect(result.end_date).toBe('2026-04-22');
    expect(result.end_time).toBe('10:00');
  });

  test('should parse array response', () => {
    const response = [
      { id: 1, name: 'Room A', active: 1 },
      { id: 2, name: 'Room B', active: 0 }
    ];

    const result = parseList(response);

    expect(result).toHaveLength(2);
    expect(result[0].active).toBe(true);
    expect(result[1].active).toBe(false);
  });

  test('should handle null list response', () => {
    expect(parseList(null)).toEqual([]);
    expect(parseList('string')).toEqual([]);
  });

  test('should parse error response', () => {
    const error = parseErrorResponse({ message: 'Invalid booking' });
    expect(error.message).toBe('Invalid booking');
  });

  test('should handle string error response', () => {
    const error = parseErrorResponse('Error message');
    expect(error.message).toBe('Error message');
  });
});
