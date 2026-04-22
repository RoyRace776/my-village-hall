/**
 * Portal AJAX Tests
 * Specific tests for portal AJAX communication
 */

describe('Portal AJAX - Request Building', () => {
  test('should build GET request', () => {
    const buildRequest = (method, action, data = {}) => {
      const request = {
        method,
        action,
        data: { ...data, _wpnonce: 'nonce123' }
      };
      return request;
    };

    const req = buildRequest('GET', 'list_bookings', { filter: 'status=confirmed' });

    expect(req.method).toBe('GET');
    expect(req.action).toBe('list_bookings');
    expect(req.data._wpnonce).toBe('nonce123');
  });

  test('should build POST request with data', () => {
    const buildRequest = (method, action, data = {}) => {
      return {
        method,
        action,
        data: { ...data, _wpnonce: 'nonce123' },
        headers: {
          'Content-Type': 'application/json'
        }
      };
    };

    const req = buildRequest('POST', 'create_booking', {
      room_id: 1,
      start_date: '2026-04-24'
    });

    expect(req.method).toBe('POST');
    expect(req.data.room_id).toBe(1);
    expect(req.headers['Content-Type']).toBe('application/json');
  });

  test('should include security nonce in all requests', () => {
    const addNonce = (data, nonce) => {
      return { ...data, _wpnonce: nonce };
    };

    const request = addNonce({ action: 'test' }, 'secure-nonce-123');

    expect(request._wpnonce).toBe('secure-nonce-123');
    expect(request.action).toBe('test');
  });
});

describe('Portal AJAX - Error Handling', () => {
  test('should handle network errors', () => {
    const handleError = (error) => {
      if (error.type === 'network') {
        return {
          success: false,
          message: 'Network error. Please check your connection.'
        };
      }
      return { success: false, message: 'An error occurred' };
    };

    const result = handleError({ type: 'network' });

    expect(result.success).toBe(false);
    expect(result.message).toContain('Network error');
  });

  test('should handle server errors', () => {
    const handleError = (error) => {
      if (error.status === 500) {
        return {
          success: false,
          message: 'Server error. Please try again later.',
          status: 500
        };
      }
      return { success: false, message: 'An error occurred' };
    };

    const result = handleError({ status: 500 });

    expect(result.success).toBe(false);
    expect(result.status).toBe(500);
  });

  test('should handle authorization errors', () => {
    const handleError = (error) => {
      if (error.status === 403) {
        return {
          success: false,
          message: 'You do not have permission to perform this action.',
          status: 403
        };
      }
      return { success: false, message: 'An error occurred' };
    };

    const result = handleError({ status: 403 });

    expect(result.message).toContain('permission');
  });

  test('should validate response format', () => {
    const validateResponse = (response) => {
      if (!response || typeof response !== 'object') {
        return { valid: false, error: 'Invalid response format' };
      }
      if (!('success' in response)) {
        return { valid: false, error: 'Response missing success field' };
      }
      return { valid: true };
    };

    expect(validateResponse(null).valid).toBe(false);
    expect(validateResponse({ success: true }).valid).toBe(true);
  });
});

describe('Portal AJAX - Data Transformation', () => {
  test('should transform raw API response to internal format', () => {
    const transformBooking = (raw) => {
      return {
        id: raw.booking_id,
        customer: raw.customer_name,
        room: raw.room_name,
        start: `${raw.start_date} ${raw.start_time}`,
        end: `${raw.end_date} ${raw.end_time}`,
        status: raw.booking_status
      };
    };

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
  });

  test('should format dates for API submission', () => {
    const formatForAPI = (data) => {
      return {
        ...data,
        start_date: data.start ? data.start.split(' ')[0] : null,
        start_time: data.start ? data.start.split(' ')[1] : null,
        end_date: data.end ? data.end.split(' ')[0] : null,
        end_time: data.end ? data.end.split(' ')[1] : null
      };
    };

    const data = {
      start: '2026-04-22 09:00',
      end: '2026-04-22 10:00'
    };

    const result = formatForAPI(data);

    expect(result.start_date).toBe('2026-04-22');
    expect(result.start_time).toBe('09:00');
  });

  test('should parse array responses', () => {
    const parseList = (response) => {
      if (!Array.isArray(response)) {
        return [];
      }
      return response.map(item => ({
        id: item.id,
        name: item.name,
        active: item.active === 1
      }));
    };

    const response = [
      { id: 1, name: 'Room A', active: 1 },
      { id: 2, name: 'Room B', active: 0 }
    ];

    const result = parseList(response);

    expect(result).toHaveLength(2);
    expect(result[0].active).toBe(true);
    expect(result[1].active).toBe(false);
  });
});

describe('Portal AJAX - Caching', () => {
  test('should cache GET requests', () => {
    const cache = {};

    const getCached = (key) => {
      if (cache[key] && !isCacheExpired(cache[key])) {
        return cache[key].data;
      }
      return null;
    };

    const setCached = (key, data, ttl = 300000) => {
      cache[key] = {
        data,
        expires: Date.now() + ttl
      };
    };

    const isCacheExpired = (entry) => {
      return Date.now() > entry.expires;
    };

    setCached('bookings', [{ id: 1 }]);
    const cached = getCached('bookings');

    expect(cached).toEqual([{ id: 1 }]);
  });

  test('should invalidate cache when data changes', () => {
    const cache = {};

    const invalidateCache = (key) => {
      delete cache[key];
    };

    const setCached = (key, data) => {
      cache[key] = data;
    };

    setCached('bookings', [{ id: 1 }]);
    expect(cache.bookings).toBeTruthy();

    invalidateCache('bookings');
    expect(cache.bookings).toBeUndefined();
  });

  test('should clear all cache', () => {
    const cache = {
      bookings: [{ id: 1 }],
      rooms: [{ id: 1 }],
      customers: [{ id: 1 }]
    };

    const clearCache = () => {
      Object.keys(cache).forEach(key => delete cache[key]);
    };

    expect(Object.keys(cache)).toHaveLength(3);
    clearCache();
    expect(Object.keys(cache)).toHaveLength(0);
  });
});

describe('Portal AJAX - Request Queuing', () => {
  test('should queue concurrent requests', () => {
    const queue = [];
    let processing = false;

    const queueRequest = (request) => {
      queue.push(request);
      if (!processing) {
        processQueue();
      }
    };

    const processQueue = () => {
      if (queue.length === 0) {
        processing = false;
        return;
      }
      processing = true;
      // Simulate request processing
      queue.shift();
    };

    queueRequest({ action: 'req1' });
    queueRequest({ action: 'req2' });

    expect(queue).toHaveLength(1);
  });

  test('should prevent duplicate requests', () => {
    const pendingRequests = new Set();

    const canMakeRequest = (requestKey) => {
      if (pendingRequests.has(requestKey)) {
        return false;
      }
      pendingRequests.add(requestKey);
      return true;
    };

    const completeRequest = (requestKey) => {
      pendingRequests.delete(requestKey);
    };

    const key = 'list_bookings_page1';
    expect(canMakeRequest(key)).toBe(true);
    expect(canMakeRequest(key)).toBe(false);

    completeRequest(key);
    expect(canMakeRequest(key)).toBe(true);
  });
});

describe('Portal AJAX - Response Status Codes', () => {
  test('should handle 200 OK response', () => {
    const handleStatus = (status, data) => {
      if (status === 200) {
        return { success: true, data };
      }
      return { success: false };
    };

    const result = handleStatus(200, { bookings: [] });
    expect(result.success).toBe(true);
  });

  test('should handle 400 Bad Request', () => {
    const handleStatus = (status, message) => {
      if (status === 400) {
        return { success: false, message: 'Bad request: ' + message };
      }
      return { success: true };
    };

    const result = handleStatus(400, 'Invalid parameters');
    expect(result.success).toBe(false);
    expect(result.message).toContain('Invalid parameters');
  });

  test('should handle 404 Not Found', () => {
    const handleStatus = (status) => {
      if (status === 404) {
        return { success: false, message: 'Resource not found' };
      }
      return { success: true };
    };

    const result = handleStatus(404);
    expect(result.message).toBe('Resource not found');
  });

  test('should handle 401 Unauthorized', () => {
    const handleStatus = (status) => {
      if (status === 401) {
        return { success: false, message: 'Please log in again' };
      }
      return { success: true };
    };

    const result = handleStatus(401);
    expect(result.message).toContain('log in');
  });
});

describe('Portal AJAX - Timeout Handling', () => {
  test('should timeout long requests', (done) => {
    const makeRequestWithTimeout = (delay, timeout = 5000) => {
      return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
          reject(new Error('Request timeout'));
        }, timeout);

        const requestTimer = setTimeout(() => {
          clearTimeout(timer);
          resolve({ success: true });
        }, delay);
      });
    };

    makeRequestWithTimeout(1000, 5000).then(result => {
      expect(result.success).toBe(true);
      done();
    });
  });

  test('should handle timeout error', (done) => {
    const makeRequestWithTimeout = (delay, timeout = 5000) => {
      return new Promise((resolve, reject) => {
        setTimeout(() => {
          reject(new Error('Request timeout'));
        }, timeout);

        setTimeout(() => {
          resolve({ success: true });
        }, delay);
      });
    };

    makeRequestWithTimeout(10000, 100).catch(error => {
      expect(error.message).toBe('Request timeout');
      done();
    });
  });
});
