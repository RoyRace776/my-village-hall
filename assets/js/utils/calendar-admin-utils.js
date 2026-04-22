/**
 * Calendar Admin Utilities - Exportable for Testing
 * Utility functions for calendar event management
 */

/**
 * Create event object with validation
 * @param {object} data - Event data
 * @returns {object} - Result with event or error
 */
function createEvent(data) {
  if (!data.start || !data.end) {
    return { success: false, error: 'Start and end times are required' };
  }

  const event = {
    id: data.id || null,
    text: data.text || 'Untitled',
    start: data.start,
    end: data.end,
    resource: data.resource || [],
    tags: data.tags || {}
  };

  return { success: true, event };
}

/**
 * Validate event times
 * @param {string} start - Start time
 * @param {string} end - End time
 * @returns {object} - Validation result
 */
function validateEventTimes(start, end) {
  const startTime = new Date(start);
  const endTime = new Date(end);

  if (endTime <= startTime) {
    return { valid: false, message: 'End time must be after start time' };
  }

  return { valid: true };
}

/**
 * Set event status with corresponding color
 * @param {object} event - Event object
 * @param {string} status - Status value
 * @returns {object} - Result with updated event or error
 */
function setEventStatus(event, status) {
  const statusColors = {
    confirmed: '#2271b1',
    pending: '#f0a500',
    cancelled: '#9aa0a6',
    completed: '#2d8f45'
  };

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
}

/**
 * Mark event as private/locked
 * @param {object} event - Event object
 * @returns {object} - Updated event
 */
function markPrivateLocked(event) {
  const cssClass = (event.cssClass || '') + ' myvh-event-private-locked';
  return { ...event, cssClass: cssClass.trim() };
}

/**
 * Assign rooms to event
 * @param {object} event - Event object
 * @param {array} roomIds - Array of room IDs
 * @returns {object} - Updated event
 */
function assignRooms(event, roomIds) {
  return {
    ...event,
    resource: Array.isArray(roomIds) ? roomIds : []
  };
}

/**
 * Remove room from event
 * @param {object} event - Event object
 * @param {string} roomId - Room ID to remove
 * @returns {object} - Updated event
 */
function removeRoom(event, roomId) {
  return {
    ...event,
    resource: (event.resource || []).filter(r => r !== roomId)
  };
}

/**
 * Add tags to event
 * @param {object} event - Event object
 * @param {object} tags - Tags to add
 * @returns {object} - Updated event
 */
function addTags(event, tags) {
  return {
    ...event,
    tags: { ...event.tags, ...tags }
  };
}

/**
 * Check if event is a buffer event
 * @param {object} event - Event object
 * @returns {boolean} - True if buffer event
 */
function isBufferEvent(event) {
  return event && event.tags && event.tags.isBuffer === true;
}

/**
 * Filter events by date range
 * @param {array} events - Array of events
 * @param {string} startDate - Start date
 * @param {string} endDate - End date
 * @returns {array} - Filtered events
 */
function filterByDateRange(events, startDate, endDate) {
  const start = new Date(startDate);
  const end = new Date(endDate);

  return (events || []).filter(event => {
    const eventStart = new Date(event.start);
    return eventStart >= start && eventStart <= end;
  });
}

/**
 * Find overlapping events
 * @param {array} events - Array of events
 * @param {string} start - Start time
 * @param {string} end - End time
 * @returns {array} - Overlapping events
 */
function findOverlapping(events, start, end) {
  const startTime = new Date(start);
  const endTime = new Date(end);

  return (events || []).filter(event => {
    const eventStart = new Date(event.start);
    const eventEnd = new Date(event.end);
    return eventStart < endTime && eventEnd > startTime;
  });
}

/**
 * Sort events by start time
 * @param {array} events - Array of events
 * @returns {array} - Sorted events
 */
function sortByTime(events) {
  return [...(events || [])].sort((a, b) => {
    return new Date(a.start) - new Date(b.start);
  });
}

/**
 * Sort events by status priority
 * @param {array} events - Array of events
 * @param {object} priority - Status priority map
 * @returns {array} - Sorted events
 */
function sortByStatus(events, priority = {}) {
  const defaultPriority = {
    confirmed: 1,
    pending: 2,
    cancelled: 3,
    ...priority
  };

  return [...(events || [])].sort((a, b) => {
    const priorityA = defaultPriority[a.status] || 99;
    const priorityB = defaultPriority[b.status] || 99;
    return priorityA - priorityB;
  });
}

/**
 * Bulk update event status
 * @param {array} events - Array of events
 * @param {string} newStatus - New status
 * @returns {array} - Updated events
 */
function bulkUpdateStatus(events, newStatus) {
  return (events || []).map(event => ({ ...event, status: newStatus }));
}

/**
 * Bulk delete events
 * @param {array} events - Array of events
 * @param {array} idsToDelete - IDs to delete
 * @returns {array} - Remaining events
 */
function deleteEvents(events, idsToDelete) {
  return (events || []).filter(e => !idsToDelete.includes(e.id));
}

// For Jest/Node.js module compatibility
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
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
    deleteEvents,
  };
}
