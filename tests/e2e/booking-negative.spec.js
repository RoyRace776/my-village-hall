const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');

async function waitForSelectOptions(selectLocator) {
  await expect
    .poll(async () => {
      return await selectLocator.evaluate((el) => {
        const options = Array.from(el.options || []);
        return options.filter((option) => String(option.value || '').trim() !== '').length;
      });
    }, { timeout: 15000 })
    .toBeGreaterThan(0);
}

async function selectFirstNonEmptyOption(selectLocator) {
  const optionValue = await selectLocator.evaluate((el) => {
    const options = Array.from(el.options || []);
    const match = options.find((option) => String(option.value || '').trim() !== '');
    return match ? match.value : '';
  });

  if (!optionValue) {
    test.skip(true, 'No selectable option available in this environment.');
  }

  await selectLocator.selectOption(optionValue);
}

async function openCreateBookingModal(page) {
  await openPortalRoute(page, '#bookings');
  await page.getByRole('link', { name: /new booking/i }).click();
  await expect(page.locator('#myvh-booking-modal-create')).toBeVisible({ timeout: 15000 });
}

async function createBooking(page, description) {
  await openCreateBookingModal(page);

  const form = page.locator('#myvh-booking-form-create');

  const roomSelect = form.locator('select[name="room_id"]');
  await waitForSelectOptions(roomSelect);
  await selectFirstNonEmptyOption(roomSelect);

  const customerSelect = form.locator('select[name="customer_id"]');
  await waitForSelectOptions(customerSelect);
  await selectFirstNonEmptyOption(customerSelect);

  const organisationSelect = form.locator('select[name="organisation_id"]');
  await expect(organisationSelect).toBeEnabled({ timeout: 15000 });
  await waitForSelectOptions(organisationSelect);
  await selectFirstNonEmptyOption(organisationSelect);

  const tomorrow = new Date(Date.now() + 24 * 60 * 60 * 1000);
  const dateText = tomorrow.toISOString().slice(0, 10);

  await form.locator('#myvh-modal-start-date').fill(dateText);
  await form.locator('#myvh-modal-start-time').fill('12:00');
  await form.locator('#myvh-modal-end-time').fill('13:00');
  await form.locator('input[name="text"]').fill(description);

  await form.getByRole('button', { name: /^create booking$/i }).first().click();

  await expect(page.locator('#myvh-booking-modal-create')).toBeHidden({ timeout: 15000 });
  await expect
    .poll(() => new URL(page.url()).hash, { timeout: 15000 })
    .toBe('#bookings');

  return page
    .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
    .filter({ hasText: description })
    .first();
}

test.describe('Portal booking negative paths', () => {
  test('blocks create when customer and organisation are missing', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    await openCreateBookingModal(page);

    const form = page.locator('#myvh-booking-form-create');

    const roomSelect = form.locator('select[name="room_id"]');
    await waitForSelectOptions(roomSelect);
    await selectFirstNonEmptyOption(roomSelect);

    await page.evaluate(() => {
      if (window.myvhCal) {
        window.myvhCal.currentCustomerId = '';
        window.myvhCal.defaultOrganisationId = '';
      }

      const form = document.getElementById('myvh-booking-form-create');
      if (!form) {
        return;
      }

      const customer = form.querySelector('[name="customer_id"]');
      const organisation = form.querySelector('[name="organisation_id"]');

      if (customer) {
        customer.required = false;
        customer.value = '';
      }

      if (organisation) {
        organisation.required = false;
        organisation.value = '';
      }

      form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    });

    await expect(page.locator('#myvh-booking-modal-create')).toBeVisible();
    await expect(form.getByRole('button', { name: /^create booking$/i }).first()).toBeEnabled();
  });

  test('stays on delete page when delete confirmation is cancelled', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);

    const suffix = Date.now();
    const description = `E2E Negative Delete ${suffix}`;
    const bookingRow = await createBooking(page, description);

    await expect(bookingRow).toBeVisible({ timeout: 15000 });
    await bookingRow.getByRole('link', { name: /delete booking/i }).click();

    await expect(page.getByRole('heading', { name: /^delete booking$/i })).toBeVisible({ timeout: 15000 });

    page.once('dialog', (dialog) => dialog.dismiss());
    await page.locator('.myvh-delete-booking-button').click();

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toContain('booking-delete?booking_id=');

    await expect(page.getByRole('heading', { name: /^delete booking$/i })).toBeVisible();

    // Cleanup the created booking to keep the test environment tidy.
    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('.myvh-delete-booking-button').click();

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#bookings');

    const deletedRow = page
      .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
      .filter({ hasText: description });

    await expect(deletedRow).toHaveCount(0, { timeout: 15000 });
  });
});
