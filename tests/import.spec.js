import { test, expect } from '@playwright/test';
test('upload valid CSV', async ({ page }) => {
    await page.goto('http://fyp-dashboard.test/fyp-projects');

    // 👇 PUT IT HERE (debug stop point)
    await page.pause();

    const filePath = path.resolve(__dirname, 'Files/valid.csv');

    await page.click('button:has-text("Import CSV")');

    const fileInput = page.locator('input[type="file"]');

    await expect(fileInput).toBeVisible({ timeout: 10000 });

    await fileInput.setInputFiles(filePath);

    await page.click('button:has-text("Import")');

    await expect(page.locator('text=Import successful')).toBeVisible();
});
