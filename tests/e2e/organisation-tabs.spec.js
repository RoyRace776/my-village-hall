const { test, expect } = require('@playwright/test');
const { hasAdminCreds, loginAsPortalAdmin, openPortalRoute } = require('./helpers/portal-auth');

test.describe('Portal organisation tabs', () => {
  test('switches tabs and saves contact details', async ({ page }) => {
    test.skip(!hasAdminCreds(), 'Set PW_ADMIN_USERNAME and PW_ADMIN_PASSWORD to run this test.');

    await loginAsPortalAdmin(page);
    await openPortalRoute(page, '#organisations');

    const cards = page.locator('.myvh-org-card');
    await expect(cards.first()).toBeVisible({ timeout: 15000 });

    const orgCard = cards.first();
    const orgId = await orgCard.getAttribute('data-org-id');
    if (!orgId) {
      test.skip(true, 'No manageable organisation card available.');
    }

    const refreshedCard = page.locator(`.myvh-org-card[data-org-id="${orgId}"]`);
    const detailsTab = refreshedCard.getByRole('tab', { name: /organisation details/i });
    const contactTab = refreshedCard.getByRole('tab', { name: /contact details/i });
    const invoicingTab = refreshedCard.getByRole('tab', { name: /invoicing details/i });
    const membersTab = refreshedCard.getByRole('tab', { name: /member details/i });

    await expect(detailsTab).toHaveAttribute('aria-selected', 'true');
    await expect(refreshedCard.locator('[data-org-panel="details"]')).toBeVisible();
    await expect(refreshedCard.locator('[data-org-panel="contact"]')).toBeHidden();
    await expect(refreshedCard.locator('[data-org-panel="invoicing"]')).toBeHidden();
    await expect(refreshedCard.locator('[data-org-panel="members"]')).toBeHidden();

    await contactTab.click();
    await expect(contactTab).toHaveAttribute('aria-selected', 'true');
    await expect(refreshedCard.locator('[data-org-panel="contact"]')).toBeVisible();
    await expect(refreshedCard.getByRole('button', { name: /save contact details/i })).toBeVisible();

    await invoicingTab.click();
    await expect(invoicingTab).toHaveAttribute('aria-selected', 'true');
    await expect(refreshedCard.locator('[data-org-panel="invoicing"]')).toBeVisible();
    await expect(refreshedCard.getByRole('button', { name: /save invoicing details/i })).toBeVisible();

    await membersTab.click();
    await expect(membersTab).toHaveAttribute('aria-selected', 'true');
    await expect(refreshedCard.locator('[data-org-panel="members"]')).toBeVisible();
    await expect(refreshedCard.getByRole('button', { name: /add member/i })).toBeVisible();

    await contactTab.click();
    await expect(refreshedCard.getByRole('button', { name: /save contact details/i })).toBeVisible();
    await refreshedCard.getByRole('button', { name: /save contact details/i }).click();

    await expect(page.locator(`.myvh-org-card[data-org-id="${orgId}"] [data-org-tab="contact"]`)).toHaveAttribute('aria-selected', 'true', {
      timeout: 15000,
    });
    await expect(page.locator(`.myvh-org-card[data-org-id="${orgId}"] [data-org-panel="contact"]`)).toBeVisible();
  });
});
