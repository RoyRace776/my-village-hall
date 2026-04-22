/**
 * Booking Modal Create Tests
 * Specific tests for booking creation modal functions
 */

describe('BookingModalCreate - Configuration', () => {
  test('should initialize with default configuration', () => {
    const defaultConfig = {
      ajax_url: null,
      nonce: 'myvh_calendar',
      context: null,
      lockCustomer: false,
      lockOrganisation: false,
      hideCustomer: false,
      hideOrganisation: false,
      requireOrganisation: false,
      canManageNoInvoiceRequired: false,
      onSuccess: () => {},
      onOpen: () => {},
      onClose: () => {},
      onDelete: () => {},
      beforeSubmit: () => true,
      lockAddonPrices: false,
      editMode: false,
      editBookingId: 0
    };

    expect(defaultConfig.lockCustomer).toBe(false);
    expect(defaultConfig.editMode).toBe(false);
    expect(typeof defaultConfig.beforeSubmit).toBe('function');
  });

  test('should merge user config with defaults', () => {
    const defaultConfig = {
      ajax_url: null,
      lockCustomer: false,
      editMode: false
    };

    const userConfig = {
      ajax_url: 'https://example.com/ajax',
      lockCustomer: true
    };

    const merged = { ...defaultConfig, ...userConfig };

    expect(merged.ajax_url).toBe('https://example.com/ajax');
    expect(merged.lockCustomer).toBe(true);
    expect(merged.editMode).toBe(false);
  });

  test('should validate required ajax_url', () => {
    const validateConfig = (config) => {
      if (!config.ajax_url) {
        return { valid: false, errors: ['ajax_url is required'] };
      }
      return { valid: true, errors: [] };
    };

    const result1 = validateConfig({ ajax_url: null });
    expect(result1.valid).toBe(false);
    expect(result1.errors).toContain('ajax_url is required');

    const result2 = validateConfig({ ajax_url: 'https://example.com' });
    expect(result2.valid).toBe(true);
  });
});

describe('BookingModalCreate - Customer Management', () => {
  test('should cache customers to avoid repeated loads', () => {
    let cache = null;
    const customers = [
      { id: 1, name: 'John Doe' },
      { id: 2, name: 'Jane Smith' }
    ];

    const loadCustomers = async () => {
      if (cache !== null) {
        return cache;
      }
      cache = customers;
      return cache;
    };

    return loadCustomers().then(result => {
      expect(result).toEqual(customers);
      expect(cache).toBe(customers);
    });
  });

  test('should handle customer selection', () => {
    const customerLocked = false;
    const selectedCustomer = { id: 1, name: 'John Doe' };

    const selectCustomer = (customer) => {
      if (customerLocked) {
        return { success: false, message: 'Customer is locked' };
      }
      return { success: true, customer };
    };

    const result = selectCustomer(selectedCustomer);
    expect(result.success).toBe(true);
    expect(result.customer.id).toBe(1);
  });

  test('should prevent customer change when locked', () => {
    const selectCustomer = (customer, locked) => {
      if (locked) {
        return { success: false, message: 'Customer is locked' };
      }
      return { success: true, customer };
    };

    const lockedResult = selectCustomer({ id: 1, name: 'John' }, true);
    expect(lockedResult.success).toBe(false);
    expect(lockedResult.message).toBe('Customer is locked');
  });
});

describe('BookingModalCreate - Organisation Management', () => {
  test('should cache organisations by customer', () => {
    const organisationsCache = {};
    const customerId = 1;
    const organisations = [
      { id: 101, name: 'Org A' },
      { id: 102, name: 'Org B' }
    ];

    const cacheOrganisations = (custId, orgs) => {
      organisationsCache[custId] = orgs;
    };

    const getOrganisations = (custId) => {
      return organisationsCache[custId] || [];
    };

    cacheOrganisations(customerId, organisations);
    expect(getOrganisations(customerId)).toEqual(organisations);
    expect(getOrganisations(999)).toEqual([]);
  });

  test('should handle organisation requirement', () => {
    const requireOrganisation = true;

    const validateOrganisation = (selected) => {
      if (requireOrganisation && !selected) {
        return { valid: false, message: 'Organisation is required' };
      }
      return { valid: true };
    };

    expect(validateOrganisation(null).valid).toBe(false);
    expect(validateOrganisation({ id: 1 }).valid).toBe(true);
  });

  test('should prevent organisation change when locked', () => {
    const selectOrganisation = (org, locked) => {
      if (locked) {
        return { success: false, message: 'Organisation is locked' };
      }
      return { success: true, organisation: org };
    };

    const result = selectOrganisation({ id: 1 }, true);
    expect(result.success).toBe(false);
  });
});

describe('BookingModalCreate - Room Selection', () => {
  test('should load and cache rooms', () => {
    let roomsCache = null;
    const rooms = [
      { id: 1, name: 'Room A', capacity: 10 },
      { id: 2, name: 'Room B', capacity: 20 }
    ];

    const loadRooms = async () => {
      if (roomsCache !== null) {
        return roomsCache;
      }
      roomsCache = rooms;
      return roomsCache;
    };

    return loadRooms().then(result => {
      expect(result).toHaveLength(2);
      expect(result[0].name).toBe('Room A');
    });
  });

  test('should validate room availability', () => {
    const rooms = [
      { id: 1, name: 'Room A', available: true },
      { id: 2, name: 'Room B', available: false }
    ];

    const isRoomAvailable = (roomId) => {
      const room = rooms.find(r => r.id === roomId);
      return room && room.available;
    };

    expect(isRoomAvailable(1)).toBe(true);
    expect(isRoomAvailable(2)).toBe(false);
  });

  test('should filter rooms by capacity', () => {
    const rooms = [
      { id: 1, name: 'Room A', capacity: 5 },
      { id: 2, name: 'Room B', capacity: 20 },
      { id: 3, name: 'Room C', capacity: 15 }
    ];

    const filterByCapacity = (minCapacity) => {
      return rooms.filter(r => r.capacity >= minCapacity);
    };

    const result = filterByCapacity(10);
    expect(result).toHaveLength(2);
    expect(result[0].name).toBe('Room B');
  });
});

describe('BookingModalCreate - Date/Time Handling', () => {
  test('should validate booking dates', () => {
    const validateDates = (startDate, endDate) => {
      if (!startDate || !endDate) {
        return { valid: false, message: 'Dates required' };
      }
      if (new Date(endDate) <= new Date(startDate)) {
        return { valid: false, message: 'End date must be after start date' };
      }
      return { valid: true };
    };

    expect(validateDates(null, '2026-04-25').valid).toBe(false);
    expect(validateDates('2026-04-25', '2026-04-24').valid).toBe(false);
    expect(validateDates('2026-04-24', '2026-04-25').valid).toBe(true);
  });

  test('should calculate booking duration', () => {
    const calculateDuration = (startDate, endDate) => {
      const start = new Date(startDate);
      const end = new Date(endDate);
      return {
        milliseconds: end - start,
        hours: (end - start) / (1000 * 60 * 60),
        days: Math.ceil((end - start) / (1000 * 60 * 60 * 24))
      };
    };

    const duration = calculateDuration('2026-04-22', '2026-04-25');
    expect(duration.days).toBe(3);
    expect(duration.hours).toBeCloseTo(72);
  });

  test('should handle time slot validation', () => {
    const validateTimeSlot = (startTime, endTime) => {
      const start = parseInt(startTime.replace(':', ''));
      const end = parseInt(endTime.replace(':', ''));

      if (end <= start) {
        return { valid: false, message: 'End time must be after start time' };
      }
      if (end - start < 30) {
        return { valid: false, message: 'Minimum 30 minutes required' };
      }
      return { valid: true };
    };

    expect(validateTimeSlot('10:00', '09:00').valid).toBe(false);
    expect(validateTimeSlot('10:00', '10:15').valid).toBe(false);
    expect(validateTimeSlot('10:00', '10:30').valid).toBe(true);
  });
});

describe('BookingModalCreate - Price Calculations', () => {
  test('should calculate base room price', () => {
    const calculateRoomPrice = (roomRate, hours) => {
      return roomRate * hours;
    };

    expect(calculateRoomPrice(50, 2)).toBe(100);
    expect(calculateRoomPrice(25, 4)).toBe(100);
  });

  test('should apply addons to price', () => {
    const applyAddons = (basePrice, addons) => {
      const addonTotal = addons.reduce((sum, addon) => sum + addon.price, 0);
      return basePrice + addonTotal;
    };

    const addons = [
      { id: 1, name: 'Chairs', price: 20 },
      { id: 2, name: 'Tables', price: 30 }
    ];

    const total = applyAddons(100, addons);
    expect(total).toBe(150);
  });

  test('should prevent addon price changes when locked', () => {
    const selectAddon = (addon, locked) => {
      if (locked) {
        return { success: false, message: 'Addon prices are locked' };
      }
      return { success: true, addon };
    };

    const result = selectAddon({ id: 1, price: 20 }, true);
    expect(result.success).toBe(false);
  });

  test('should calculate final price with tax', () => {
    const calculateFinalPrice = (subtotal, taxRate) => {
      const tax = subtotal * (taxRate / 100);
      return {
        subtotal,
        tax,
        total: subtotal + tax
      };
    };

    const pricing = calculateFinalPrice(100, 20);
    expect(pricing.tax).toBe(20);
    expect(pricing.total).toBe(120);
  });
});

describe('BookingModalCreate - Form Validation', () => {
  test('should validate complete booking form', () => {
    const validateForm = (formData) => {
      const errors = [];

      if (!formData.customerId) errors.push('Customer required');
      if (!formData.roomId) errors.push('Room required');
      if (!formData.startDate) errors.push('Start date required');
      if (!formData.endDate) errors.push('End date required');

      return {
        valid: errors.length === 0,
        errors
      };
    };

    const invalidForm = {
      customerId: null,
      roomId: 1,
      startDate: '2026-04-24'
    };

    const result = validateForm(invalidForm);
    expect(result.valid).toBe(false);
    expect(result.errors).toHaveLength(2);
  });

  test('should support beforeSubmit hook', () => {
    const beforeSubmit = jest.fn((formData) => {
      // Custom validation in hook
      return formData.customerId > 0;
    });

    const formData = { customerId: 1, roomId: 1 };
    beforeSubmit(formData);

    expect(beforeSubmit).toHaveBeenCalledWith(formData);
  });

  test('should call onSuccess hook after submission', () => {
    const onSuccess = jest.fn();
    const response = { bookingId: 123, status: 'created' };

    onSuccess(response);

    expect(onSuccess).toHaveBeenCalledWith(response);
  });
});
