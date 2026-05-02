const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');

async function hasInvoiceOptions(invoiceSelect) {
  return expect
    .poll(async () => {
      const options = await invoiceSelect.locator('option').allTextContents();
      return options.filter((value) => String(value || '').trim() !== '').length;
    }, { timeout: 15000 })
    .toBeGreaterThan(1)
    .then(() => true)
    .catch(() => false);
}

async function selectFirstInvoice(invoiceSelect) {
  const optionValue = await invoiceSelect.evaluate((el) => {
    const options = Array.from(el.options || []);
    const match = options.find((option) => String(option.value || '').trim() !== '');
    return match ? match.value : '';
  });

  if (!optionValue) {
    throw new Error('No invoice options available');
  }

  await invoiceSelect.selectOption(optionValue);
}

test.describe('Portal payments', () => {
  test('creates and deletes a payment from the payments page', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    await openPortalRoute(page, '#payments');

    const paymentForm = page.locator('form[data-portal-action="myvh_portal_create_payment"]');
    await expect(paymentForm).toBeVisible({ timeout: 15000 });

    const invoiceSelect = page.locator('#myvh-portal-payment-invoice');
    await expect(invoiceSelect).toBeVisible({ timeout: 15000 });

    const hasInvoices = await hasInvoiceOptions(invoiceSelect);
    test.skip(!hasInvoices, 'No invoices available yet for payment e2e flow.');

    await selectFirstInvoice(invoiceSelect);

    const suffix = Date.now();
    const reference = `E2E-PAY-${suffix}`;

    await page.locator('#myvh-portal-payment-amount').fill('5.00');
    await page.locator('#myvh-portal-payment-method').selectOption('card');
    await page.locator('#myvh-portal-payment-reference').fill(reference);
    await page.locator('#myvh-portal-payment-comment').fill('E2E payment test');

    await page.getByRole('button', { name: /save payment/i }).click();

    const paymentRow = page
      .locator('.myvh-invoices-table tbody tr')
      .filter({ hasText: reference })
      .first();

    await expect(paymentRow).toBeVisible({ timeout: 15000 });

    page.once('dialog', (dialog) => dialog.accept());
    await paymentRow.getByRole('button', { name: /delete/i }).click();

    const deletedRows = page
      .locator('.myvh-invoices-table tbody tr')
      .filter({ hasText: reference });

    await expect(deletedRows).toHaveCount(0, { timeout: 15000 });
  });

  test('requires invoice, amount, and payment method before save', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    await openPortalRoute(page, '#payments');

    const paymentForm = page.locator('form[data-portal-action="myvh_portal_create_payment"]');
    await expect(paymentForm).toBeVisible({ timeout: 15000 });

    const invoiceSelect = page.locator('#myvh-portal-payment-invoice');
    await expect(invoiceSelect).toBeVisible({ timeout: 15000 });

    const hasInvoices = await hasInvoiceOptions(invoiceSelect);
    test.skip(!hasInvoices, 'No invoices available yet for payment validation e2e flow.');

    await page.getByRole('button', { name: /save payment/i }).click();

    await expect(invoiceSelect).toBeFocused();
    await expect(page.locator('#myvh-portal-payment-amount')).toHaveValue('');
    await expect(page.locator('#myvh-portal-payment-method')).toHaveValue('');
  });
});
