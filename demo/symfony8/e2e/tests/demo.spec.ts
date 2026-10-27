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
    await expect(page.locator('main table')).not.toContainText(/MUIF[\w-]{40,}/);
  });

  test('a created message is stored encrypted and read back decrypted', async ({ page }) => {
    const title = `e2e ${Date.now()}`;
    const secret = 'Door code for the archive room is behind the reception desk.';

    await page.goto('/secret-message/new');
    await page.getByLabel('Title (stored in plain text)').fill(title);
    await page.getByLabel('Message (encrypted in database)').fill(secret);
    await page.getByRole('button', { name: 'Create' }).click();

    await expect(page.getByRole('alert')).toContainText('Secret message created');
    const listRow = page.locator('main table tbody tr', { hasText: title });
    await expect(listRow).toContainText(secret.slice(0, 50));
    await expect(listRow).not.toContainText(/MUIF[\w-]{40,}/);

    await page.goto('/secret-message/db-values?mode=raw');
    const rawRow = page.locator('main table tbody tr', { hasText: title });
    // Halite ciphertext (base64url, "MUIF" version header), never the plaintext.
    await expect(rawRow).toContainText(/MUIF[\w-]{40,}/);
    await expect(rawRow).not.toContainText(secret);

    // Clean up so the suite can be re-run against the same demo data.
    await page.goto('/secret-message/');
    page.once('dialog', (dialog) => dialog.accept());
    await page.locator('main table tbody tr', { hasText: title }).getByRole('button', { name: 'Delete' }).click();
    await expect(page.getByRole('alert')).toContainText('Secret message deleted');
    await expect(page.locator('main table tbody tr', { hasText: title })).toHaveCount(0);
  });

  test('other demo pages respond', async ({ page }) => {
    for (const path of ['/encrypt-util-demo', '/sensitive-record/', '/mysql-aes-note/', '/secret-message/db-values']) {
      const response = await page.goto(path);
      expect(response?.ok(), path).toBeTruthy();
    }
  });
});
