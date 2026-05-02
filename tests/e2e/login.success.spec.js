const { test, expect } = require('@playwright/test');

const loginUrl = process.env.PW_LOGIN_URL || '/login/';
const portalUrl = process.env.PW_PORTAL_URL || '/portal/';
const username = process.env.PW_LOGIN_USERNAME;
const password = process.env.PW_LOGIN_PASSWORD;

function normalizePath(value) {
  return value.replace(/\/+$/, '') || '/';
}

test.describe('Portal login success', () => {
  test('logs in and reaches the portal', async ({ page }) => {
    test.skip(
      !username || !password,
      'Set PW_LOGIN_USERNAME and PW_LOGIN_PASSWORD to run this test.'
    );

    await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });

    await expect(page.locator('#myvh-username')).toBeVisible();
    await expect(page.locator('#myvh-password')).toBeVisible();

    await page.locator('#myvh-username').fill(username);
    await page.locator('#myvh-password').fill(password);
    await page.getByRole('button', { name: /sign in/i }).click();

    await expect(page.locator('.myvh-error-message')).toHaveCount(0);

    const expectedPortalPath = normalizePath(new URL(portalUrl, page.url()).pathname);
    await expect
      .poll(() => normalizePath(new URL(page.url()).pathname), { timeout: 15000 })
      .toBe(expectedPortalPath);

    await expect(page.locator('#myvh-portal')).toBeVisible({ timeout: 15000 });
  });
});
