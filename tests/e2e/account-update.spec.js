const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
} = require('./helpers/portal-auth');
const {
  saveAccountDetails,
  snapshotAccountDetails,
} = require('./helpers/portal-cleanup');

test.describe('Portal account management', () => {
  test('updates account details', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    const originalDetails = await snapshotAccountDetails(page);

    let didMutate = false;

    try {
      const suffix = Date.now();
      const phone = `0700${String(suffix).slice(-7)}`;
      const address = `E2E Address ${suffix}`;

      await saveAccountDetails(page, { phone, address });
      didMutate = true;

      await expect(page.locator('#myvh-account-phone')).toHaveValue(phone);
      await expect(page.locator('#myvh-account-address')).toHaveValue(address);
    } finally {
      if (didMutate) {
        await saveAccountDetails(page, originalDetails);
      }
    }
  });
});
