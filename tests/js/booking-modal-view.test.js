const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/booking-modal-view.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

function buildModalFixture() {
  document.body.innerHTML = `
    <div id="myvh-booking-modal-view" class="hidden">
      <form id="myvh-booking-form-view">
        <button type="submit">View Booking</button>
        <button type="button" class="myvh-cancel">Cancel</button>
        <button type="button" class="myvh-edit-booking">Edit</button>

        <div id="myvh-modal-view-edit-reason" style="display:none"></div>

        <table>
          <tr id="myvh-modal-view-no-invoice-row"><td><input type="checkbox" name="no_invoice_required"></td></tr>
          <tr id="myvh-modal-end-date-row" style="display:none"><td><input id="myvh-modal-end-date"></td></tr>
        </table>

        <select name="room_id"><option value="">Select...</option></select>
        <select name="customer_id"><option value="">Select...</option></select>
        <select name="organisation_id"><option value="">Select...</option></select>

        <input name="status" value="">
        <textarea name="description"></textarea>
        <input type="checkbox" name="public">

        <input id="myvh-modal-start-date" value="">
        <input id="myvh-modal-start-time" value="">
        <input id="myvh-modal-end-time" value="">
      </form>
    </div>
  `;
}

describe('BookingModalView', () => {
  beforeEach(() => {
    jest.restoreAllMocks();
    delete window.BookingModalView;

    buildModalFixture();

    window.MyvhFlatpickr = {
      initWithin: jest.fn(),
      setValue: jest.fn((input, value) => {
        input.value = value;
      })
    };

    window.fetch = jest.fn().mockResolvedValue({
      json: () => Promise.resolve({
        success: true,
        data: {
          booking: {
            RoomId: '14',
            RoomName: 'Main Hall',
            CustomerId: '7',
            CustomerName: 'Alex Smith',
            OrganisationId: '9',
            OrganisationName: 'Village Group',
            StartDate: '2026-06-02',
            StartTime: '09:00:00',
            EndDate: '2026-06-02',
            EndTime: '10:00:00',
            Status: 'pending',
            Description: 'Board meeting',
            Public: 1,
            NoInvoiceRequired: 0
          },
          addons: [],
          can_edit: false,
          edit_reason: 'Locked booking',
          can_manage_no_invoice_required: false
        }
      })
    });

    window.eval(scriptSource);
  });

  test('uses complete payload without fetch and enables edit button when allowed', async () => {
    const onOpen = jest.fn();

    window.BookingModalView.init({
      ajax_url: '/ajax',
      nonce: 'portal-nonce',
      context: 'portal',
      onOpen: onOpen
    });

    const payload = {
      booking: {
        RoomId: '14',
        RoomName: 'Main Hall',
        CustomerId: '7',
        CustomerName: 'Alex Smith',
        OrganisationId: '9',
        OrganisationName: 'Village Group',
        StartDate: '2026-06-02',
        StartTime: '09:00:00',
        EndDate: '2026-06-02',
        EndTime: '10:00:00',
        Status: 'confirmed',
        Description: 'Confirmed booking',
        Public: 1,
        NoInvoiceRequired: 1
      },
      addons: [],
      canEdit: true,
      editReason: '',
      canManageNoInvoiceRequired: true
    };

    window.BookingModalView.open({ bookingId: 40, payload: payload });
    await flushPromises();

    expect(window.fetch).not.toHaveBeenCalled();
    expect(document.querySelector('[name="room_id"]').value).toBe('14');
    expect(document.querySelector('[name="status"]').value).toBe('Confirmed');
    expect(document.querySelector('[name="description"]').value).toBe('Confirmed booking');
    expect(document.querySelector('.myvh-edit-booking').style.display).toBe('');
    expect(document.getElementById('myvh-modal-view-no-invoice-row').style.display).toBe('');
    expect(document.querySelector('[name="no_invoice_required"]').checked).toBe(true);
    expect(onOpen).toHaveBeenCalled();
  });

  test('fetches booking when payload is incomplete and shows edit reason when locked', async () => {
    window.BookingModalView.init({
      ajax_url: '/ajax',
      nonce: 'portal-nonce',
      context: 'portal'
    });

    window.BookingModalView.open({ bookingId: 41, payload: { booking: {} } });

    await flushPromises();
    await flushPromises();

    expect(window.fetch).toHaveBeenCalledTimes(1);
    expect(document.querySelector('.myvh-edit-booking').style.display).toBe('none');
    expect(document.getElementById('myvh-modal-view-edit-reason').textContent).toBe('Locked booking');
    expect(document.getElementById('myvh-modal-view-edit-reason').style.display).toBe('');
  });

  test('fires onEdit with booking id when edit button clicked', async () => {
    const onEdit = jest.fn();

    window.BookingModalView.init({
      ajax_url: '/ajax',
      nonce: 'portal-nonce',
      context: 'portal',
      onEdit: onEdit
    });

    const payload = {
      booking: {
        RoomId: '14',
        RoomName: 'Main Hall',
        CustomerId: '7',
        CustomerName: 'Alex Smith',
        OrganisationId: '9',
        OrganisationName: 'Village Group',
        StartDate: '2026-06-02',
        StartTime: '09:00:00',
        EndDate: '2026-06-02',
        EndTime: '10:00:00',
        Status: 'confirmed',
        Description: 'Editable booking',
        Public: 1,
        NoInvoiceRequired: 1
      },
      addons: [],
      canEdit: true,
      editReason: '',
      canManageNoInvoiceRequired: true
    };

    window.BookingModalView.open({ bookingId: 99, payload: payload });
    await flushPromises();

    document.querySelector('.myvh-edit-booking').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    expect(onEdit).toHaveBeenCalledWith({ bookingId: 99 });
    expect(document.getElementById('myvh-booking-modal-view').classList.contains('hidden')).toBe(true);
  });
});
