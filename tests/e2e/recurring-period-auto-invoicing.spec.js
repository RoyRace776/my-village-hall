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

async function configureRecurringPeriodRule(page) {
  await openPortalRoute(page, '#recurring-booking-invoice-rules');

  const form = page.locator('form[data-portal-action="myvh_portal_save_recurring_booking_invoice_rules"]');
  await expect(form).toBeVisible({ timeout: 15000 });

  const firstRow = form.locator('#myvh-recurring-booking-rules-table tbody tr.myvh-rule-row').first();
  await expect(firstRow).toBeVisible({ timeout: 15000 });

  await firstRow.locator('input[name$="[name]"]').fill('E2E Period Rule');
  await firstRow.locator('select[name$="[trigger_timing]"]').selectOption('start_of_month');
  await firstRow.locator('select[name$="[trigger_direction]"]').selectOption('in_advance');
  await firstRow.locator('input[name$="[trigger_period_count]"]').fill('0');
  await firstRow.locator('select[name$="[group_by]"]').selectOption('by_customer');
  await firstRow.locator('input[name$="[due_date_offset_days]"]').fill('7');

  const activeCheckbox = firstRow.locator('input[name$="[is_active]"]');
  if (!(await activeCheckbox.isChecked())) {
    await activeCheckbox.check();
  }

  const ruleIdRaw = await firstRow.locator('input[name$="[id]"]').inputValue();
  const ruleId = Number(ruleIdRaw || '0');

  if (ruleId > 0) {
    await form.locator('#myvh-default-recurring-booking-rule').selectOption(String(ruleId));
  }

  await form.getByRole('button', { name: /save rules/i }).click();

  const message = page.locator('#myvh-recurring-booking-rules-message');
  await expect(message).toBeVisible({ timeout: 15000 });
  await expect(message).toContainText(/updated/i);
}

async function runAutoInvoicingAndReadCount(page) {
  await openPortalRoute(page, '#invoice-generate');

  const form = page.locator('form[data-portal-action="myvh_portal_run_auto_invoicing"]');
  await expect(form).toBeVisible({ timeout: 15000 });

  const runCompleted = page.waitForResponse(async (response) => {
    if (!response.url().includes('admin-ajax.php')) return false;
    const request = response.request();
    if (request.method() !== 'POST') return false;
    const body = request.postData() || '';
    if (!body.includes('myvh_portal_run_auto_invoicing')) return false;

    try {
      const payload = await response.json();
      return Boolean(payload && payload.success === true);
    } catch {
      return false;
    }
  }, { timeout: 20000 });

  await form.getByRole('button', { name: /run auto-invoicing/i }).click();

  const response = await runCompleted;
  const payload = await response.json();
  const count = Number(payload?.data?.invoice_count ?? -1);

  const message = page.locator('#myvh-auto-invoicing-portal-message');
  await expect(message).toBeVisible({ timeout: 15000 });
  await expect(message).toContainText(/auto-invoicing generated/i);

  return count;
}

async function createConfirmedRecurringSeries(page, description) {
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
  await form.locator('select[name="status"]').selectOption('confirmed');

  const recurringCheckbox = form.locator('#myvh-modal-is-recurring');
  await recurringCheckbox.check();

  await form.locator('select[name="recurrence_type"]').selectOption('daily');
  await form.locator('input[name="recurrence_end_type"][value="count"]').check();
  await form.locator('input[name="max_occurrences"]').fill('2');

  await form.getByRole('button', { name: /^create booking$/i }).first().click();
  await expect(page.locator('#myvh-booking-modal-create')).toBeHidden({ timeout: 15000 });

  const rows = page
    .locator('#myvh-bookings-table tbody tr.myvh-bookings-table-row')
    .filter({ hasText: description });

  await expect.poll(() => rows.count(), { timeout: 15000 }).toBeGreaterThanOrEqual(2);
  return rows.count();
}

test.describe('Recurring period auto-invoicing', () => {
  test('invoices a recurring series once and then becomes idempotent', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    await configureRecurringPeriodRule(page);

    // Drain any existing backlog so the assertion is scoped to the series created in this test.
    await runAutoInvoicingAndReadCount(page);

    const description = `E2E Period Recurring ${Date.now()}`;
    await createConfirmedRecurringSeries(page, description);

    const firstRunCount = await runAutoInvoicingAndReadCount(page);
    expect(firstRunCount).toBe(1);

    const secondRunCount = await runAutoInvoicingAndReadCount(page);
    expect(secondRunCount).toBe(0);
  });
});
