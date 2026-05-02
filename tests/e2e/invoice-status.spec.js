const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');

async function openFirstInvoice(page) {
  await openPortalRoute(page, '#invoices');

  const invoiceLink = page.locator('.myvh-invoices-table tbody tr .myvh-invoice-number a').first();
  const hasInvoice = (await invoiceLink.count()) > 0;
  if (!hasInvoice) {
    return false;
  }

  await invoiceLink.click();
  await expect(page.getByRole('heading', { name: /view invoice/i })).toBeVisible({ timeout: 15000 });
  return true;
}

test.describe('Portal invoice status updates', () => {
  test('updates invoice status from invoice view', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);

    const opened = await openFirstInvoice(page);
    test.skip(!opened, 'No invoices available for invoice status update test.');

    const statusForm = page.locator('#myvh-portal-invoice-status-form');
    const formExists = (await statusForm.count()) > 0;
    test.skip(!formExists, 'Invoice status form unavailable (likely invoice has payments).');

    const statusSelect = page.locator('#myvh-portal-invoice-status');
    await expect(statusSelect).toBeVisible({ timeout: 15000 });

    const currentStatus = await statusSelect.inputValue();
    const candidateStatuses = ['sent', 'overdue', 'draft', 'cancelled'];
    const nextStatus = candidateStatuses.find((status) => status !== currentStatus);

    test.skip(!nextStatus, 'No alternate status available for this invoice.');

    await statusSelect.selectOption(nextStatus);
    await statusForm.getByRole('button', { name: /update status/i }).click();

    const message = statusForm.locator('[data-invoice-status-message]');
    await expect(message).toBeVisible({ timeout: 15000 });
    await expect(message).toContainText(/updated/i);

    const currentStatusLabel = page.locator('[data-current-invoice-status]');
    await expect(currentStatusLabel).toContainText(new RegExp(nextStatus, 'i'));

    const statusBadge = page.locator('[data-invoice-status-badge]');
    await expect(statusBadge).toContainText(new RegExp(nextStatus, 'i'));

    // Restore original status so this test leaves minimal data drift.
    await statusSelect.selectOption(currentStatus);
    await statusForm.getByRole('button', { name: /update status/i }).click();
    await expect(statusForm.locator('[data-invoice-status-message]')).toContainText(/updated/i, { timeout: 15000 });
  });
});
