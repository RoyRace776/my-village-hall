/**
 * Portal Dialog Tests
 * Tests for portal dialog functions
 * Imports from portal-dialog-utils.js
 */

const {
  getSiteName,
  getDialogStyles,
  createAlertDialog,
  createConfirmDialog,
  validateDialogResponse
} = require('../../assets/js/utils/portal-dialog-utils.js');

describe('MyvhPortalDialog - Site Name Resolution', () => {
  afterEach(() => {
    delete window.myvhPortal;
    delete window.myvhCal;
  });

  test('should get site name from myvhPortal', () => {
    window.myvhPortal = { site_name: 'Test Village Hall' };
    expect(getSiteName()).toBe('Test Village Hall');
  });

  test('should get site name from myvhPortal.siteName', () => {
    window.myvhPortal = { siteName: 'Village Hall Portal' };
    expect(getSiteName()).toBe('Village Hall Portal');
  });

  test('should fallback to myvhCal.site_name', () => {
    window.myvhCal = { site_name: 'Calendar Site' };
    expect(getSiteName()).toBe('Calendar Site');
  });

  test('should fallback to document.title', () => {
    const originalTitle = document.title;
    document.title = 'Page Title';
    expect(getSiteName()).toBe('Page Title');
    document.title = originalTitle;
  });

  test('should use default when no name available', () => {
    const result = getSiteName();
    expect(typeof result).toBe('string');
    expect(result.length > 0).toBe(true);
  });
});

describe('MyvhPortalDialog - Style Management', () => {
  test('should get dialog styles', () => {
    const styles = getDialogStyles();
    expect(typeof styles).toBe('string');
    expect(styles).toContain('myvh-portal-dialog');
    expect(styles).toContain('myvh-portal-dialog-backdrop');
  });

  test('should include critical CSS classes in styles', () => {
    const styles = getDialogStyles();
    const cssClasses = [
      'myvh-portal-dialog-backdrop',
      'myvh-portal-dialog__head',
      'myvh-portal-dialog__body',
      'myvh-portal-dialog__actions',
      'myvh-portal-dialog__btn'
    ];

    cssClasses.forEach(className => {
      expect(styles).toContain(className);
    });
  });
});

describe('MyvhPortalDialog - Alert Dialog', () => {
  test('should create alert dialog with message', () => {
    const dialog = createAlertDialog('This is an alert', { title: 'Warning' });

    expect(dialog.type).toBe('alert');
    expect(dialog.message).toBe('This is an alert');
    expect(dialog.title).toBe('Warning');
    expect(dialog.buttons).toHaveLength(1);
    expect(dialog.buttons[0].label).toBe('OK');
  });

  test('should use default title when not provided', () => {
    const dialog = createAlertDialog('Message');
    expect(dialog.title).toBeTruthy();
  });
});

describe('MyvhPortalDialog - Confirm Dialog', () => {
  test('should create confirm dialog with two buttons', () => {
    const dialog = createConfirmDialog('Are you sure?', { okLabel: 'Yes', cancelLabel: 'No' });

    expect(dialog.type).toBe('confirm');
    expect(dialog.message).toBe('Are you sure?');
    expect(dialog.buttons).toHaveLength(2);
    expect(dialog.buttons[0].label).toBe('No');
    expect(dialog.buttons[1].label).toBe('Yes');
  });

  test('should use default button labels', () => {
    const dialog = createConfirmDialog('Proceed?');
    expect(dialog.buttons[0].label).toBe('Cancel');
    expect(dialog.buttons[1].label).toBe('OK');
  });

  test('should mark OK button as primary', () => {
    const dialog = createConfirmDialog('Confirm?');
    expect(dialog.buttons[1].primary).toBe(true);
    expect(dialog.buttons[0].primary).toBe(false);
  });
});

describe('MyvhPortalDialog - Response Validation', () => {
  test('should validate correct response format', () => {
    const result = validateDialogResponse({ type: 'alert' });
    expect(result.valid).toBe(true);
  });

  test('should reject null response', () => {
    const result = validateDialogResponse(null);
    expect(result.valid).toBe(false);
    expect(result.error).toContain('Invalid');
  });

  test('should reject non-object response', () => {
    const result = validateDialogResponse('string');
    expect(result.valid).toBe(false);
  });

  test('should reject response without type', () => {
    const result = validateDialogResponse({ message: 'test' });
    expect(result.valid).toBe(false);
    expect(result.error).toContain('type');
  });
});
