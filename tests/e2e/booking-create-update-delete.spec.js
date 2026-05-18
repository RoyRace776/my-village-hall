const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');
const { findNextSlotFromAvailabilityService } = require('./helpers/availability');

async function waitForChoices(selectLocator) {
  await expect
    .poll(async () => {
      return await selectLocator.evaluate((el) => {
        const options = Array.from(el.options || []);
        return options.filter((option) => String(option.value || '').trim() !== '').length;
      });
    }, { timeout: 15000 })
    .toBeGreaterThan(0);
}

async function chooseFirstValue(selectLocator) {
  const firstValue = await selectLocator.evaluate((el) => {
    const options = Array.from(el.options || []);
    const option = options.find((entry) => String(entry.value || '').trim() !== '');
    return option ? option.value : '';
  });

  if (!firstValue) {
    throw new Error('No selectable option available for booking form.');
  }

  await selectLocator.selectOption(firstValue);
  return firstValue;
}

function toIsoDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function toFlatpickrDate(date) {
  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const year = date.getFullYear();
  return `${day}/${month}/${year}`;
}

async function setHiddenAndAltValue(form, selector, value, altValue = null) {
  const source = form.locator(selector).first();
  const alt = form.locator(`${selector} + input.flatpickr-alt-input`).first();

  if (await alt.count()) {
    await alt.fill(altValue ?? value);
    await alt.dispatchEvent('input');
    await alt.dispatchEvent('change');
    await alt.blur();
  }

  await source.evaluate((input, nextValue) => {
    input.value = nextValue;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }, value);
}

async function openCreateModal(page) {
  await openPortalRoute(page, '#bookings');
  await page.getByRole('link', { name: /new booking/i }).click();
  await expect(page.locator('#myvh-booking-modal-create')).toBeVisible({ timeout: 15000 });
}

async function createBookingViaNextAvailableSlot(page, description) {
  await openCreateModal(page);

  const form = page.locator('#myvh-booking-form-create');

  const room = form.locator('select[name="room_id"]');
  await waitForChoices(room);
  const selectedRoom = Number(await chooseFirstValue(room));

  const customer = form.locator('select[name="customer_id"]');
  await waitForChoices(customer);
  await chooseFirstValue(customer);

  const organisation = form.locator('select[name="organisation_id"]');
  await expect(organisation).toBeEnabled({ timeout: 15000 });
  await waitForChoices(organisation);
  await chooseFirstValue(organisation);

  const slot = await findNextSlotFromAvailabilityService(page, selectedRoom, 60);

  const slotDate = new Date(`${slot.start_date}T00:00:00`);
  const isoDate = toIsoDate(slotDate);
  const flatpickrDate = toFlatpickrDate(slotDate);

  await setHiddenAndAltValue(form, '#myvh-modal-start-date', isoDate, flatpickrDate);
  await setHiddenAndAltValue(form, '#myvh-modal-start-time', slot.start_time);
  await setHiddenAndAltValue(form, '#myvh-modal-end-time', slot.end_time);

  await expect(form.locator('input[name="start"]')).toHaveValue(`${isoDate} ${slot.start_time}`);
  await expect(form.locator('input[name="end"]')).toHaveValue(`${isoDate} ${slot.end_time}`);

  await form.locator('input[name="text"]').fill(description);
  await form.getByRole('button', { name: /^create booking$/i }).click();

  await expect(page.locator('#myvh-booking-modal-create')).toBeHidden({ timeout: 15000 });
  await expect.poll(() => new URL(page.url()).hash, { timeout: 15000 }).toBe('#bookings');

  const row = page
    .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
    .filter({ hasText: description })
    .first();

  await expect(row).toBeVisible({ timeout: 15000 });
  return row;
}

test.describe('Portal booking create-update-delete', () => {
  test('creates, edits, and deletes a booking using next available slot', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);

    const stamp = Date.now();
    const createdDescription = `E2E CreateUpdateDelete ${stamp}`;
    const updatedDescription = `E2E CreateUpdateDelete Updated ${stamp}`;

    const createdRow = await createBookingViaNextAvailableSlot(page, createdDescription);

    await createdRow.getByRole('link', { name: /edit booking/i }).click();
    await expect(page.locator('#myvh-booking-modal-create')).toBeVisible({ timeout: 15000 });

    await page.locator('#myvh-booking-form-create input[name="text"]').fill(updatedDescription);
    await page.locator('#myvh-booking-modal-create .myvh-modal-actions button[type="submit"][form="myvh-booking-form-create"]').click();

    await expect.poll(() => new URL(page.url()).hash, { timeout: 15000 }).toBe('#bookings');

    const updatedRow = page
      .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
      .filter({ hasText: updatedDescription })
      .first();

    await expect(updatedRow).toBeVisible({ timeout: 15000 });

    await updatedRow.getByRole('link', { name: /delete booking/i }).click();
    await expect(page.getByRole('dialog')).toContainText(/delete this booking\? this action cannot be undone\./i, { timeout: 15000 });

    const confirmBackdrop = page
      .locator('.myvh-portal-dialog-backdrop')
      .filter({ hasText: /delete this booking\? this action cannot be undone\./i })
      .last();

    await expect(confirmBackdrop).toBeVisible({ timeout: 15000 });

    const deleteRequestCompleted = page.waitForResponse(async (response) => {
      if (!response.url().includes('admin-ajax.php')) {
        return false;
      }

      const request = response.request();
      if (request.method() !== 'POST') {
        return false;
      }

      const body = request.postData() || '';
      if (!body.includes('myvh_portal_delete_booking')) {
        return false;
      }

      try {
        const payload = await response.json();
        return !!payload && payload.success === true;
      } catch (error) {
        return false;
      }
    }, { timeout: 15000 });

    await confirmBackdrop.locator('button.myvh-portal-dialog__btn.myvh-portal-dialog__btn--primary').click();
    await expect(confirmBackdrop).toBeHidden({ timeout: 15000 });
    await deleteRequestCompleted;

    await expect.poll(() => new URL(page.url()).hash, { timeout: 15000 }).toBe('#bookings');

    const deletedRow = page
      .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
      .filter({ hasText: updatedDescription });

    await expect(deletedRow).toHaveCount(0, { timeout: 15000 });
  });
});
