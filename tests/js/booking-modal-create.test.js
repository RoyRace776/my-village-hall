const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/booking-modal-create.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

function buildModalFixture() {
  document.body.innerHTML = `
    <div id="myvh-booking-modal-create" class="hidden">
      <h2>Create Booking</h2>
      <div class="myvh-account-hint"></div>
      <form id="myvh-booking-form-create">
        <button type="submit">Create Booking</button>
        <button type="button" class="myvh-cancel">Cancel</button>
        <button type="button" class="myvh-delete-booking" style="display:none">Delete</button>

        <input name="booking_id" value="">
        <input name="start" value="">
        <input name="end" value="">
        <input name="text" value="">

        <table>
          <tr id="myvh-modal-status-row"><td><input name="status" value="pending"></td></tr>
          <tr id="myvh-modal-no-invoice-row"><td><input type="checkbox" name="no_invoice_required"></td></tr>
          <tr id="myvh-modal-end-date-row"><td><input id="myvh-modal-end-date" value=""></td></tr>
          <tr id="myvh-modal-edit-scope-row" style="display:none"><td>
            <input type="radio" name="edit_scope" value="single" checked>
            <input type="radio" name="edit_scope" value="series">
          </td></tr>
        </table>

        <input id="myvh-modal-start-date" value="2026-05-10">
        <input id="myvh-modal-start-time" value="09:00">
        <input id="myvh-modal-end-time" value="10:00">

        <select name="room_id"><option value="">Select...</option></select>
        <select name="customer_id"><option value="">Select...</option></select>
        <select name="organisation_id"><option value="">Select...</option></select>

        <input type="checkbox" name="public">

        <div id="myvh-modal-recurring-options" style="display:none"></div>
        <input id="myvh-modal-is-recurring" type="checkbox">
        <select id="myvh-modal-rec-type" name="recurrence_type">
          <option value="daily">Daily</option>
          <option value="monthly_day">Monthly Day</option>
        </select>
        <div id="myvh-modal-interval-row"></div>
        <div id="myvh-modal-monthly-day-row" style="display:none"></div>
        <span id="myvh-modal-interval-label"></span>
        <input name="recurrence_interval" value="1">
        <input name="recurrence_interval_md" value="1">

        <input type="radio" name="recurrence_end_type" value="date" checked>
        <input type="radio" name="recurrence_end_type" value="count">
        <input name="max_occurrences" value="">
        <input name="recurrence_end_date" value="2026-12-31">
      </form>
    </div>
  `;
}

describe('BookingModalCreate', () => {
  beforeEach(() => {
    jest.restoreAllMocks();
    delete window.BookingModalCreate;

    buildModalFixture();

    window.MyvhFlatpickr = {
      initWithin: jest.fn(),
      setValue: jest.fn((input, value) => {
        input.value = value;
      }),
      syncState: jest.fn()
    };

    window.fetch = jest.fn().mockResolvedValue({
      json: () => Promise.resolve({ success: true, data: { booking_id: 321 } })
    });

    window.eval(scriptSource);
  });

  test('applies no-invoice visibility on init based on permissions', () => {
    const checkbox = document.querySelector('[name="no_invoice_required"]');
    const row = document.getElementById('myvh-modal-no-invoice-row');

    window.BookingModalCreate.init({
      ajax_url: '/ajax',
      nonce: 'nonce-1',
      canManageNoInvoiceRequired: false
    });

    expect(row.style.display).toBe('none');
    expect(checkbox.disabled).toBe(true);

    window.BookingModalCreate.init({
      ajax_url: '/ajax',
      nonce: 'nonce-1',
      canManageNoInvoiceRequired: true
    });

    expect(row.style.display).toBe('');
    expect(checkbox.disabled).toBe(false);
  });

  test('does not submit when beforeSubmit returns false', () => {
    window.BookingModalCreate.init({
      ajax_url: '/ajax',
      nonce: 'nonce-1',
      beforeSubmit: () => false
    });

    const form = document.getElementById('myvh-booking-form-create');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    expect(window.fetch).not.toHaveBeenCalled();
  });

  test('submits portal create payload and calls onSuccess', async () => {
    const onSuccess = jest.fn();
    window.myvhCal = {
      currentCustomerId: 55,
      defaultOrganisationId: 88
    };

    document.querySelector('[name="public"]').checked = true;
    document.querySelector('[name="no_invoice_required"]').checked = true;
    document.querySelector('[name="room_id"]').innerHTML = '<option value="14">Main Hall</option>';
    document.querySelector('[name="room_id"]').value = '14';

    window.BookingModalCreate.init({
      ajax_url: '/ajax',
      nonce: 'portal-nonce',
      context: 'portal',
      canManageNoInvoiceRequired: true,
      onSuccess: onSuccess
    });

    const form = document.getElementById('myvh-booking-form-create');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    await flushPromises();
    await flushPromises();

    expect(window.fetch).toHaveBeenCalledTimes(1);
    const request = window.fetch.mock.calls[0][1];
    expect(request.method).toBe('POST');

    const payload = Object.fromEntries(request.body.entries());
    expect(payload.action).toBe('myvh_portal_create_booking');
    expect(payload.nonce).toBe('portal-nonce');
    expect(payload.room_id).toBe('14');
    expect(payload.customer_id).toBe('55');
    expect(payload.organisation_id).toBe('88');
    expect(payload.public).toBe('1');
    expect(payload.no_invoice_required).toBe('1');

    expect(onSuccess).toHaveBeenCalledWith({ booking_id: 321 });
  });

  test('opens in edit mode, loads booking, and reveals recurring edit scope', async () => {
    window.fetch.mockResolvedValueOnce({
      json: () => Promise.resolve({
        success: true,
        data: {
          booking: {
            RoomId: '14',
            CustomerId: '7',
            OrganisationId: '9',
            Description: 'Edit booking text',
            Status: 'confirmed',
            StartDate: '2026-05-15',
            StartTime: '08:30:00',
            EndDate: '2026-05-16',
            EndTime: '09:30:00',
            Public: 1,
            NoInvoiceRequired: 1,
            RecurringPatternId: 99
          },
          addons: [],
          can_delete: true,
          delete_reason: ''
        }
      })
    });

    window.BookingModalCreate.init({
      ajax_url: '/ajax',
      nonce: 'portal-nonce',
      context: 'portal',
      loadRooms: () => Promise.resolve([{ Id: '14', Name: 'Main Hall', AllowMultiDayBookings: 1 }]),
      loadCustomers: () => Promise.resolve([{ Id: '7', Name: 'Alex Smith' }]),
      loadOrganisations: () => Promise.resolve([{ Id: '9', Name: 'Village Group' }])
    });

    window.BookingModalCreate.open({ editMode: true, bookingId: 12 });

    await flushPromises();
    await flushPromises();
    await flushPromises();

    const modal = document.getElementById('myvh-booking-modal-create');
    const scopeRow = document.getElementById('myvh-modal-edit-scope-row');
    const bookingId = document.querySelector('[name="booking_id"]').value;

    expect(modal.classList.contains('hidden')).toBe(false);
    expect(bookingId).toBe('12');
    expect(scopeRow.style.display).toBe('');
    expect(document.querySelector('h2').textContent).toBe('Edit Booking');
    expect(document.querySelector('.myvh-delete-booking').style.display).toBe('');
  });
});
