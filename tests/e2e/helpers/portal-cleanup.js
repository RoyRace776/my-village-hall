const { expect } = require('@playwright/test');
const { openPortalRoute } = require('./portal-auth');

function parseInvoiceIdFromHref(href) {
  const value = String(href || '');
  const match = value.match(/[?#&]invoice_id=(\d+)/i);
  return match ? Number(match[1]) : 0;
}

async function deleteCustomerByEmail(page, email) {
  await openPortalRoute(page, '#customers');

  const rows = page
    .locator('.myvh-customer-list-table tbody tr')
    .filter({ hasText: email });

  if ((await rows.count()) === 0) {
    return false;
  }

  await rows.first().getByRole('button', { name: /delete customer/i }).click();

  const confirmDialog = page
    .locator('.myvh-portal-dialog')
    .filter({ hasText: /delete this customer\? this cannot be undone\./i });

  await expect(confirmDialog).toBeVisible({ timeout: 15000 });
  await confirmDialog.getByRole('button', { name: /^ok$/i }).click();

  await expect(rows).toHaveCount(0, { timeout: 15000 });
  return true;
}

async function snapshotAccountDetails(page) {
  await openPortalRoute(page, '#account');

  await expect(page.locator('#myvh-account-details-form')).toBeVisible({ timeout: 15000 });

  return {
    phone: await page.locator('#myvh-account-phone').inputValue(),
    address: await page.locator('#myvh-account-address').inputValue(),
  };
}

async function saveAccountDetails(page, details) {
  await openPortalRoute(page, '#account');

  await expect(page.locator('#myvh-account-details-form')).toBeVisible({ timeout: 15000 });

  await page.locator('#myvh-account-phone').fill(details.phone || '');
  await page.locator('#myvh-account-address').fill(details.address || '');
  await page.getByRole('button', { name: /save details/i }).click();

  const message = page.locator('#myvh-account-details-message');
  await expect(message).toBeVisible({ timeout: 15000 });
  await expect(message).toContainText(/updated|saved/i);
}

async function listVisibleInvoiceIds(page) {
  await openPortalRoute(page, '#invoices');

  const hrefs = await page
    .locator('.myvh-invoices-table tbody tr .myvh-invoice-number a')
    .evaluateAll((elements) => elements.map((element) => element.getAttribute('href') || ''));

  const ids = hrefs
    .map(parseInvoiceIdFromHref)
    .filter((id) => Number.isInteger(id) && id > 0);

  return [...new Set(ids)];
}

async function cancelInvoiceById(page, invoiceId) {
  await openPortalRoute(page, `#invoice-view?invoice_id=${invoiceId}`);

  const statusForm = page.locator('#myvh-portal-invoice-status-form');
  if ((await statusForm.count()) === 0) {
    return false;
  }

  const statusSelect = page.locator('#myvh-portal-invoice-status');
  await expect(statusSelect).toBeVisible({ timeout: 15000 });

  const options = await statusSelect
    .locator('option')
    .evaluateAll((nodes) => nodes.map((node) => String(node.value || '')));
  if (!options.includes('cancelled')) {
    return false;
  }

  const currentStatus = await statusSelect.inputValue();
  if (currentStatus === 'cancelled') {
    return true;
  }

  await statusSelect.selectOption('cancelled');
  await statusForm.getByRole('button', { name: /update status/i }).click();

  const message = statusForm.locator('[data-invoice-status-message]');
  await expect(message).toContainText(/updated/i, { timeout: 15000 });
  return true;
}

module.exports = {
  cancelInvoiceById,
  deleteCustomerByEmail,
  listVisibleInvoiceIds,
  saveAccountDetails,
  snapshotAccountDetails,
};