const { expect } = require('@playwright/test');

const loginUrl = process.env.PW_LOGIN_URL || '/login/';
const portalUrl = process.env.PW_PORTAL_URL || '/portal/';
const adminUsername = process.env.PW_ADMIN_USERNAME || process.env.PW_LOGIN_USERNAME;
const adminPassword = process.env.PW_ADMIN_PASSWORD || process.env.PW_LOGIN_PASSWORD;

function normalizePath(value) {
  return value.replace(/\/+$/, '') || '/';
}

function hasAdminCreds() {
  return Boolean(adminUsername && adminPassword);
}

async function loginAsPortalAdmin(page) {
  await page.goto(loginUrl, { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#myvh-username')).toBeVisible();
  await expect(page.locator('#myvh-password')).toBeVisible();

  await page.locator('#myvh-username').fill(adminUsername);
  await page.locator('#myvh-password').fill(adminPassword);
  await page.getByRole('button', { name: /sign in/i }).click();

  const expectedPortalPath = normalizePath(new URL(portalUrl, page.url()).pathname);
  await expect
    .poll(() => normalizePath(new URL(page.url()).pathname), { timeout: 15000 })
    .toBe(expectedPortalPath);

  await expect(page.locator('#myvh-portal')).toBeVisible({ timeout: 15000 });
}

async function openPortalRoute(page, hashRoute) {
  const targetUrl = new URL(hashRoute, new URL(portalUrl, page.url())).toString();
  await page.goto(targetUrl, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#portal-content')).toBeVisible({ timeout: 15000 });
}

module.exports = {
  adminUsername,
  adminPassword,
  hasAdminCreds,
  loginAsPortalAdmin,
  openPortalRoute,
};
