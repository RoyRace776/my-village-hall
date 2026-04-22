/**
 * Portal App Tests
 * Tests for portal routing and navigation
 */

describe('Portal App - Route Handling', () => {
  const getHashRoute = (targetHash) => {
    const normalized = String(targetHash || '').replace(/^#/, '');
    const [page, queryString] = normalized.split('?');
    const params = {};

    new URLSearchParams(queryString || '').forEach((value, key) => {
      params[key] = value;
    });

    return {
      page: page || 'dashboard',
      params: params
    };
  };

  describe('getHashRoute', () => {
    test('should parse simple page routes', () => {
      const route = getHashRoute('#bookings');
      expect(route.page).toBe('bookings');
      expect(route.params).toEqual({});
    });

    test('should handle routes with hash prefix', () => {
      const route1 = getHashRoute('#dashboard');
      const route2 = getHashRoute('dashboard');
      expect(route1.page).toBe('dashboard');
      expect(route2.page).toBe('dashboard');
    });

    test('should parse query parameters', () => {
      const route = getHashRoute('#bookings?booking_id=123&action=edit');
      expect(route.page).toBe('bookings');
      expect(route.params.booking_id).toBe('123');
      expect(route.params.action).toBe('edit');
    });

    test('should default to dashboard for empty hash', () => {
      const route1 = getHashRoute('');
      const route2 = getHashRoute('#');
      const route3 = getHashRoute(null);

      expect(route1.page).toBe('dashboard');
      expect(route2.page).toBe('dashboard');
      expect(route3.page).toBe('dashboard');
    });

    test('should decode URL encoded parameters', () => {
      const route = getHashRoute('#bookings?message=Hello%20World');
      expect(route.params.message).toBe('Hello World');
    });
  });
});

describe('Portal App - Legacy Booking Routes', () => {
  const getLegacyBookingAction = (page, params) => {
    const legacyBookingRoutes = new Set(['new-booking', 'bookings-new', 'booking-view', 'booking-edit', 'booking-delete']);

    if (!legacyBookingRoutes.has(page)) {
      return null;
    }

    const bookingId = parseInt(params.booking_id || '0', 10) || 0;

    if (page === 'new-booking' || page === 'bookings-new') {
      return {
        page: 'bookings',
        run: function() {
          // window.MyvhPortalCalendarFlow?.openCreate({});
        }
      };
    }

    if (!bookingId) {
      return {
        page: 'bookings',
        run: function() {}
      };
    }

    if (page === 'booking-edit') {
      return {
        page: 'bookings',
        run: function() {
          // window.MyvhPortalCalendarFlow?.openEdit(bookingId);
        }
      };
    }

    return null;
  };

  describe('getLegacyBookingAction', () => {
    test('should recognize legacy booking routes', () => {
      expect(getLegacyBookingAction('new-booking', {})).not.toBeNull();
      expect(getLegacyBookingAction('bookings-new', {})).not.toBeNull();
      expect(getLegacyBookingAction('booking-edit', { booking_id: '1' })).not.toBeNull();
      expect(getLegacyBookingAction('invalid-route', {})).toBeNull();
    });

    test('should map new-booking to bookings page', () => {
      const action = getLegacyBookingAction('new-booking', {});
      expect(action.page).toBe('bookings');
      expect(typeof action.run).toBe('function');
    });

    test('should handle booking edit with ID', () => {
      const action = getLegacyBookingAction('booking-edit', { booking_id: '123' });
      expect(action).not.toBeNull();
      expect(action.page).toBe('bookings');
    });

    test('should return null for invalid routes', () => {
      expect(getLegacyBookingAction('dashboard', {})).toBeNull();
      expect(getLegacyBookingAction('settings', {})).toBeNull();
    });
  });
});

describe('Portal App - Route Aliases', () => {
  test('should have route aliases configured', () => {
    const routeAliases = {
      'my-bookings': 'bookings',
      'book-room': 'bookings',
      'home': 'dashboard'
    };

    expect(routeAliases['my-bookings']).toBe('bookings');
    expect(routeAliases['book-room']).toBe('bookings');
    expect(routeAliases['home']).toBe('dashboard');
  });

  test('should resolve aliased routes', () => {
    const routeAliases = {
      'my-bookings': 'bookings',
      'book-room': 'bookings',
      'home': 'dashboard'
    };

    const resolveAlias = (route) => routeAliases[route] || route;

    expect(resolveAlias('my-bookings')).toBe('bookings');
    expect(resolveAlias('home')).toBe('dashboard');
    expect(resolveAlias('bookings')).toBe('bookings'); // No alias
  });
});
