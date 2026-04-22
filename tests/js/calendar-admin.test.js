/**
 * Calendar Admin Tests
 * Tests for calendar admin functionality
 * Imports from calendar-admin-utils.js
 */

const {
  createEvent,
  validateEventTimes,
  setEventStatus,
  markPrivateLocked,
  assignRooms,
  removeRoom,
  addTags,
  isBufferEvent,
  filterByDateRange,
  findOverlapping,
  sortByTime,
  sortByStatus,
  bulkUpdateStatus,
  deleteEvents
} = require('../../assets/js/utils/calendar-admin-utils.js');

describe('Calendar Admin - Event Creation', () => {
  test('should create event with required fields', () => {
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
    const result = createEvent({ text: 'Event' });
    expect(result.success).toBe(false);
    expect(result.error).toContain('required');
  });

  test('should validate event end time after start', () => {
    expect(validateEventTimes('2026-04-22 10:00', '2026-04-22 09:00').valid).toBe(false);
    expect(validateEventTimes('2026-04-22 09:00', '2026-04-22 10:00').valid).toBe(true);
  });
});

describe('Calendar Admin - Event Status', () => {
  test('should set event status with color', () => {
    const event = { id: 1, text: 'Meeting' };
    const result = setEventStatus(event, 'confirmed');

    expect(result.success).toBe(true);
    expect(result.event.status).toBe('confirmed');
    expect(result.event.backColor).toBe('#2271b1');
  });

  test('should handle unknown status', () => {
    const result = setEventStatus({}, 'unknown');
    expect(result.success).toBe(false);
  });

  test('should mark private locked events', () => {
    const event = { id: 1, cssClass: 'event-class' };
    const result = markPrivateLocked(event);

    expect(result.cssClass).toContain('myvh-event-private-locked');
  });
});

describe('Calendar Admin - Event Resources', () => {
  test('should assign rooms as resources', () => {
    const event = { id: 1, text: 'Booking' };
    const result = assignRooms(event, ['room-1', 'room-2']);

    expect(result.resource).toEqual(['room-1', 'room-2']);
  });

  test('should remove room from event', () => {
    const event = { id: 1, resource: ['room-1', 'room-2'] };
    const result = removeRoom(event, 'room-1');

    expect(result.resource).toEqual(['room-2']);
  });
});

describe('Calendar Admin - Event Tags', () => {
  test('should add tags to event', () => {
    const event = { id: 1, tags: { isBuffer: false } };
    const result = addTags(event, { roomColour: '#ff0000', isPublic: true });

    expect(result.tags.isBuffer).toBe(false);
    expect(result.tags.roomColour).toBe('#ff0000');
    expect(result.tags.isPublic).toBe(true);
  });

  test('should identify buffer events', () => {
    const bufferEvent = { id: 1, tags: { isBuffer: true } };
    const regularEvent = { id: 2, tags: { isBuffer: false } };

    expect(isBufferEvent(bufferEvent)).toBe(true);
    expect(isBufferEvent(regularEvent)).toBe(false);
  });
});

describe('Calendar Admin - Date Range Queries', () => {
  test('should filter events by date range', () => {
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
    const events = [
      { id: 1, start: '2026-04-22 09:00', end: '2026-04-22 10:00' },
      { id: 2, start: '2026-04-22 10:30', end: '2026-04-22 11:30' },
      { id: 3, start: '2026-04-22 09:30', end: '2026-04-22 10:30' }
    ];

    const result = findOverlapping(events, '2026-04-22 09:00', '2026-04-22 10:00');

    expect(result).toHaveLength(2);
  });
});

describe('Calendar Admin - Event Sorting', () => {
  test('should sort events by start time', () => {
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

describe('Calendar Admin - Bulk Operations', () => {
  test('should bulk update event status', () => {
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
    const events = [
      { id: 1, text: 'Event 1' },
      { id: 2, text: 'Event 2' },
      { id: 3, text: 'Event 3' }
    ];

    const result = deleteEvents(events, [1, 3]);

    expect(result).toHaveLength(1);
    expect(result[0].id).toBe(2);
  });
});
