const { test, expect } = require('@playwright/test');

const loginUrl = process.env.PW_LOGIN_URL || '/login/';
const invalidUsername = process.env.PW_INVALID_LOGIN_USERNAME || 'invalid-user@example.com';
const invalidPassword = process.env.PW_INVALID_LOGIN_PASSWORD || 'definitely-wrong-password';

test.describe('Portal login failure', () => {
  test('shows an error for invalid credentials', async ({ page }) => {
    await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });

    await expect(page.locator('#myvh-username')).toBeVisible();
    await expect(page.locator('#myvh-password')).toBeVisible();

    await page.locator('#myvh-username').fill(invalidUsername);
    await page.locator('#myvh-password').fill(invalidPassword);
    await page.getByRole('button', { name: /sign in/i }).click();

    const errorMessage = page.locator('.myvh-error-message');
    await expect(errorMessage).toBeVisible();
    await expect(errorMessage).not.toBeEmpty();
    await expect(page.locator('#myvh-username')).toBeVisible();
    await expect(page.locator('#myvh-password')).toBeVisible();
    await expect(page.locator('#myvh-portal')).toHaveCount(0);
  });
});
