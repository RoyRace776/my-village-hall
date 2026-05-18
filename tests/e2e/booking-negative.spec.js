const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');
const { findNextSlotFromAvailabilityService } = require('./helpers/availability');

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
  return optionValue;
}

async function setHiddenDateValue(container, selector, value) {
  await container.locator(selector).first().evaluate((input, nextValue) => {
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
  const selectedRoomId = Number(await selectFirstNonEmptyOption(roomSelect));

  const customerSelect = form.locator('select[name="customer_id"]');
  await waitForSelectOptions(customerSelect);
  await selectFirstNonEmptyOption(customerSelect);

  const organisationSelect = form.locator('select[name="organisation_id"]');
  await expect(organisationSelect).toBeEnabled({ timeout: 15000 });
  await waitForSelectOptions(organisationSelect);
  await selectFirstNonEmptyOption(organisationSelect);

  const slot = await findNextSlotFromAvailabilityService(page, selectedRoomId, 60);

  await setHiddenDateValue(form, '#myvh-modal-start-date', slot.start_date);
  await setHiddenDateValue(form, '#myvh-modal-start-time', slot.start_time);
  await setHiddenDateValue(form, '#myvh-modal-end-time', slot.end_time);
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

  test('keeps booking on list when delete confirmation is cancelled', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);

    const suffix = Date.now();
    const description = `E2E Negative Delete ${suffix}`;
    const bookingRow = await createBooking(page, description);

    await expect(bookingRow).toBeVisible({ timeout: 15000 });
    await bookingRow.getByRole('link', { name: /delete booking/i }).click();
    const cancelConfirmBackdrop = page
      .locator('.myvh-portal-dialog-backdrop')
      .filter({ hasText: /delete this booking\? this action cannot be undone\./i })
      .last();
    await expect(cancelConfirmBackdrop).toBeVisible({ timeout: 15000 });
    // Cancel: click the non-primary button on the active backdrop
    await cancelConfirmBackdrop
      .locator('button.myvh-portal-dialog__btn:not(.myvh-portal-dialog__btn--primary)')
      .click({ force: true });
    await expect(cancelConfirmBackdrop).toBeHidden({ timeout: 15000 });

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#bookings');

    const rowAfterCancel = page
      .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
      .filter({ hasText: description })
      .first();
    await expect(rowAfterCancel).toBeVisible({ timeout: 15000 });

    // Cleanup the created booking to keep the test environment tidy.
    await rowAfterCancel.getByRole('link', { name: /delete booking/i }).click();
    const confirmDeleteBackdrop = page
      .locator('.myvh-portal-dialog-backdrop')
      .filter({ hasText: /delete this booking\? this action cannot be undone\./i })
      .last();
    await expect(confirmDeleteBackdrop).toBeVisible({ timeout: 15000 });

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

    // Confirm: click the primary button
    await confirmDeleteBackdrop.locator('button.myvh-portal-dialog__btn.myvh-portal-dialog__btn--primary').click();
    await expect(confirmDeleteBackdrop).toBeHidden({ timeout: 15000 });
    await deleteRequestCompleted;

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#bookings');

    const deletedRow = page
      .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
      .filter({ hasText: description });

    await expect(deletedRow).toHaveCount(0, { timeout: 15000 });
  });
});
