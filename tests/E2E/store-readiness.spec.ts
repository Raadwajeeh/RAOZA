import { expect, test } from '@playwright/test';

test.describe('store readiness smoke', () => {
  test('public trust pages and footer links are reachable', async ({ page }) => {
    await page.goto('/');
    const necessary = page.getByRole('button', { name: 'Necessary only' });
    if (await necessary.isVisible()) {
      await necessary.click();
      await expect(necessary).toBeHidden();
    }

    const expected = [
      ['Contact', '/pages/contact'],
      ['Help & FAQ', '/pages/faq'],
      ['Size guide', '/pages/size-guide'],
      ['Shipping & delivery', '/pages/shipping'],
      ['Returns & refunds', '/pages/returns'],
      ['Privacy', '/pages/privacy'],
      ['Cookies', '/pages/cookies'],
      ['Terms & conditions', '/pages/terms'],
    ] as const;

    const footer = page.locator('footer');
    for (const [name, href] of expected) {
      await expect(footer.getByRole('link', { name, exact: true })).toHaveAttribute('href', href);
      const response = await page.request.get(href);
      expect(response.ok(), `${href} should be reachable`).toBeTruthy();
    }
  });

  test('mobile menu is keyboard-contained while closed and usable when open', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/shop');
    const necessary = page.getByRole('button', { name: 'Necessary only' });
    if (await necessary.isVisible()) {
      await necessary.click();
      await expect(necessary).toBeHidden();
    }

    const menu = page.getByRole('navigation', { name: 'Mobile', includeHidden: true });
    await expect(menu).toHaveAttribute('inert', '');
    const button = page.getByRole('button', { name: 'Open menu' });
    await button.focus();
    await expect(button).toBeFocused();
    await button.press('Enter');
    await expect(menu).not.toHaveAttribute('inert', '');
    await expect(page.getByRole('link', { name: 'Shop all' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(menu).toHaveAttribute('inert', '');
  });

  test('checkout exposes order totals, policy links and explicit terms acceptance', async ({ page }) => {
    await page.goto('/products/raoza-signature-tee');
    await page.getByRole('button', { name: 'M', exact: true }).click();
    await page.getByRole('button', { name: 'Cream', exact: true }).click();
    await page.getByRole('button', { name: 'Add to bag' }).click();
    await page.getByRole('link', { name: /View bag/ }).click();
    await page.getByRole('link', { name: 'Continue to checkout' }).click();

    await expect(page.getByRole('heading', { name: 'Order summary' })).toBeVisible();
    await expect(page.getByText('Estimated total')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Privacy Policy' })).toHaveAttribute('href', '/pages/privacy');
    await expect(page.getByRole('link', { name: 'Shipping', exact: true })).toHaveAttribute('href', '/pages/shipping');
    await expect(page.getByRole('link', { name: 'Returns', exact: true })).toHaveAttribute('href', '/pages/returns');
    const terms = page.getByRole('checkbox', { name: /agree to the Terms/ });
    await expect(terms).toHaveAttribute('required', '');
    await expect(terms).not.toBeChecked();
  });
});
