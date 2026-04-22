/**
 * Calendar Core Tests
 * Tests for the calendar-core.js module
 * Imports from calendar-core-utils.js for testable functions
 */

const {
  normaliseHexColour,
  getReadableTextColour,
  getStatusColors,
  applyEventStatusColors
} = require('../../assets/js/utils/calendar-core-utils.js');

describe('Calendar Core - Color Utilities', () => {
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

  describe('applyEventStatusColors', () => {
    test('should apply status colors to events', () => {
      const events = [
        { id: 1, tags: { status: 'confirmed' } },
        { id: 2, tags: { status: 'pending' } }
      ];

      const result = applyEventStatusColors(events, getStatusColors());

      expect(result[0].barColor).toBe('#2271b1');
      expect(result[1].barColor).toBe('#f0a500');
    });

    test('should handle buffer events', () => {
      const events = [{ id: 1, tags: { isBuffer: true } }];
      const result = applyEventStatusColors(events, getStatusColors());

      expect(result[0].moveDisabled).toBe(true);
      expect(result[0].resizeDisabled).toBe(true);
      expect(result[0].clickDisabled).toBe(true);
    });

    test('should handle empty event array', () => {
      const result = applyEventStatusColors([], getStatusColors());
      expect(result).toEqual([]);
    });
  });
});
