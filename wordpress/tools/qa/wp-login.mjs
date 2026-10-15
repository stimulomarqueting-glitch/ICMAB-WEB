/** Shared Playwright helpers for the local WordPress QA (http://localhost:8881). */
import { chromium } from 'playwright-core';

export const BASE = process.env.WP_BASE || 'http://localhost:8881';

export async function launch() {
  return chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox'],
  });
}

/** Logs in and returns a page. user/pass default to the local admin. */
export async function login(browser, user = 'admin', pass = 'admin-local-pass', viewport = { width: 1440, height: 1000 }) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  await page.goto(`${BASE}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  return page;
}

/** Dismisses the block editor welcome guide if it shows up. */
export async function closeWelcome(page) {
  await page.evaluate(() => {
    try {
      window.wp?.data?.dispatch('core/preferences')?.set('core/edit-post', 'welcomeGuide', false);
    } catch (e) {}
  });
  const btn = page.locator('.edit-post-welcome-guide button[aria-label="Close"], .components-guide button[aria-label="Close"]');
  if (await btn.count()) await btn.first().click().catch(() => {});
}
