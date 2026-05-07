const { test, expect } = require('@playwright/test');

const baseUrl = process.env.PW_BASE_URL;
const resetRequestUrl =
  process.env.PW_RESET_REQUEST_URL ||
  (baseUrl ? new URL('/login/?reset=1', baseUrl).toString() : '/login/?reset=1');
const resetEmail = process.env.PW_RESET_EMAIL || process.env.PW_LOGIN_USERNAME;

test.describe('Password reset request', () => {
  test('submits reset request and shows success message', async ({ page }) => {
    test.skip(
      !resetEmail,
      'Set PW_RESET_EMAIL (or PW_LOGIN_USERNAME) to run this test.'
    );

    await page.goto(resetRequestUrl, { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/reset|login/i, { timeout: 15000 });

    const emailField = page
      .getByTestId('reset-email')
      .or(page.getByLabel(/email/i))
      .or(page.getByRole('textbox', { name: /email/i }));

    await expect(emailField).toBeVisible({ timeout: 15000 });

    await emailField.fill(resetEmail);
    await page.getByRole('button', { name: /send reset link/i }).click();

    const successMessage = page.locator('.myvh-login-success');
    await expect(successMessage).toBeVisible({ timeout: 15000 });
    await expect(successMessage).toContainText(/check your email|reset link/i);

    await expect(page.locator('.myvh-error-message')).toHaveCount(0);
  });
});
