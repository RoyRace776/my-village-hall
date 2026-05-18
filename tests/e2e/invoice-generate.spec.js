const { test, expect } = require('@playwright/test');
const {
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
} = require('./helpers/portal-auth');
const {
  cancelInvoiceById,
  listVisibleInvoiceIds,
} = require('./helpers/portal-cleanup');
const { findNextSlotFromAvailabilityService } = require('./helpers/availability');

function firstActiveBookingCheckbox(page) {
  return page
    .locator('.myvh-generate-booking-panel.is-active .myvh-uninvoiced-checkbox:not([disabled])')
    .first();
}

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

async function createAndConfirmBooking(page, description) {
  // Open the bookings page and click New Booking
  await openPortalRoute(page, '#bookings');
  await page.getByRole('link', { name: /new booking/i }).click();
  await expect(page.locator('#myvh-booking-modal-create')).toBeVisible({ timeout: 15000 });

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
  await expect.poll(() => new URL(page.url()).hash, { timeout: 15000 }).toBe('#bookings');

  // Locate the newly created booking row
  const bookingRow = page
    .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
    .filter({ hasText: description })
    .first();
  await expect(bookingRow).toBeVisible({ timeout: 15000 });

  // Edit the booking to set its status to Confirmed
  await bookingRow.getByRole('link', { name: /edit booking/i }).click();
  await expect(page.locator('#myvh-booking-modal-create')).toBeVisible({ timeout: 15000 });

  await page.locator('#myvh-booking-form-create select[name="status"]').selectOption('confirmed');
  await page
    .locator('#myvh-booking-modal-create .myvh-modal-actions button[type="submit"][form="myvh-booking-form-create"]')
    .click();

  await expect(page.locator('#myvh-booking-modal-create')).toBeHidden({ timeout: 15000 });
  await expect.poll(() => new URL(page.url()).hash, { timeout: 15000 }).toBe('#bookings');

  return page
    .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
    .filter({ hasText: description })
    .first();
}

async function deleteBookingRow(page, bookingRow) {
  await bookingRow.getByRole('link', { name: /delete booking/i }).click();

  const confirmBackdrop = page
    .locator('.myvh-portal-dialog-backdrop')
    .filter({ hasText: /delete this booking\? this action cannot be undone\./i })
    .last();
  await expect(confirmBackdrop).toBeVisible({ timeout: 15000 });

  const deleteCompleted = page.waitForResponse(async (response) => {
    if (!response.url().includes('admin-ajax.php')) return false;
    const request = response.request();
    if (request.method() !== 'POST') return false;
    const body = request.postData() || '';
    if (!body.includes('myvh_portal_delete_booking')) return false;
    try {
      const payload = await response.json();
      return !!payload && payload.success === true;
    } catch {
      return false;
    }
  }, { timeout: 15000 });

  await confirmBackdrop
    .locator('button.myvh-portal-dialog__btn.myvh-portal-dialog__btn--primary')
    .click();
  await expect(confirmBackdrop).toBeHidden({ timeout: 15000 });
  await deleteCompleted;
}

test.describe('Portal invoice generation', () => {
  test('shows validation when no bookings are selected', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    await openPortalRoute(page, '#invoice-generate');

    const form = page.locator('form[data-portal-action="myvh_portal_create_invoice"]');
    await expect(form).toBeVisible({ timeout: 15000 });

    const createButton = form
      .getByRole('button', { name: /create invoice/i })
      .or(form.locator('button[type="submit"]', { hasText: /create invoice/i }))
      .or(form.locator('input[type="submit"][value*="Invoice" i]'));

    const buttonCount = await createButton.count();
    test.skip(buttonCount === 0, 'Invoice generation UI not available in this environment.');

    await expect(createButton.first()).toBeEnabled({ timeout: 15000 });
    await createButton.first().click();

    const message = page.locator('#myvh-invoice-create-message');
    await expect(message).toBeVisible({ timeout: 15000 });
    await expect(message).toContainText(/no bookings selected|select at least one booking/i);

    await expect
      .poll(() => new URL(page.url()).hash, { timeout: 15000 })
      .toBe('#invoice-generate');
  });

  test('creates invoices from selected uninvoiced bookings', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);

    const stamp = Date.now();
    const description = `E2E Invoice Generate ${stamp}`;

    const existingInvoiceIds = await listVisibleInvoiceIds(page);
    const existingInvoiceIdSet = new Set(existingInvoiceIds);
    let createdInvoiceIds = [];
    let bookingRow = null;

    try {
      // Create a booking in the next available slot and confirm it so it can be invoiced
      bookingRow = await createAndConfirmBooking(page, description);

      await openPortalRoute(page, '#invoice-generate');

      const form = page.locator('form[data-portal-action="myvh_portal_create_invoice"]');
      await expect(form).toBeVisible({ timeout: 15000 });

      const createButton = form
        .getByRole('button', { name: /create invoice/i })
        .or(form.locator('button[type="submit"]', { hasText: /create invoice/i }))
        .or(form.locator('input[type="submit"][value*="Invoice" i]'));

      const buttonCount = await createButton.count();
      test.skip(buttonCount === 0, 'Invoice generation UI not available in this environment.');

      const checkbox = firstActiveBookingCheckbox(page);
      await expect(checkbox).toBeVisible({ timeout: 15000 });

      await checkbox.check();
      await expect(createButton.first()).toBeEnabled({ timeout: 15000 });
      await createButton.first().click();

      await expect
        .poll(() => new URL(page.url()).hash, { timeout: 15000 })
        .toBe('#invoices');

      await expect(page.getByRole('heading', { name: /invoice/i }).first()).toBeVisible({ timeout: 15000 });

      const visibleInvoiceIds = await listVisibleInvoiceIds(page);
      createdInvoiceIds = visibleInvoiceIds.filter((id) => !existingInvoiceIdSet.has(id));

      await expect(createdInvoiceIds.length).toBeGreaterThan(0);
    } finally {
      if (createdInvoiceIds.length === 0) {
        const visibleInvoiceIds = await listVisibleInvoiceIds(page);
        createdInvoiceIds = visibleInvoiceIds.filter((id) => !existingInvoiceIdSet.has(id));
      }

      for (const invoiceId of createdInvoiceIds) {
        await cancelInvoiceById(page, invoiceId);
      }

      // Delete the booking that was created for this test
      if (bookingRow) {
        await openPortalRoute(page, '#bookings');
        const rowToDelete = page
          .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
          .filter({ hasText: description })
          .first();
        if ((await rowToDelete.count()) > 0) {
          await deleteBookingRow(page, rowToDelete);
        }
      }
    }
  });
});
