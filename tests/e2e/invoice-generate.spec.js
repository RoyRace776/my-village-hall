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

    const createButton = form.getByRole('button', { name: /create invoice/i });
    await expect(createButton).toBeVisible({ timeout: 15000 });
    await expect(createButton).toBeEnabled({ timeout: 15000 });
    await createButton.click();

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

    const checkbox = firstActiveBookingCheckbox(page);
    const hasSelectableBooking = (await checkbox.count()) > 0;

    test.skip(!hasSelectableBooking, 'No uninvoiced bookings available for invoice generation test.');

    await checkbox.check();
    const createButton = form.getByRole('button', { name: /create invoice/i });
    await expect(createButton).toBeVisible({ timeout: 15000 });
    await expect(createButton).toBeEnabled({ timeout: 15000 });
    await createButton.click();

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#invoices');

    await expect(page.getByRole('heading', { name: /invoice/i }).first()).toBeVisible({ timeout: 15000 });
  });
});
