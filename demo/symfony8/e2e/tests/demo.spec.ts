import { test, expect } from '@playwright/test';

/**
 * REQ-DEMO-013 — happy-path e2e against the Doctrine Encrypt demo.
 */
test.describe('Doctrine Encrypt demo happy path', () => {
  test('home lists the demo sections', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('h2').first()).toContainText('Doctrine Encrypt Bundle');
  });

  test('secret messages are listed decrypted', async ({ page }) => {
    const response = await page.goto('/secret-message/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.locator('main table tbody tr').first()).toBeVisible();
    await expect(page.locator('main table')).not.toContainText('<ENC>');
  });

  test('other demo pages respond', async ({ page }) => {
    for (const path of ['/encrypt-util-demo', '/sensitive-record/', '/mysql-aes-note/', '/secret-message/db-values']) {
      const response = await page.goto(path);
      expect(response?.ok(), path).toBeTruthy();
    }
  });
});
