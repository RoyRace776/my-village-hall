const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');
const { deleteCustomerByEmail } = require('./helpers/portal-cleanup');

test.describe('Portal customer creation', () => {
  test('creates a new customer successfully', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    let loggedIn = false;
    let customerEmail = '';

    try {
      await loginAsPortalAdmin(page);
      loggedIn = true;

      await openPortalRoute(page, '#customer-add');

      await expect(page.locator('#myvh-customer-create-name')).toBeVisible({ timeout: 15000 });
      await expect(page.locator('#myvh-customer-create-email')).toBeVisible();

      const suffix = Date.now();
      const customerName = `E2E Customer ${suffix}`;
      customerEmail = `e2e-customer-${suffix}@example.com`;
      const customerPhone = '07000000000';

      await page.locator('#myvh-customer-create-name').fill(customerName);
      await page.locator('#myvh-customer-create-email').fill(customerEmail);
      await page.locator('#myvh-customer-create-phone').fill(customerPhone);

      await page.getByRole('button', { name: /create customer/i }).click();

      const successMessage = page.locator('#myvh-customer-create-message');
      const errorMessage = page.locator('.myvh-error-message, #myvh-customer-create-error');

      await expect
        .poll(() => new URL(page.url()).hash, { timeout: 15000 })
        .toBe('#customers');

      if (await successMessage.count()) {
        await expect(successMessage).toContainText(/customer created/i, { timeout: 15000 });
      }

      await expect(errorMessage).toHaveCount(0);

      const customerRow = page
        .locator('.myvh-customer-list-table tbody tr')
        .filter({ hasText: customerEmail })
        .first();

      await expect(customerRow).toBeVisible({ timeout: 15000 });
      await expect(customerRow).toContainText(customerName);

    } finally {
      if (loggedIn && customerEmail) {
        await deleteCustomerByEmail(page, customerEmail);
      }
    }
  });
});
