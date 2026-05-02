const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/bookings.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

describe('Bookings module', () => {
  beforeAll(() => {
    jest.restoreAllMocks();
    delete window.Bookings;
    window.eval(scriptSource);
  });

  beforeEach(() => {
    jest.restoreAllMocks();
    delete window.MyvhPortalCalendarFlow;

    document.body.innerHTML = `
      <table id="myvh-bookings-table">
        <tbody>
          <tr class="myvh-booking-group-header" data-group="group1">
            <td>Recurring Group</td>
          </tr>
          <tr
            class="myvh-bookings-table-row myvh-recurring-child"
            data-group="group1"
            data-booking-id="123"
            data-status="pending"
            data-start-date="2026-05-10"
            data-end-date="2026-05-10"
            data-start-time="09:30"
            data-end-time="10:30"
            data-room-id="45"
            data-room-name="Main Hall"
            data-customer="7"
            data-customer-name="Alex Smith"
            data-organisation="9"
            data-organisation-name="Village Group"
            data-description="Weekly meeting"
          >
            <td>
              <div class="myvh-booking-actions-inline">
                <a class="myvh-action-icon" href="#booking-edit">Edit</a>
                <a class="myvh-action-icon" href="#booking-view">View</a>
              </div>
            </td>
          </tr>
          <tr class="myvh-bookings-table-row myvh-recurring-child" data-group="group1" data-booking-id="124" data-status="confirmed">
            <td>Confirmed child</td>
          </tr>
        </tbody>
      </table>
      <div>
        <input type="checkbox" class="myvh-status-filter" value="confirmed" checked>
        <input type="checkbox" class="myvh-status-filter" value="pending" checked>
      </div>
    `;
    window.Bookings.init();
  });

  test('filters rows by status and keeps group header visible when children remain visible', () => {
    const pendingCheckbox = document.querySelector('.myvh-status-filter[value="pending"]');
    pendingCheckbox.checked = false;
    pendingCheckbox.dispatchEvent(new Event('change', { bubbles: true }));

    const pendingRow = document.querySelector('tr[data-booking-id="123"]');
    const confirmedRow = document.querySelector('tr[data-booking-id="124"]');
    const groupHeader = document.querySelector('.myvh-booking-group-header');

    expect(pendingRow.classList.contains('myvh-hidden-by-filter')).toBe(true);
    expect(confirmedRow.classList.contains('myvh-hidden-by-filter')).toBe(false);
    expect(groupHeader.classList.contains('myvh-hidden-by-filter')).toBe(false);
  });

  test('delegates booking edit click to calendar flow with prefill payload', () => {
    window.MyvhPortalCalendarFlow = {
      openEdit: jest.fn()
    };

    const editLink = document.querySelector('a[href="#booking-edit"]');
    editLink.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    expect(window.MyvhPortalCalendarFlow.openEdit).toHaveBeenCalledTimes(1);
    expect(window.MyvhPortalCalendarFlow.openEdit).toHaveBeenCalledWith(123, {
      prefill: expect.objectContaining({
        start: '2026-05-10 09:30:00',
        end: '2026-05-10 10:30:00',
        roomId: '45',
        customerId: '7',
        organisationId: '9',
        description: 'Weekly meeting',
        status: 'pending'
      })
    });
  });

  test('waits for booking flow ready event when flow is not immediately available', () => {
    const viewLink = document.querySelector('a[href="#booking-view"]');
    viewLink.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    const openView = jest.fn();
    window.MyvhPortalCalendarFlow = { openView: openView };

    document.dispatchEvent(new Event('myvh:portal-booking-flow-ready'));

    expect(openView).toHaveBeenCalledTimes(1);
    expect(openView).toHaveBeenCalledWith(123, {
      prefill: expect.objectContaining({
        roomName: 'Main Hall',
        customerName: 'Alex Smith',
        organisationName: 'Village Group'
      })
    });
  });
});
