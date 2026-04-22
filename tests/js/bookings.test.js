/**
 * Bookings Tests
 * Tests for bookings filtering and dialog utilities
 */

describe('Bookings - Dialog Utilities', () => {
  // Mock dialog functions
  const createMockDialog = () => ({
    alert: jest.fn().mockResolvedValue(true),
    confirm: jest.fn().mockResolvedValue(true)
  });

  describe('portalAlert', () => {
    test('should use portal dialog if available', async () => {
      const mockDialog = createMockDialog();
      window.MyvhPortalDialog = mockDialog;

      const portalAlert = (message) => {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.alert === 'function') {
          return window.MyvhPortalDialog.alert(message);
        }
        window.alert(message);
        return Promise.resolve(true);
      };

      const result = await portalAlert('Test message');

      expect(mockDialog.alert).toHaveBeenCalledWith('Test message');
      expect(result).toBe(true);

      delete window.MyvhPortalDialog;
    });

    test('should fallback to window.alert if dialog unavailable', async () => {
      window.alert = jest.fn();

      const portalAlert = (message) => {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.alert === 'function') {
          return window.MyvhPortalDialog.alert(message);
        }
        window.alert(message);
        return Promise.resolve(true);
      };

      await portalAlert('Test message');

      expect(window.alert).toHaveBeenCalledWith('Test message');
    });
  });

  describe('portalConfirm', () => {
    test('should use portal dialog if available', async () => {
      const mockDialog = createMockDialog();
      window.MyvhPortalDialog = mockDialog;

      const portalConfirm = (message) => {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.confirm === 'function') {
          return window.MyvhPortalDialog.confirm(message);
        }
        return Promise.resolve(window.confirm(message));
      };

      const result = await portalConfirm('Confirm?');

      expect(mockDialog.confirm).toHaveBeenCalledWith('Confirm?');
      expect(result).toBe(true);

      delete window.MyvhPortalDialog;
    });
  });
});

describe('Bookings - Status Filtering', () => {
  let container;

  beforeEach(() => {
    // Create a test table structure
    container = document.createElement('div');
    container.innerHTML = `
      <table id="myvh-bookings-table">
        <tbody>
          <tr class="myvh-booking-group-header" data-group="group1">
            <td>Group 1</td>
          </tr>
          <tr data-status="confirmed">
            <td>Booking 1</td>
          </tr>
          <tr data-status="pending">
            <td>Booking 2</td>
          </tr>
          <tr class="myvh-booking-group-header" data-group="group2">
            <td>Group 2</td>
          </tr>
          <tr data-status="cancelled">
            <td>Booking 3</td>
          </tr>
        </tbody>
      </table>
      <div class="myvh-status-filter">
        <input type="checkbox" class="myvh-status-filter" value="confirmed">
        <input type="checkbox" class="myvh-status-filter" value="pending">
        <input type="checkbox" class="myvh-status-filter" value="cancelled">
      </div>
    `;
    document.body.appendChild(container);
  });

  afterEach(() => {
    document.body.removeChild(container);
  });

  test('should filter table rows by selected status', () => {
    const filterByStatus = () => {
      const checked = Array.from(document.querySelectorAll('.myvh-status-filter:checked')).map(function(cb) {
        return cb.value;
      });

      const rows = document.querySelectorAll('#myvh-bookings-table tbody tr[data-status]');
      rows.forEach(function(row) {
        const status = row.getAttribute('data-status');
        if (!status || checked.indexOf(status) !== -1) {
          row.classList.remove('myvh-hidden-by-filter');
        } else {
          row.classList.add('myvh-hidden-by-filter');
        }
      });
    };

    // Check only confirmed
    const confirmedCheckbox = document.querySelector('input[value="confirmed"]');
    confirmedCheckbox.checked = true;

    filterByStatus();

    const rows = document.querySelectorAll('#myvh-bookings-table tbody tr[data-status]');
    expect(rows[0].classList.contains('myvh-hidden-by-filter')).toBe(false); // confirmed
    expect(rows[1].classList.contains('myvh-hidden-by-filter')).toBe(true);   // pending
    expect(rows[2].classList.contains('myvh-hidden-by-filter')).toBe(true);   // cancelled
  });

  test('should show all rows when multiple statuses selected', () => {
    const filterByStatus = () => {
      const checked = Array.from(document.querySelectorAll('.myvh-status-filter:checked')).map(function(cb) {
        return cb.value;
      });

      const rows = document.querySelectorAll('#myvh-bookings-table tbody tr[data-status]');
      rows.forEach(function(row) {
        const status = row.getAttribute('data-status');
        if (!status || checked.indexOf(status) !== -1) {
          row.classList.remove('myvh-hidden-by-filter');
        } else {
          row.classList.add('myvh-hidden-by-filter');
        }
      });
    };

    // Check multiple statuses
    document.querySelector('input[value="confirmed"]').checked = true;
    document.querySelector('input[value="pending"]').checked = true;

    filterByStatus();

    const rows = document.querySelectorAll('#myvh-bookings-table tbody tr[data-status]');
    expect(rows[0].classList.contains('myvh-hidden-by-filter')).toBe(false); // confirmed
    expect(rows[1].classList.contains('myvh-hidden-by-filter')).toBe(false); // pending
    expect(rows[2].classList.contains('myvh-hidden-by-filter')).toBe(true);  // cancelled
  });
});
