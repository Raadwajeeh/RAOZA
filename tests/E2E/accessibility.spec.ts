import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function dismissConsent(page: Page) {
  const button = page.getByRole('button', { name: 'Necessary only' });
  if (await button.isVisible()) await button.click();
  await expect(page.getByRole('progressbar', { name: 'Loading page' })).toBeHidden();
}

test.describe('Phase 10 storefront accessibility', () => {
  for (const viewport of [
    { name: 'mobile', width: 375, height: 812 },
    { name: 'desktop', width: 1440, height: 1000 },
  ]) {
    test(`${viewport.name} key pages have no automatic WCAG A/AA violations`, async ({ page }) => {
      await page.setViewportSize(viewport);
      for (const path of ['/', '/shop', '/products/raoza-signature-tee', '/cart', '/account/login', '/pages/returns']) {
        await page.goto(path);
        await dismissConsent(page);
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        const violations = result.violations.flatMap(violation =>
          violation.nodes.map(node => `${violation.id}: ${node.target.join(' ')} — ${node.failureSummary ?? ''}`),
        );
        expect(violations, `${path}: ${violations.join(' | ')}`).toEqual([]);
      }
    });
  }

  test('mobile menu moves, contains and restores keyboard focus', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/shop');
    await dismissConsent(page);
    const toggle = page.getByRole('button', { name: 'Open menu' });
    await toggle.focus();
    await toggle.press('Enter');
    await expect(page.getByRole('link', { name: 'Shop all' })).toBeFocused();
    await page.keyboard.press('Shift+Tab');
    await expect(page.getByRole('button', { name: 'Close menu' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: 'Open menu' })).toBeFocused();
  });

  test('checkout communicates progress before payment', async ({ page }) => {
    await page.goto('/products/raoza-signature-tee');
    await dismissConsent(page);
    await page.getByRole('button', { name: 'M', exact: true }).click();
    await page.getByRole('button', { name: 'Cream', exact: true }).click();
    await page.getByRole('button', { name: 'Add to bag' }).click();
    await page.goto('/checkout');
    const progress = page.getByRole('list', { name: 'Checkout progress' });
    await expect(progress.getByText('Details')).toBeVisible();
    await expect(progress.getByText('Payment')).toBeVisible();
    await expect(progress.getByText('Confirmation')).toBeVisible();
    await expect(progress.locator('[aria-current="step"]')).toContainText('Details');
  });
});
