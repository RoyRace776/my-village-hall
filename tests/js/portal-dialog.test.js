const fs = require('fs');
const path = require('path');

const scriptPath = path.resolve(__dirname, '../../assets/js/portal-dialog.js');
const scriptSource = fs.readFileSync(scriptPath, 'utf8');

describe('MyvhPortalDialog', () => {
  beforeEach(() => {
    jest.restoreAllMocks();
    document.body.innerHTML = '';
    document.head.innerHTML = '';

    delete window.MyvhPortalDialog;
    delete window.myvhPortal;
    delete window.myvhCal;

    window.eval(scriptSource);
  });

  test('queues dialogs and renders one at a time', async () => {
    const firstPromise = window.MyvhPortalDialog.confirm('First question?');
    const secondPromise = window.MyvhPortalDialog.alert('Second message');

    expect(document.querySelectorAll('.myvh-portal-dialog-backdrop')).toHaveLength(1);
    expect(document.querySelector('.myvh-portal-dialog__body').textContent).toBe('First question?');

    document.querySelector('.myvh-portal-dialog__btn').click();

    await expect(firstPromise).resolves.toBe(false);
    expect(document.querySelector('.myvh-portal-dialog__body').textContent).toBe('Second message');

    document.querySelector('.myvh-portal-dialog__btn--primary').click();
    await expect(secondPromise).resolves.toBe(true);
  });

  test('resolves confirm as false on Escape key', async () => {
    const promise = window.MyvhPortalDialog.confirm('Continue?');

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

    await expect(promise).resolves.toBe(false);
    expect(document.querySelector('.myvh-portal-dialog-backdrop')).toBeNull();
  });

  test('resolves alert as true on backdrop click', async () => {
    const promise = window.MyvhPortalDialog.alert('Heads up');

    const backdrop = document.querySelector('.myvh-portal-dialog-backdrop');
    backdrop.dispatchEvent(new MouseEvent('click', { bubbles: true }));

    await expect(promise).resolves.toBe(true);
  });

  test('injects style block once across multiple dialogs', async () => {
    const first = window.MyvhPortalDialog.alert('One');
    document.querySelector('.myvh-portal-dialog__btn--primary').click();
    await first;

    const second = window.MyvhPortalDialog.alert('Two');
    document.querySelector('.myvh-portal-dialog__btn--primary').click();
    await second;

    expect(document.querySelectorAll('#myvh-portal-dialog-styles')).toHaveLength(1);
  });

  test('uses site name as default title in header', async () => {
    window.myvhPortal = { site_name: 'Village Hall Portal' };
    const promise = window.MyvhPortalDialog.alert('Welcome');

    expect(document.querySelector('.myvh-portal-dialog__head').textContent).toBe('Village Hall Portal');

    document.querySelector('.myvh-portal-dialog__btn--primary').click();
    await promise;
  });
});
