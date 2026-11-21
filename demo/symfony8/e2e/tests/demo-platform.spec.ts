import { test, expect, type Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * UiKit demo platform shell (REQ-DEMO-014): layout grammar, demo styles, host theme tokens, framework classes, the
 * closed phone drawer, 44 px touch targets and input borders on every demo page, plus axe (wcag2a / 2aa / 21a / 21aa,
 * serious + critical = 0) in light and dark, desktop and phone, and the keyboard drawer pass.
 */
type Theme = 'light' | 'dark';
type PageEntry = string | ((page: Page) => Promise<string>);

async function prepare(_page: Page): Promise<void> {}

async function setTheme(page: Page, theme: Theme): Promise<void> {
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-theme', t);
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
}

async function axe(page: Page): Promise<string[]> {
  const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).exclude('.sf-toolbar').analyze();
  return result.violations
    .filter((v) => v.impact === 'serious' || v.impact === 'critical')
    .map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);
}

/** The demo layout + style rules (docs/USAGE.md "Demo platform shell"; same function as the rollout brief's spec): layout grammar,
 * no demo styles / host tokens / framework classes, closed drawer inert, 44 px targets on coarse pointers, input borders 3:1. */
/* eslint-disable */
// prettier-ignore
function demoPlatformRules(): string[] {
  const issues = [];
  const main = document.querySelector('#nowo-ui-content');
  const shell = document.querySelector('[data-testid="demo-platform"]');
  if (!main || !shell) return ['no-demo-platform-shell'];
  const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  const carded = (el) => el.closest('.nowo-ui-card, [data-nowo-ui-region="table"], [data-nowo-ui-region="form"], .nowo-ui-form-panel, dialog');
  const skip = (el) => el.closest('dialog, [data-nowo-ui-region="filters"], .nowo-ui-filters, .nowo-ui-page-header');

  // Layout grammar.
  main.querySelectorAll('table').forEach((t) => { if (!carded(t)) issues.push('table-outside-card'); });
  main.querySelectorAll('form').forEach((f) => {
    if (f.querySelector('input:not([type="hidden"]), select, textarea') && !carded(f) && !skip(f) && !f.closest('table')) issues.push('form-outside-card');
  });
  const primaries = [...main.querySelectorAll('.nowo-ui-btn--primary')].filter((b) => visible(b) && !b.closest('table, dialog, [data-nowo-ui-region="filters"], .nowo-ui-filters, form'));
  if (primaries.length > 1) issues.push(`primary-buttons:${primaries.length}`);
  const header = main.querySelector('.nowo-ui-page-header');
  const flashes = main.querySelector('.nowo-ui-flashes');
  if (header && flashes && (flashes.compareDocumentPosition(header) & Node.DOCUMENT_POSITION_FOLLOWING)) issues.push('flashes-before-page-header');
  if (document.documentElement.scrollWidth > document.documentElement.clientWidth + 1) issues.push('page-scrolls-horizontally');

  // Styles: no demo styles, no host theme tokens, no CDN / foreign stylesheet, no framework classes inside the shell.
  document.querySelectorAll('style').forEach((s) => {
    if (s.matches('[data-nowo-ui-theme-tokens], [data-nowo-ui-appearance]')) { issues.push('host-theme-tokens'); return; }
    if (!/sf-toolbar|sfwdt|sf-minitoolbar|sf-display/.test(s.textContent || '') && !s.closest('[id^="sfwdt"], .sf-toolbar')) issues.push('style-element');
  });
  document.querySelectorAll('link[rel="stylesheet"]').forEach((l) => {
    const path = new URL(l.href, location.href).pathname;
    if (!path.startsWith('/bundles/') && !path.startsWith('/_wdt/') && !path.startsWith('/_profiler/')) issues.push(`stylesheet:${path}`);
  });
  const framework = /^(acme-.*|nav-tabs|nav-link|nav-item|alert|alert-.*|form-check.*|form-control|form-select|form-label|btn|btn-.*|card|card-.*|badge|row|col|col-.*|table|table-.*)$/;
  shell.querySelectorAll('[class]').forEach((el) => {
    for (const c of el.classList) { if (framework.test(c)) { issues.push(`framework-class:${c}`); break; } }
  });

  // Drawer: below 901 px a closed drawer is inert and the burger says so.
  const aside = shell.querySelector('[data-nowo-ui-aside]');
  if (aside && window.innerWidth < 901) {
    const open = shell.classList.contains('is-aside-open-mobile');
    if (!open && !aside.hasAttribute('inert')) issues.push('drawer-not-inert');
    shell.querySelectorAll('[data-nowo-ui-burger]').forEach((b) => {
      if (visible(b) && b.getAttribute('aria-expanded') !== (open ? 'true' : 'false')) issues.push('burger-state');
    });
  }

  // Touch: 44 px targets on coarse pointers (checkbox / radio 24 px, their label carries the 44 px row).
  if (window.matchMedia('(pointer: coarse)').matches) {
    const controls = [...shell.querySelectorAll('.nowo-ui-btn, button, summary, input:not([type="hidden"]), select, textarea, [role="tab"], .nowo-ui-aside__link, .nowo-ui-demo-platform__locale')]
      .filter((el) => visible(el) && !el.closest('[inert], table, .ts-control'));
    for (const el of controls) {
      const r = el.getBoundingClientRect();
      const small = el.matches('input[type="checkbox"], input[type="radio"]') ? r.height < 24 || r.width < 24 : r.height < 43.5;
      if (small) { issues.push(`target-under-44px:${(el.className && String(el.className).split(' ')[0]) || el.tagName.toLowerCase()}`); }
    }
  }

  // Inputs: boundary 3:1 or more against the surface behind them (WCAG 1.4.11).
  const rgb = (c) => { const m = c.match(/[\d.]+/g); return m ? m.slice(0, 4).map(Number) : null; };
  const lum = (c) => { const v = c.slice(0, 3).map((x) => { const s = x / 255; return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4; }); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; };
  const bgOf = (el) => { for (let n = el.parentElement; n; n = n.parentElement) { const c = rgb(getComputedStyle(n).backgroundColor); if (c && (c.length < 4 || c[3] > 0)) return c; } return [255, 255, 255]; };
  shell.querySelectorAll('input:not([type="hidden"], [type="checkbox"], [type="radio"]), select, textarea').forEach((el) => {
    if (!visible(el) || el.closest('[inert]')) return;
    const border = rgb(getComputedStyle(el).borderTopColor);
    if (!border) return;
    const a = lum(border); const b = lum(bgOf(el));
    if ((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05) < 3) issues.push(`input-border-under-3:1:${el.id || el.name || el.tagName.toLowerCase()}`);
  });

  return [...new Set(issues)];
}


const PAGES: PageEntry[] = [
  '/', '/encrypt-util-demo', '/secret-message/', '/secret-message/new', '/secret-message/db-values',
  '/sensitive-record/', '/sensitive-record/new', '/sensitive-record/raw',
  '/mysql-aes-note/', '/mysql-aes-note/new', '/mysql-aes-note/db-values', '/mysql-aes-note/sql', '/mysql-aes-note/sql/new', '/mysql-aes-note/sql/raw',
  async (page: Page) => { await page.goto('/secret-message/'); return (await page.locator('main table tbody tr a:has-text("Show")').first().getAttribute('href')) as string; },
  async (page: Page) => { await page.goto('/secret-message/'); return (await page.locator('main table tbody tr a:has-text("Edit")').first().getAttribute('href')) as string; },
];

const BUNDLE_OWNED: Array<{ path: RegExp; rule: RegExp }> = [];

/** Findings on a bundle's own page are reported as bundle-owned (not patched in the demo): they are filtered here, by path and rule. */
function demo(url: string, issues: string[]): string[] {
  const path = new URL(url).pathname;
  return issues.filter((issue) => !BUNDLE_OWNED.some((o) => o.path.test(path) && o.rule.test(issue)));
}

async function resolvePath(page: Page, entry: PageEntry): Promise<string> {
  return typeof entry === 'function' ? entry(page) : entry;
}

test.describe('demo platform rules', () => {
  // Device-emulating projects (phone / iphone ...) set their own context: the viewport blocks below do it explicitly.
  test.beforeEach(async ({ page }, testInfo) => {
    test.skip(/phone/i.test(testInfo.project.name), 'viewport blocks set their own context');
    // The Symfony toolbar is dev chrome, never part of the rules or axe runs.
    await page.addInitScript(() => {
      document.addEventListener('DOMContentLoaded', () => {
        const s = document.createElement('style');
        s.textContent = '.sf-toolbar,.sf-minitoolbar,[id^="sfwdt"]{display:none!important}';
        document.head.appendChild(s);
      });
    });
  });

  test('desktop 1440 px', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await prepare(page);
    for (const path of PAGES) {
      await page.goto(await resolvePath(page, path));
      expect(demo(page.url(), await page.evaluate(demoPlatformRules)), page.url()).toEqual([]);
    }
  });

  test.describe('phone 390 px, touch', () => {
    test.use({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });

    test('rules on every page', async ({ page }) => {
      await prepare(page);
      for (const path of PAGES) {
        await page.goto(await resolvePath(page, path));
        expect(demo(page.url(), await page.evaluate(demoPlatformRules)), page.url()).toEqual([]);
      }
    });

    test('keyboard drawer pass', async ({ page }) => {
      await prepare(page);
      await page.goto(await resolvePath(page, PAGES[0]));
      const aside = page.locator('[data-nowo-ui-aside]');
      await expect(aside).toHaveAttribute('inert', '');
      const burger = page.locator('[data-nowo-ui-burger]').first();
      await expect(burger).toHaveAttribute('aria-expanded', 'false');
      await burger.focus();
      await page.keyboard.press('Enter');
      await expect(page.getByTestId('demo-platform')).toHaveClass(/is-aside-open-mobile/);
      await expect(page.locator('[data-nowo-ui-aside] :focus')).toHaveCount(1);
      await expect(page.locator('.nowo-ui-main')).toHaveAttribute('inert', '');
      await expect(page.locator('html')).toHaveClass(/nowo-ui-drawer-open/);
      await page.keyboard.press('Escape');
      await expect(burger).toBeFocused();
      await expect(aside).toHaveAttribute('inert', '');
    });
  });

  for (const theme of ['light', 'dark'] as Theme[]) {
    for (const [name, width] of [['desktop', 1440], ['phone', 390]] as const) {
      test(`axe clean: ${name}, ${theme}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await prepare(page);
        for (const path of PAGES) {
          await page.goto(await resolvePath(page, path));
          await setTheme(page, theme);
          expect(demo(page.url(), await axe(page)), page.url()).toEqual([]);
        }
      });
    }
  }
});
