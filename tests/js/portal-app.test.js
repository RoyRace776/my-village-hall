const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/portal-app.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0));
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
    window.fetch = jest.fn().mockResolvedValue({
      text: () => Promise.resolve('<div id="myvh-initial">Initial</div>')
    });

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
    window.fetch.mockResolvedValue({
      text: () => Promise.resolve(`
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
    });

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
});
