const { test, expect } = require('@playwright/test');

const loginUrl = process.env.PW_LOGIN_URL || 'login/';
const portalUrl = process.env.PW_PORTAL_URL || './';
const username = process.env.PW_ADMIN_USERNAME;
const password = process.env.PW_ADMIN_PASSWORD;

function normalizePath(value) {
  return value.replace(/\/+$/, '') || '/';
}

test.describe('Portal customer creation', () => {
  test('creates a new customer successfully', async ({ page }) => {
    test.skip(
      !username || !password,
      'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD (client admin credentials) to run this test.'
    );

    await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });

    await expect(page.locator('#myvh-username')).toBeVisible();
    await expect(page.locator('#myvh-password')).toBeVisible();

    await page.locator('#myvh-username').fill(username);
    await page.locator('#myvh-password').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();

    const expectedPortalPath = normalizePath(new URL(portalUrl, page.url()).pathname);
    await expect
      .poll(() => normalizePath(new URL(page.url()).pathname), { timeout: 15000 })
      .toBe(expectedPortalPath);

    await expect(page.locator('#myvh-portal')).toBeVisible({ timeout: 15000 });

    await page.goto(new URL('#customer-add', new URL(portalUrl, page.url())).toString(), {
      waitUntil: 'domcontentloaded',
    });

    await expect(page.locator('#myvh-customer-create-name')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#myvh-customer-create-email')).toBeVisible();

    const suffix = Date.now();
    const customerName = `E2E Customer ${suffix}`;
    const customerEmail = `e2e-customer-${suffix}@example.com`;
    const customerPhone = '07000000000';

    await page.locator('#myvh-customer-create-name').fill(customerName);
    await page.locator('#myvh-customer-create-email').fill(customerEmail);
    await page.locator('#myvh-customer-create-phone').fill(customerPhone);

    await page.getByRole('button', { name: /create customer/i }).click();

    const successMessage = page.locator('#myvh-customer-create-message');
    const errorMessage = page.locator('.myvh-error-message, #myvh-customer-create-error');

    await expect(successMessage.or(errorMessage)).toBeVisible({ timeout: 15000 });

    if (await errorMessage.isVisible()) {
      throw new Error(`Customer creation failed: ${await errorMessage.innerText()}`);
    }

    await expect(successMessage).toBeVisible({ timeout: 15000 });
    await expect(successMessage).toContainText(/customer created/i);

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#customers');

    const customerRow = page
      .locator('.myvh-customer-list-table tbody tr')
      .filter({ hasText: customerEmail })
      .first();

    await expect(customerRow).toBeVisible({ timeout: 15000 });
    await expect(customerRow).toContainText(customerName);
  });
});
