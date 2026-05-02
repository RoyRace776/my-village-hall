const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/portal-ajax.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

function formDataToObject(formData) {
  const output = {};
  for (const [key, value] of formData.entries()) {
    output[key] = value;
  }
  return output;
}

describe('MyvhPortalAjax', () => {
  beforeEach(() => {
    jest.restoreAllMocks();
    delete window.MyvhPortalAjax;
    delete window.myvhPortal;
    delete window.myvhCal;

    window.fetch = jest.fn().mockResolvedValue({
      json: () => Promise.resolve({ success: true })
    });

    window.eval(scriptSource);
  });

  test('throws when no portal config is available', () => {
    expect(() => window.MyvhPortalAjax.get({ action: 'x' })).toThrow('Portal AJAX config missing');
  });

  test('adds nonce to GET when missing and uses portal config by default', async () => {
    window.myvhPortal = {
      ajax_url: '/portal-ajax-endpoint',
      nonce: 'portal-nonce'
    };

    await window.MyvhPortalAjax.get({ action: 'myvh_portal_list' });

    expect(window.fetch).toHaveBeenCalledTimes(1);
    const requestUrl = window.fetch.mock.calls[0][0];
    expect(requestUrl).toContain('/portal-ajax-endpoint?');
    expect(requestUrl).toContain('action=myvh_portal_list');
    expect(requestUrl).toContain('nonce=portal-nonce');
  });

  test('preserves explicit nonce in GET query', async () => {
    window.myvhPortal = {
      ajax_url: '/portal-ajax-endpoint',
      nonce: 'portal-nonce'
    };

    await window.MyvhPortalAjax.get({ action: 'myvh_portal_list', nonce: 'manual-nonce' });

    const requestUrl = window.fetch.mock.calls[0][0];
    expect(requestUrl).toContain('nonce=manual-nonce');
    expect(requestUrl).not.toContain('nonce=portal-nonce');
  });

  test('builds POST FormData payload and appends request_id and nonce', async () => {
    window.myvhPortal = {
      ajax_url: '/portal-ajax-endpoint',
      nonce: 'portal-nonce'
    };

    jest.spyOn(Date, 'now').mockReturnValue(1714670000000);
    jest.spyOn(Math, 'random').mockReturnValue(0.5);

    await window.MyvhPortalAjax.post('myvh_portal_update', {
      booking_id: '123',
      status: 'confirmed'
    });

    const request = window.fetch.mock.calls[0][1];
    expect(request.method).toBe('POST');

    const bodyValues = formDataToObject(request.body);
    expect(bodyValues.action).toBe('myvh_portal_update');
    expect(bodyValues.booking_id).toBe('123');
    expect(bodyValues.status).toBe('confirmed');
    expect(bodyValues.nonce).toBe('portal-nonce');
    expect(bodyValues.request_id).toMatch(/^myvh_1714670000000_/);
  });

  test('uses calendar scope config when scope is calendar', async () => {
    window.myvhPortal = {
      ajax_url: '/portal-ajax-endpoint',
      nonce: 'portal-nonce'
    };
    window.myvhCal = {
      ajax_url: '/calendar-ajax-endpoint',
      nonce: 'calendar-nonce',
      portalNonce: 'calendar-portal-nonce'
    };

    await window.MyvhPortalAjax.post('myvh_calendar_action', { x: '1' }, { scope: 'calendar' });

    const requestUrl = window.fetch.mock.calls[0][0];
    const bodyValues = formDataToObject(window.fetch.mock.calls[0][1].body);

    expect(requestUrl).toBe('/calendar-ajax-endpoint');
    expect(bodyValues.nonce).toBe('calendar-nonce');
  });
});
