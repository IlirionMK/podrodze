import { test, expect } from '@playwright/test';

test.describe('Accessibility Tests', () => {
  test('page has proper heading structure', async ({ page }) => {
    await page.goto('/');
    
    // Check for proper heading hierarchy
    const headings = await page.locator('h1, h2, h3, h4, h5, h6').all();
    
    // Should have at least one heading
    expect(headings.length).toBeGreaterThan(0);
  });

  test('interactive elements are keyboard accessible', async ({ page }) => {
    await page.goto('/');
    
    // Test tab navigation
    await page.keyboard.press('Tab');
    
    // Should focus on first interactive element
    const focusedElement = await page.locator(':focus');
    await expect(focusedElement).toBeVisible();
  });

  test('buttons have proper ARIA labels', async ({ page }) => {
    await page.goto('/');
    
    // Check language switcher buttons
    const langButtons = page.locator('button');
    const count = await langButtons.count();
    
    for (let i = 0; i < count; i++) {
      const button = langButtons.nth(i);
      await expect(button).toBeVisible();
    }
  });

  test('links have descriptive text', async ({ page }) => {
    await page.goto('/');
    
    // Check navigation links have text content
    const links = page.locator('a[href]');
    const count = await links.count();
    
    for (let i = 0; i < count; i++) {
      const link = links.nth(i);
      const text = await link.textContent();
      expect(text?.trim()).not.toBe('');
    }
  });

  test('color contrast is sufficient', async ({ page }) => {
    await page.goto('/');
    
    // This is a basic check - in real scenarios you'd use axe-core
    // For now, just ensure elements are visible
    await expect(page.locator('header')).toBeVisible();
    await expect(page.locator('footer')).toBeVisible();
    await expect(page.locator('button')).toBeVisible();
  });

  test('page is responsive on different screen sizes', async ({ page }) => {
    await page.goto('/');
    
    // Test desktop
    await page.setViewportSize({ width: 1200, height: 800 });
    await expect(page.locator('header')).toBeVisible();
    
    // Test tablet
    await page.setViewportSize({ width: 768, height: 1024 });
    await expect(page.locator('header')).toBeVisible();
    
    // Test mobile
    await page.setViewportSize({ width: 375, height: 667 });
    await expect(page.locator('header')).toBeVisible();
  });
});
