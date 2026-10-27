import { test, expect, type Locator, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — README screenshots in full demo context: demo navbar + bundle feature + Symfony web debug
 * toolbar, on the seeded fixtures (`make demo-screenshots` reloads them first).
 * Captured as PNG; `make demo-screenshots` converts them to WebP (docs/images/demo/) and deletes the PNGs.
 */
test.describe.configure({ mode: 'serial' });

const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

const WIDTH = 1440;
const HEIGHT = 900;

/**
 * Captures the viewport (navbar → bundle feature → fixed web debug toolbar) once the toolbar is rendered.
 * When `until` is given, the viewport grows so that element is fully visible above the toolbar.
 */
async function captureDemoFrame(page: Page, file: string, until?: Locator): Promise<void> {
  const toolbar = page.locator('.sf-toolbar .sf-toolbarreset');
  await expect(toolbar).toBeVisible();
  if (until) {
    const bottom = await until.evaluate((el) => el.getBoundingClientRect().bottom + window.scrollY);
    const toolbarHeight = (await toolbar.boundingBox())?.height ?? 40;
    await page.setViewportSize({ width: WIDTH, height: Math.max(HEIGHT, Math.ceil(bottom + 32 + toolbarHeight)) });
    await expect(toolbar).toBeVisible();
  }
  await page.evaluate(() => new Promise((done) => requestAnimationFrame(() => setTimeout(done, 500))));
  await page.screenshot({ path: resolve(outDir, file) });
}

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('Doctrine Encrypt screenshots (full demo frame)', () => {
  test.use({ viewport: { width: WIDTH, height: HEIGHT }, colorScheme: 'light' });

  test('secret messages — ciphertext stored in MySQL next to the decrypted value', async ({ page }) => {
    await page.goto('/secret-message/db-values?mode=both');
    const table = page.locator('main table');
    // Halite ciphertext (base64url, "MUIF" version header) is shown next to the decrypted message.
    await expect(table).toContainText(/MUIF[\w-]{40,}/);
    await expect(table).toContainText('Office Wi-Fi');
    await captureDemoFrame(page, 'encrypted-at-rest.png', table);
  });

  test('MySQL AES — filter an AES_ENCRYPT column with AES_DECRYPT in SQL', async ({ page }) => {
    await page.goto('/mysql-aes-note/sql?secret=find-me');
    const table = page.locator('main table');
    await expect(table).toContainText('find-me-sql-filter');
    const strategy = page.locator('main details');
    await strategy.locator('summary').click();
    await expect(strategy.locator('pre')).toContainText('AES_DECRYPT(secret_native, :key)');
    await captureDemoFrame(page, 'mysql-aes-search.png', table);
  });
});
