const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');

async function createCustomer(page, { name, email, phone }) {
  await openPortalRoute(page, '#customer-add');

  await expect(page.locator('#myvh-customer-create-name')).toBeVisible({ timeout: 15000 });

  await page.locator('#myvh-customer-create-name').fill(name);
  await page.locator('#myvh-customer-create-email').fill(email);
  await page.locator('#myvh-customer-create-phone').fill(phone);

  await page.getByRole('button', { name: /create customer/i }).click();

  await expect
    .poll(() => new URL(page.url()).hash, { timeout: 15000 })
    .toBe('#customers');
}

async function getCustomerRow(page, email) {
  return page
    .locator('.myvh-customer-list-table tbody tr')
    .filter({ hasText: email })
    .first();
}

test.describe('Portal customer management', () => {
  test('edits a customer successfully', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);

    const suffix = Date.now();
    const customer = {
      name: `E2E Editable Customer ${suffix}`,
      email: `e2e-editable-${suffix}@example.com`,
      phone: '07000000001',
    };

    await createCustomer(page, customer);

    const row = await getCustomerRow(page, customer.email);
    await expect(row).toBeVisible({ timeout: 15000 });

    await row.getByRole('link', { name: /edit customer/i }).click();

    await expect(page.getByRole('heading', { name: /edit customer/i })).toBeVisible({ timeout: 15000 });

    const updatedName = `${customer.name} Updated`;
    await page.locator('input[name="name"]').fill(updatedName);
    await page.getByRole('button', { name: /update customer/i }).click();

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#customers');

    const updatedRow = await getCustomerRow(page, customer.email);
    await expect(updatedRow).toBeVisible({ timeout: 15000 });
    await expect(updatedRow).toContainText(updatedName);
  });

  test('deletes a customer successfully', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);

    const suffix = Date.now();
    const customer = {
      name: `E2E Deletable Customer ${suffix}`,
      email: `e2e-deletable-${suffix}@example.com`,
      phone: '07000000002',
    };

    await createCustomer(page, customer);

    const row = await getCustomerRow(page, customer.email);
    await expect(row).toBeVisible({ timeout: 15000 });

    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('button', { name: /delete customer/i }).click();

    await expect
      .poll(async () => {
        const candidateRow = await getCustomerRow(page, customer.email);
        return candidateRow.count();
      }, { timeout: 15000 })
      .toBe(0);
  });
});
