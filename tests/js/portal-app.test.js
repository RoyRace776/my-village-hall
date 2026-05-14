const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/portal-app.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

function mockHtmlResponse(html, options = {}) {
  const contentType = options.contentType || 'text/html';
  const ok = Object.prototype.hasOwnProperty.call(options, 'ok') ? options.ok : true;
  const status = Object.prototype.hasOwnProperty.call(options, 'status') ? options.status : 200;

  return {
    ok,
    status,
    headers: {
      get: (name) => (String(name || '').toLowerCase() === 'content-type' ? contentType : ''),
    },
    text: () => Promise.resolve(html),
  };
}

describe('Portal app integration behaviors', () => {
  beforeAll(async () => {
    document.body.innerHTML = `
      <nav data-portal-nav>
        <div data-portal-nav-group class="myvh-portal-nav-group">
          <button type="button" class="myvh-portal-nav-toggle" aria-expanded="false">
            <span>Menu</span>
          </button>
          <a href="#dashboard">Dashboard</a>
        </div>
      </nav>
      <div id="portal-content"></div>
    `;

    window.myvhPortal = {
      ajax_url: '/portal-ajax-endpoint',
      nonce: 'portal-nonce'
    };
    window.MyvhFlatpickr = { initWithin: jest.fn() };
    window.fetch = jest.fn().mockResolvedValue(
      mockHtmlResponse('<div id="myvh-initial">Initial</div>')
    );

    window.eval(scriptSource);
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flushPromises();
    await flushPromises();
  });

  beforeEach(() => {
    jest.clearAllMocks();
    delete window.MyvhPortalCalendarFlow;
  });

  test('handles legacy delete link and calls booking flow delete action', () => {
    window.MyvhPortalCalendarFlow = {
      deleteBooking: jest.fn()
    };

    document.getElementById('portal-content').innerHTML = '<a id="legacy-delete" href="#booking-delete?booking_id=77">Delete</a>';
    document.getElementById('legacy-delete').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    expect(window.MyvhPortalCalendarFlow.deleteBooking).toHaveBeenCalledWith(77);
  });

  test('queues legacy create action until booking flow ready event fires', () => {
    document.getElementById('portal-content').innerHTML = '<a id="legacy-create" href="#new-booking">Create</a>';
    document.getElementById('legacy-create').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    const openCreate = jest.fn();
    window.MyvhPortalCalendarFlow = { openCreate: openCreate };

    document.dispatchEvent(new Event('myvh:portal-booking-flow-ready'));

    expect(openCreate).toHaveBeenCalledWith({});
  });

  test('opens and closes nav group using toggle click and Escape key', () => {
    const group = document.querySelector('[data-portal-nav-group]');
    const toggle = group.querySelector('.myvh-portal-nav-toggle');

    toggle.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(group.classList.contains('is-open')).toBe(true);
    expect(toggle.getAttribute('aria-expanded')).toBe('true');

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(group.classList.contains('is-open')).toBe(false);
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
  });

  test('sorts customer table on sortable header and ignores actions header', async () => {
    window.fetch.mockResolvedValue(
      mockHtmlResponse(`
        <table class="myvh-customer-list-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr><td>Zulu</td><td>Edit</td></tr>
            <tr><td>Alpha</td><td>Edit</td></tr>
            <tr><td>Mike</td><td>Edit</td></tr>
          </tbody>
        </table>
      `)
    );

    window.location.hash = '#customers';
    window.dispatchEvent(new Event('hashchange'));
    await flushPromises();
    await flushPromises();
    await flushPromises();

    const table = document.querySelector('.myvh-customer-list-table');
    const nameHeader = table.querySelectorAll('th')[0];
    const actionsHeader = table.querySelectorAll('th')[1];

    expect(actionsHeader.classList.contains('myvh-th-sortable')).toBe(false);

    nameHeader.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(table.querySelector('tbody tr td').textContent).toBe('Alpha');

    nameHeader.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(table.querySelector('tbody tr td').textContent).toBe('Zulu');
  });

  test('enables save button when adding a hall notice in settings', async () => {
    window.fetch.mockResolvedValue(
      mockHtmlResponse(`
        <div class="myvh-dashboard-section myvh-client-settings-page">
          <form class="myvh-account-form myvh-settings-form" data-portal-action="myvh_portal_save_client_settings" data-reload-page="settings">
            <div class="myvh-notices-repeater" data-notices-repeater data-field="hall_notices" data-placeholder-from="From now" data-placeholder-to="Forever">
              <table class="myvh-notices-table">
                <tbody class="myvh-notices-body"></tbody>
              </table>
              <button type="button" class="myvh-notice-add-row">+ Add Notice</button>
            </div>
            <button type="submit" class="button button-primary">Save Settings</button>
          </form>
        </div>
      `)
    );

    window.location.hash = '#settings';
    window.dispatchEvent(new Event('hashchange'));
    await flushPromises();
    await flushPromises();
    await flushPromises();
    await flushPromises();
    await flushPromises();

    const submitButton = document.querySelector('.myvh-settings-form button[type="submit"]');
    const addNoticeButton = document.querySelector('.myvh-notice-add-row');

    expect(submitButton).not.toBeNull();
    expect(addNoticeButton).not.toBeNull();
    expect(submitButton.disabled).toBe(true);

    addNoticeButton.click();

    expect(submitButton.disabled).toBe(false);
  });

  test('updates payment amount label when invoice is selected', async () => {
    window.fetch.mockResolvedValue(
      mockHtmlResponse(`
        <div class="myvh-dashboard-section myvh-client-settings-page myvh-payments-page">
          <form class="myvh-account-form" data-portal-action="myvh_portal_create_payment">
            <div class="myvh-account-field">
              <label for="myvh-portal-payment-invoice"><strong>Invoice</strong></label>
              <select id="myvh-portal-payment-invoice" name="invoice_id" required>
                <option value="">Select an invoice</option>
                <option value="42" data-amount-due="45.00">INV-000042 - Test Customer</option>
              </select>
            </div>
            <div class="myvh-account-field">
              <label for="myvh-portal-payment-amount"><strong id="myvh-portal-payment-amount-label" data-base-label="Amount">Amount</strong></label>
              <input id="myvh-portal-payment-amount" type="number" name="payment_amount" min="0.01" step="0.01" required>
            </div>
          </form>
        </div>
      `)
    );

    window.location.hash = '#payments';
    window.dispatchEvent(new Event('hashchange'));
    await flushPromises();
    await flushPromises();
    await flushPromises();

    const invoiceSelect = document.getElementById('myvh-portal-payment-invoice');
    const amountLabel = document.getElementById('myvh-portal-payment-amount-label');

    expect(invoiceSelect).not.toBeNull();
    expect(amountLabel).not.toBeNull();
    expect(amountLabel.textContent).toBe('Amount');

    invoiceSelect.value = '42';
    invoiceSelect.dispatchEvent(new Event('change', { bubbles: true }));

    expect(amountLabel.textContent).toBe('Amount (£45.00 outstanding)');
  });

  test('applies payments date filter via hash route', async () => {
    window.fetch.mockResolvedValue(
      mockHtmlResponse(`
        <div class="myvh-dashboard-section myvh-client-settings-page myvh-payments-page">
          <form id="myvh-payment-filter-form" class="myvh-invoice-filter-form">
            <input type="hidden" name="invoice_id" value="17">
            <input type="date" name="start_date" value="2026-04-13">
            <input type="date" name="end_date" value="2026-05-13">
            <button type="submit">Apply Filter</button>
          </form>
        </div>
      `)
    );

    window.location.hash = '#payments';
    window.dispatchEvent(new Event('hashchange'));
    await flushPromises();
    await flushPromises();
    await flushPromises();

    const filterForm = document.getElementById('myvh-payment-filter-form');
    const startDateInput = filterForm.querySelector('input[name="start_date"]');
    const endDateInput = filterForm.querySelector('input[name="end_date"]');

    startDateInput.value = '2026-03-01';
    endDateInput.value = '2026-05-01';
    filterForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    expect(window.location.hash).toContain('#payments?');
    expect(window.location.hash).toContain('invoice_id=17');
    expect(window.location.hash).toContain('start_date=2026-03-01');
    expect(window.location.hash).toContain('end_date=2026-05-01');
  });
});
