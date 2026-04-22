/**
 * Calendar Core Utilities - Exportable for Testing
 * Extracts testable functions from CalendarCore IIFE
 */

// These utility functions are extracted from window.CalendarCore
// to make them testable with Jest

/**
 * Normalize and validate a hex color string
 * @param {string} value - The color value to validate
 * @returns {string} - Valid hex color or empty string
 */
function normaliseHexColour(value) {
  const text = String(value || "").trim().toLowerCase();
  return /^#[0-9a-f]{6}$/.test(text) ? text : "";
}

/**
 * Get readable text color based on background brightness
 * @param {string} backgroundColour - Hex color string
 * @returns {string} - White (#ffffff) for dark, dark (#1f2933) for light
 */
function getReadableTextColour(backgroundColour) {
  const hex = normaliseHexColour(backgroundColour);
  if (!hex) {
    return "#1f2933";
  }

  const red = parseInt(hex.slice(1, 3), 16);
  const green = parseInt(hex.slice(3, 5), 16);
  const blue = parseInt(hex.slice(5, 7), 16);
  const brightness = (red * 299 + green * 587 + blue * 114) / 1000;

  return brightness < 145 ? "#ffffff" : "#1f2933";
}

/**
 * Get status colors with custom overrides
 * @param {object} opts - Options containing statusColors
 * @returns {object} - Merged status colors
 */
function getStatusColors(opts = {}) {
  const DEFAULT_STATUS_COLORS = {
    confirmed: "#2271b1",
    pending: "#f0a500",
    cancelled: "#9aa0a6",
    completed: "#2d8f45",
  };

  if (!opts.statusColors || typeof opts.statusColors !== "object") {
    return DEFAULT_STATUS_COLORS;
  }

  return {
    ...DEFAULT_STATUS_COLORS,
    ...opts.statusColors,
  };
}

/**
 * Apply status colors to events
 * @param {array} events - Array of event objects
 * @param {object} statusColors - Color mapping for statuses
 * @returns {array} - Events with applied colors and styling
 */
function applyEventStatusColors(events, statusColors) {
  const DEFAULT_STATUS_COLORS = {
    confirmed: "#2271b1",
    pending: "#f0a500",
    cancelled: "#9aa0a6",
    completed: "#2d8f45",
  };

  const colors = statusColors || DEFAULT_STATUS_COLORS;

  return (events || []).map(function(event) {
    const tags = event && event.tags ? event.tags : {};
    const existingCssClass = String(event && event.cssClass ? event.cssClass : "").trim();

    // Buffer events: neutral styling
    if (tags.isBuffer) {
      const roomColour = normaliseHexColour(tags.roomColour || tags.colour || event.backColor);
      const backgroundColour = roomColour || "#f7f3ee";
      const bufferAccent = "#9aa0a6";

      return {
        ...event,
        backColor: backgroundColour,
        fontColor: getReadableTextColour(backgroundColour),
        borderColor: bufferAccent,
        barColor: bufferAccent,
        moveDisabled: true,
        resizeDisabled: true,
        clickDisabled: true,
      };
    }

    // Regular events: status-based styling
    const status = String(tags.status || "").toLowerCase();
    const fallbackColor = colors.confirmed || DEFAULT_STATUS_COLORS.confirmed;
    const accentColor = colors[status] || fallbackColor;
    const roomColour = normaliseHexColour(tags.roomColour || tags.colour || event.backColor);
    const backgroundColour = roomColour || "#f7f3ee";

    return {
      ...event,
      backColor: backgroundColour,
      fontColor: getReadableTextColour(backgroundColour),
      borderColor: accentColor,
      barColor: accentColor,
      cssClass: existingCssClass,
    };
  });
}

// For Jest/Node.js module compatibility
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    normaliseHexColour,
    getReadableTextColour,
    getStatusColors,
    applyEventStatusColors,
  };
}
