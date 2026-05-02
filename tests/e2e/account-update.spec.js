const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');

test.describe('Portal account management', () => {
  test('updates account details', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    await openPortalRoute(page, '#account');

    await expect(page.locator('#myvh-account-details-form')).toBeVisible({ timeout: 15000 });

    const suffix = Date.now();
    const phone = `0700${String(suffix).slice(-7)}`;
    const address = `E2E Address ${suffix}`;

    await page.locator('#myvh-account-phone').fill(phone);
    await page.locator('#myvh-account-address').fill(address);

    await page.getByRole('button', { name: /save details/i }).click();

    const message = page.locator('#myvh-account-details-message');
    await expect(message).toBeVisible({ timeout: 15000 });
    await expect(message).toContainText(/updated|saved/i);
  });
});
