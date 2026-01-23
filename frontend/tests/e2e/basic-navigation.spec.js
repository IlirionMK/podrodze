import { test, expect } from '@playwright/test';

test.describe('Basic Navigation', () => {
  test('homepage loads correctly', async ({ page }) => {
    await page.goto('/');
    
    // Check if the page loads
    await expect(page).toHaveTitle(/PoDrodze/);
    
    // Check for header elements
    await expect(page.locator('header')).toBeVisible();
    await expect(page.locator('text=PoDrodze')).toBeVisible();
  });

  test('language switcher works', async ({ page }) => {
    await page.goto('/');
    
    // Find language switcher buttons
    const plButton = page.locator('button:has-text("PL")');
    const enButton = page.locator('button:has-text("EN")');
    
    await expect(plButton).toBeVisible();
    await expect(enButton).toBeVisible();
    
    // Test language switching
    await enButton.click();
    await expect(plButton).not.toHaveClass(/bg-blue-600/);
    await expect(enButton).toHaveClass(/bg-blue-600/);
  });

  test('navigation links are present', async ({ page }) => {
    await page.goto('/');
    
    // Check for login/register links when not authenticated
    await expect(page.locator('a[href="/login"]')).toBeVisible();
    await expect(page.locator('a[href="/register"]')).toBeVisible();
  });

  test('footer is displayed', async ({ page }) => {
    await page.goto('/');
    
    // Check footer elements
    await expect(page.locator('footer')).toBeVisible();
    await expect(page.locator('text=PoDrodze')).toBeVisible();
    await expect(page.locator('text=footer.student_project')).toBeVisible();
  });

  test('responsive design works on mobile', async ({ page }) => {
    await page.goto('/');
    await page.setViewportSize({ width: 375, height: 667 }); // iPhone size
    
    // Check if mobile layout works
    await expect(page.locator('header')).toBeVisible();
    
    // Check if elements are properly sized for mobile
    const header = page.locator('header .max-w-7xl');
    await expect(header).toBeVisible();
  });
});
