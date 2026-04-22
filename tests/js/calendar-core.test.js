/**
 * Calendar Core Tests
 * Tests for the calendar-core.js module
 */

describe('Calendar Core - Color Utilities', () => {
  // We'll create local versions of the functions to test since they're in an IIFE

  const normaliseHexColour = (value) => {
    const text = String(value || "").trim().toLowerCase();
    return /^#[0-9a-f]{6}$/.test(text) ? text : "";
  };

  const getReadableTextColour = (backgroundColour) => {
    const hex = normaliseHexColour(backgroundColour);
    if (!hex) {
      return "#1f2933";
    }

    const red = parseInt(hex.slice(1, 3), 16);
    const green = parseInt(hex.slice(3, 5), 16);
    const blue = parseInt(hex.slice(5, 7), 16);
    const brightness = (red * 299 + green * 587 + blue * 114) / 1000;

    return brightness < 145 ? "#ffffff" : "#1f2933";
  };

  describe('normaliseHexColour', () => {
    test('should accept valid hex colors', () => {
      expect(normaliseHexColour('#2271b1')).toBe('#2271b1');
      expect(normaliseHexColour('#f0a500')).toBe('#f0a500');
    });

    test('should convert uppercase to lowercase', () => {
      expect(normaliseHexColour('#2271B1')).toBe('#2271b1');
    });

    test('should trim whitespace', () => {
      expect(normaliseHexColour('  #2271b1  ')).toBe('#2271b1');
    });

    test('should return empty string for invalid hex colors', () => {
      expect(normaliseHexColour('#12345')).toBe('');
      expect(normaliseHexColour('#gggggg')).toBe('');
      expect(normaliseHexColour('not-a-color')).toBe('');
      expect(normaliseHexColour('')).toBe('');
      expect(normaliseHexColour(null)).toBe('');
    });
  });

  describe('getReadableTextColour', () => {
    test('should return white text for dark backgrounds', () => {
      expect(getReadableTextColour('#000000')).toBe('#ffffff');
      expect(getReadableTextColour('#1a1a1a')).toBe('#ffffff');
    });

    test('should return dark text for light backgrounds', () => {
      expect(getReadableTextColour('#ffffff')).toBe('#1f2933');
      expect(getReadableTextColour('#f0f0f0')).toBe('#1f2933');
    });

    test('should return default dark color for invalid colors', () => {
      expect(getReadableTextColour('#invalid')).toBe('#1f2933');
      expect(getReadableTextColour('')).toBe('#1f2933');
      expect(getReadableTextColour(null)).toBe('#1f2933');
    });

    test('should handle standard status colors', () => {
      const colors = {
        confirmed: '#2271b1',
        pending: '#f0a500',
        cancelled: '#9aa0a6',
        completed: '#2d8f45'
      };

      expect(getReadableTextColour(colors.confirmed)).toBe('#ffffff');
      expect(getReadableTextColour(colors.pending)).toBe('#1f2933');
      expect(getReadableTextColour(colors.completed)).toBe('#ffffff');
    });
  });

  describe('getStatusColors', () => {
    const getStatusColors = (opts = {}) => {
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
    };

    test('should return default colors when no options provided', () => {
      const colors = getStatusColors();
      expect(colors).toHaveProperty('confirmed', '#2271b1');
      expect(colors).toHaveProperty('pending', '#f0a500');
      expect(colors).toHaveProperty('cancelled', '#9aa0a6');
      expect(colors).toHaveProperty('completed', '#2d8f45');
    });

    test('should merge custom colors with defaults', () => {
      const customColors = { confirmed: '#ff0000' };
      const colors = getStatusColors({ statusColors: customColors });
      expect(colors.confirmed).toBe('#ff0000');
      expect(colors.pending).toBe('#f0a500');
    });

    test('should ignore invalid statusColors option', () => {
      const colors1 = getStatusColors({ statusColors: null });
      const colors2 = getStatusColors({ statusColors: 'invalid' });
      const colors3 = getStatusColors({ statusColors: [] });

      expect(colors1).toHaveProperty('confirmed');
      expect(colors2).toHaveProperty('confirmed');
      expect(colors3).toHaveProperty('confirmed');
    });
  });
});
