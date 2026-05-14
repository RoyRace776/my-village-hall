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
    throw new Error('No selectable option available.');
  }

  await selectLocator.selectOption(optionValue);
}

function formatIsoDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function formatFlatpickrAltDate(date) {
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
}

async function setBookingDateOrTime(container, selector, value, altValue = null) {
  const sourceInput = container.locator(selector).first();
  const altInput = container.locator(`${selector} + input.flatpickr-alt-input`).first();

  if (await altInput.count()) {
    await altInput.fill(altValue ?? value);
    await altInput.dispatchEvent('input');
    await altInput.dispatchEvent('change');
    await altInput.blur();
  }

  await sourceInput.evaluate((input, nextValue) => {
    input.value = nextValue;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }, value);
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

  // Keep bookings in the future so delete rules are less likely to block cleanup.
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const dateText = formatIsoDate(tomorrow);
  const altDateText = formatFlatpickrAltDate(tomorrow);

  await setBookingDateOrTime(form, '#myvh-modal-start-date', dateText, altDateText);
  await setBookingDateOrTime(form, '#myvh-modal-start-time', '10:00');
  await setBookingDateOrTime(form, '#myvh-modal-end-time', '11:00');

  await expect(form.locator('input[name="start"]')).toHaveValue(`${dateText} 10:00`);
  await expect(form.locator('input[name="end"]')).toHaveValue(`${dateText} 11:00`);

  await form.locator('input[name="text"]').fill(description);

  await form.getByRole('button', { name: /^create booking$/i }).first().click();

  await expect(page.locator('#myvh-booking-modal-create')).toBeHidden({ timeout: 15000 });

  await expect
    .poll(() => new URL(page.url()).hash, { timeout: 15000 })
    .toBe('#bookings');

  const bookingRow = page
    .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
    .filter({ hasText: description })
    .first();

  await expect(bookingRow).toBeVisible({ timeout: 15000 });
  return bookingRow;
}

test.describe('Portal booking lifecycle', () => {
  test('creates, edits, and deletes a booking', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);

    const suffix = Date.now();
    const initialDescription = `E2E Booking ${suffix}`;
    const updatedDescription = `E2E Booking Updated ${suffix}`;

    const createdRow = await createBooking(page, initialDescription);

    await createdRow.getByRole('link', { name: /edit booking/i }).click();
    await expect(page.getByRole('heading', { name: /^edit booking$/i })).toBeVisible({ timeout: 15000 });

    await page.locator('#myvh-booking-description').fill(updatedDescription);
    await page.getByRole('button', { name: /save booking/i }).click();

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#bookings');

    const updatedRow = page
      .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
      .filter({ hasText: updatedDescription })
      .first();

    await expect(updatedRow).toBeVisible({ timeout: 15000 });

    await updatedRow.getByRole('link', { name: /delete booking/i }).click();
    await expect(page.getByRole('heading', { name: /^delete booking$/i })).toBeVisible({ timeout: 15000 });

    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('.myvh-delete-booking-button').click();

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#bookings');

    const deletedRow = page
      .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
      .filter({ hasText: updatedDescription });

    await expect(deletedRow).toHaveCount(0, { timeout: 15000 });
  });
});
