import { test, expect, type Page } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

/**
 * REQ-DEMO-013 — docs/DEMO.md screenshots (docs/assets/*.webp after scripts/to-webp.mjs).
 * Captures keep the demo navbar + cards and hide the Symfony toolbar.
 */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/assets');

test.use({ viewport: { width: 1280, height: 800 } });

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

async function hideDebugToolbar(page: Page): Promise<void> {
  await page.addStyleTag({ content: '.sf-toolbar, [id^="sfwdt"], .sf-minitoolbar { display: none !important; }' });
}

test.describe('Doctrine Encrypt demo screenshots', () => {
  test('home', async ({ page }) => {
    await page.goto('/');
    await hideDebugToolbar(page);
    await expect(page.locator('h2').first()).toContainText('Doctrine Encrypt Bundle');
    await page.screenshot({ path: resolve(outDir, 'demo-home.png') });
  });

  test('secret messages CRUD', async ({ page }) => {
    await page.goto('/secret-message/');
    await hideDebugToolbar(page);
    await expect(page.locator('main table tbody tr').first()).toBeVisible();
    await page.screenshot({ path: resolve(outDir, 'demo-crud.png') });
  });
});
