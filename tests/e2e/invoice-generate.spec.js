const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');

function firstActiveBookingCheckbox(page) {
  return page
    .locator('.myvh-generate-booking-panel.is-active .myvh-uninvoiced-checkbox:not([disabled])')
    .first();
}

test.describe('Portal invoice generation', () => {
  test('shows validation when no bookings are selected', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    await openPortalRoute(page, '#invoice-generate');

    const form = page.locator('form[data-portal-action="myvh_portal_create_invoice"]');
    await expect(form).toBeVisible({ timeout: 15000 });

    const createButton = form
      .getByRole('button', { name: /create invoice/i })
      .or(form.locator('button[type="submit"]', { hasText: /create invoice/i }))
      .or(form.locator('input[type="submit"][value*="Invoice" i]'));

    const buttonCount = await createButton.count();
    test.skip(buttonCount === 0, 'Invoice generation UI not available in this environment.');

    await expect(createButton.first()).toBeEnabled({ timeout: 15000 });
    await createButton.first().click();

    const message = page.locator('#myvh-invoice-create-message');
    await expect(message).toBeVisible({ timeout: 15000 });
    await expect(message).toContainText(/no bookings selected|select at least one booking/i);

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#invoice-generate');
  });

  test('creates invoices from selected uninvoiced bookings', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    await openPortalRoute(page, '#invoice-generate');

    const form = page.locator('form[data-portal-action="myvh_portal_create_invoice"]');
    await expect(form).toBeVisible({ timeout: 15000 });

    const createButton = form
      .getByRole('button', { name: /create invoice/i })
      .or(form.locator('button[type="submit"]', { hasText: /create invoice/i }))
      .or(form.locator('input[type="submit"][value*="Invoice" i]'));

    const buttonCount = await createButton.count();
    test.skip(buttonCount === 0, 'Invoice generation UI not available in this environment.');

    const checkbox = firstActiveBookingCheckbox(page);
    const hasSelectableBooking = (await checkbox.count()) > 0;

    test.skip(!hasSelectableBooking, 'No uninvoiced bookings available for invoice generation test.');

    await checkbox.check();
    await expect(createButton.first()).toBeEnabled({ timeout: 15000 });
    await createButton.first().click();

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#invoices');

    await expect(page.getByRole('heading', { name: /invoice/i }).first()).toBeVisible({ timeout: 15000 });
  });
});
