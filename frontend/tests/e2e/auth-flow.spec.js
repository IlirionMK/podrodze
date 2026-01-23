import { test, expect } from '@playwright/test';

test.describe('Authentication Flow', () => {
  test('navigates to login page', async ({ page }) => {
    await page.goto('/');
    
    // Click login link
    await page.click('a[href="/login"]');
    
    // Should navigate to login page
    await expect(page).toHaveURL(/.*login/);
  });

  test('navigates to register page', async ({ page }) => {
    await page.goto('/');
    
    // Click register link
    await page.click('a[href="/register"]');
    
    // Should navigate to register page
    await expect(page).toHaveURL(/.*register/);
  });

  test('login/register buttons have proper styling', async ({ page }) => {
    await page.goto('/');
    
    // Check register button styling
    const registerButton = page.locator('a[href="/register"]');
    await expect(registerButton).toHaveClass(/bg-gradient-to-r/);
    await expect(registerButton).toHaveClass(/from-blue-600/);
    await expect(registerButton).toHaveClass(/to-purple-600/);
    await expect(registerButton).toHaveClass(/text-white/);
  });

  test('header shows different states for authenticated/unauthenticated users', async ({ page }) => {
    await page.goto('/');
    
    // Initially should show login/register for unauthenticated users
    await expect(page.locator('a[href="/login"]')).toBeVisible();
    await expect(page.locator('a[href="/register"]')).toBeVisible();
    
    // Should not show user menu
    await expect(page.locator('.relative button img')).not.toBeVisible();
  });
});
