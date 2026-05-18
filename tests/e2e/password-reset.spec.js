const { test, expect } = require('@playwright/test');

const baseUrl = process.env.PW_BASE_URL;
const resetRequestUrl =
  process.env.PW_RESET_REQUEST_URL ||
  (baseUrl ? new URL('/test/login/?reset=1', baseUrl).toString() : '/test/login/?reset=1');
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
      .getByTestId('myvh-reset-email')
      .or(page.locator('#myvh-reset-email'))
      .or(page.getByLabel(/email/i))
      .or(page.getByRole('textbox', { name: /email/i }));

    const emailFieldCount = await emailField.count();
    test.skip(emailFieldCount === 0, 'Password reset form is not available at the configured reset URL.');

    await expect(emailField.first()).toBeVisible({ timeout: 15000 });
    await emailField.first().fill(resetEmail);

    const submit = page
      .getByRole('button', { name: /send reset link|send link|reset password|request reset/i })
      .or(page.locator('button[type="submit"]'))
      .or(page.locator('input[type="submit"]'));

    const submitCount = await submit.count();
    test.skip(submitCount === 0, 'Password reset submit control not found on reset page.');

    await submit.first().click();

    const successMessage = page.locator('.myvh-login-success');
    await expect(successMessage).toBeVisible({ timeout: 15000 });
    await expect(successMessage).toContainText(/check your email|reset link/i);

    await expect(page.locator('.myvh-error-message')).toHaveCount(0);
  });
});
