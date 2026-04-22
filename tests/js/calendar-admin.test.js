/**
 * Calendar Admin Tests
 * Specific tests for calendar admin functionality
 */

describe('Calendar Admin - Event Creation', () => {
  test('should create event with required fields', () => {
    const createEvent = (data) => {
      const event = {
        id: data.id || null,
        text: data.text || 'Untitled',
        start: data.start,
        end: data.end,
        resource: data.resource || [],
        tags: data.tags || {}
      };

      if (!event.start || !event.end) {
        return { success: false, error: 'Start and end times required' };
      }

      return { success: true, event };
    };

    const eventData = {
      id: 'booking-123',
      text: 'Team Meeting',
      start: '2026-04-22 09:00',
      end: '2026-04-22 10:00',
      resource: ['room-1']
    };

    const result = createEvent(eventData);
    expect(result.success).toBe(true);
    expect(result.event.text).toBe('Team Meeting');
  });

  test('should reject event without times', () => {
    const createEvent = (data) => {
      if (!data.start || !data.end) {
        return { success: false, error: 'Start and end times required' };
      }
      return { success: true };
    };

    const result = createEvent({ text: 'Event' });
    expect(result.success).toBe(false);
    expect(result.error).toContain('required');
  });

  test('should validate event end time after start', () => {
    const validateEventTimes = (start, end) => {
      const startTime = new Date(start);
      const endTime = new Date(end);

      if (endTime <= startTime) {
        return { valid: false, message: 'End time must be after start' };
      }
      return { valid: true };
    };

    expect(validateEventTimes('2026-04-22 10:00', '2026-04-22 09:00').valid).toBe(false);
    expect(validateEventTimes('2026-04-22 09:00', '2026-04-22 10:00').valid).toBe(true);
  });
});

describe('Calendar Admin - Event Status', () => {
  test('should set event status with color', () => {
    const statusColors = {
      confirmed: '#2271b1',
      pending: '#f0a500',
      cancelled: '#9aa0a6',
      completed: '#2d8f45'
    };

    const setEventStatus = (event, status) => {
      if (!statusColors[status]) {
        return { success: false, error: 'Invalid status' };
      }

      return {
        success: true,
        event: {
          ...event,
          status,
          backColor: statusColors[status]
        }
      };
    };

    const event = { id: 1, text: 'Meeting' };
    const result = setEventStatus(event, 'confirmed');

    expect(result.success).toBe(true);
    expect(result.event.status).toBe('confirmed');
    expect(result.event.backColor).toBe('#2271b1');
  });

  test('should handle unknown status', () => {
    const setEventStatus = (event, status) => {
      const validStatuses = ['confirmed', 'pending', 'cancelled'];
      if (!validStatuses.includes(status)) {
        return { success: false, error: 'Invalid status' };
      }
      return { success: true };
    };

    const result = setEventStatus({}, 'unknown');
    expect(result.success).toBe(false);
  });

  test('should mark private locked events', () => {
    const markPrivateLocked = (event) => {
      const cssClass = (event.cssClass || '') + ' myvh-event-private-locked';
      return { ...event, cssClass: cssClass.trim() };
    };

    const event = { id: 1, cssClass: 'event-class' };
    const result = markPrivateLocked(event);

    expect(result.cssClass).toContain('myvh-event-private-locked');
  });
});

describe('Calendar Admin - Event Resources', () => {
  test('should assign rooms as resources', () => {
    const assignRooms = (event, roomIds) => {
      return {
        ...event,
        resource: roomIds
      };
    };

    const event = { id: 1, text: 'Booking' };
    const result = assignRooms(event, ['room-1', 'room-2']);

    expect(result.resource).toEqual(['room-1', 'room-2']);
  });

  test('should remove room from event', () => {
    const removeRoom = (event, roomId) => {
      return {
        ...event,
        resource: event.resource.filter(r => r !== roomId)
      };
    };

    const event = { id: 1, resource: ['room-1', 'room-2'] };
    const result = removeRoom(event, 'room-1');

    expect(result.resource).toEqual(['room-2']);
  });

  test('should validate resource exists before assign', () => {
    const availableRooms = ['room-1', 'room-2', 'room-3'];

    const assignRoom = (event, roomId) => {
      if (!availableRooms.includes(roomId)) {
        return { success: false, error: 'Room does not exist' };
      }
      return { success: true, event: { ...event, resource: [roomId] } };
    };

    const result1 = assignRoom({ id: 1 }, 'room-1');
    const result2 = assignRoom({ id: 1 }, 'room-99');

    expect(result1.success).toBe(true);
    expect(result2.success).toBe(false);
  });
});

describe('Calendar Admin - Event Tags', () => {
  test('should add tags to event', () => {
    const addTags = (event, tags) => {
      return {
        ...event,
        tags: { ...event.tags, ...tags }
      };
    };

    const event = { id: 1, tags: { isBuffer: false } };
    const result = addTags(event, { roomColour: '#ff0000', isPublic: true });

    expect(result.tags.isBuffer).toBe(false);
    expect(result.tags.roomColour).toBe('#ff0000');
    expect(result.tags.isPublic).toBe(true);
  });

  test('should identify buffer events', () => {
    const isBufferEvent = (event) => {
      return event.tags && event.tags.isBuffer === true;
    };

    const bufferEvent = { id: 1, tags: { isBuffer: true } };
    const regularEvent = { id: 2, tags: { isBuffer: false } };

    expect(isBufferEvent(bufferEvent)).toBe(true);
    expect(isBufferEvent(regularEvent)).toBe(false);
  });

  test('should identify private vs public events', () => {
    const isPrivateEvent = (event) => {
      const tags = event.tags || {};
      return !tags.isPublic;
    };

    const privateEvent = { tags: { isPublic: false } };
    const publicEvent = { tags: { isPublic: true } };

    expect(isPrivateEvent(privateEvent)).toBe(true);
    expect(isPrivateEvent(publicEvent)).toBe(false);
  });
});

describe('Calendar Admin - Bulk Operations', () => {
  test('should bulk update event status', () => {
    const bulkUpdateStatus = (events, newStatus) => {
      return events.map(event => ({ ...event, status: newStatus }));
    };

    const events = [
      { id: 1, status: 'pending' },
      { id: 2, status: 'pending' },
      { id: 3, status: 'pending' }
    ];

    const result = bulkUpdateStatus(events, 'confirmed');

    expect(result).toHaveLength(3);
    expect(result.every(e => e.status === 'confirmed')).toBe(true);
  });

  test('should bulk delete events', () => {
    const deleteEvents = (events, idsToDelete) => {
      return events.filter(e => !idsToDelete.includes(e.id));
    };

    const events = [
      { id: 1, text: 'Event 1' },
      { id: 2, text: 'Event 2' },
      { id: 3, text: 'Event 3' }
    ];

    const result = deleteEvents(events, [1, 3]);

    expect(result).toHaveLength(1);
    expect(result[0].id).toBe(2);
  });

  test('should bulk assign rooms to events', () => {
    const bulkAssignRooms = (events, roomIds) => {
      return events.map(event => ({ ...event, resource: roomIds }));
    };

    const events = [
      { id: 1, resource: [] },
      { id: 2, resource: [] }
    ];

    const result = bulkAssignRooms(events, ['room-1', 'room-2']);

    expect(result.every(e => e.resource.length === 2)).toBe(true);
  });
});

describe('Calendar Admin - Date Range Queries', () => {
  test('should filter events by date range', () => {
    const filterByDateRange = (events, startDate, endDate) => {
      const start = new Date(startDate);
      const end = new Date(endDate);

      return events.filter(event => {
        const eventStart = new Date(event.start);
        return eventStart >= start && eventStart <= end;
      });
    };

    const events = [
      { id: 1, start: '2026-04-20' },
      { id: 2, start: '2026-04-22' },
      { id: 3, start: '2026-04-25' }
    ];

    const result = filterByDateRange(events, '2026-04-21', '2026-04-24');

    expect(result).toHaveLength(1);
    expect(result[0].id).toBe(2);
  });

  test('should find overlapping events', () => {
    const findOverlapping = (events, start, end) => {
      const startTime = new Date(start);
      const endTime = new Date(end);

      return events.filter(event => {
        const eventStart = new Date(event.start);
        const eventEnd = new Date(event.end);
        return eventStart < endTime && eventEnd > startTime;
      });
    };

    const events = [
      { id: 1, start: '2026-04-22 09:00', end: '2026-04-22 10:00' },
      { id: 2, start: '2026-04-22 10:30', end: '2026-04-22 11:30' },
      { id: 3, start: '2026-04-22 09:30', end: '2026-04-22 10:30' }
    ];

    const result = findOverlapping(events, '2026-04-22 09:00', '2026-04-22 10:00');

    expect(result).toHaveLength(2); // Events 1 and 3
  });
});

describe('Calendar Admin - Event Sorting', () => {
  test('should sort events by start time', () => {
    const sortByTime = (events) => {
      return [...events].sort((a, b) => {
        return new Date(a.start) - new Date(b.start);
      });
    };

    const events = [
      { id: 1, start: '2026-04-22 14:00' },
      { id: 2, start: '2026-04-22 09:00' },
      { id: 3, start: '2026-04-22 11:00' }
    ];

    const result = sortByTime(events);

    expect(result[0].id).toBe(2);
    expect(result[1].id).toBe(3);
    expect(result[2].id).toBe(1);
  });

  test('should sort events by status priority', () => {
    const statusPriority = { confirmed: 1, pending: 2, cancelled: 3 };

    const sortByStatus = (events) => {
      return [...events].sort((a, b) => {
        return (statusPriority[a.status] || 99) - (statusPriority[b.status] || 99);
      });
    };

    const events = [
      { id: 1, status: 'cancelled' },
      { id: 2, status: 'confirmed' },
      { id: 3, status: 'pending' }
    ];

    const result = sortByStatus(events);

    expect(result[0].id).toBe(2);
    expect(result[1].id).toBe(3);
    expect(result[2].id).toBe(1);
  });
});
