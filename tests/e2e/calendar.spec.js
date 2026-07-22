import {expect, test} from '@playwright/test';

function pageErrors(page, allowed = []) {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') errors.push(message.text());
    });

    return () => expect(errors.filter((error) => !allowed.some((pattern) => pattern.test(error)))).toEqual([]);
}

async function openCalendar(page, route = '#d2010_3') {
    await page.goto(`/${route}`);
    await expect(page.locator('[data-calendar-view]')).toBeVisible();
    await expect(page.locator('[data-region="month"]')).toHaveAttribute('aria-busy', 'false');
}

test('uses the server route when the URL has no hash', async ({page}) => {
    const assertNoErrors = pageErrors(page);
    const response = await page.goto('/');
    expect(response).not.toBeNull();
    expect((await response.headers())['content-security-policy']).toContain("default-src 'self'");

    const region = page.locator('[data-region="month"]');
    const route = await region.getAttribute('data-initial-calendar-route');
    expect(route).toMatch(/^#d[0-9]{4}_(?:[1-9]|1[0-2])$/);
    const match = route.match(/^#d([0-9]{4})_([0-9]{1,2})$/);
    await expect(page.locator(`[data-calendar-view][data-year="${match[1]}"][data-month="${match[2]}"]`)).toBeVisible();
    assertNoErrors();
});

test('keeps keyboard focus through month navigation and browser history', async ({page}) => {
    const assertNoErrors = pageErrors(page);
    await openCalendar(page);

    const next = page.locator('[data-calendar-direction="next"]');
    await next.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#d2010_4$/);
    await expect(page.locator('[data-calendar-view][data-month="4"]')).toBeVisible();
    await expect(page.locator('[data-calendar-direction="next"]')).toBeFocused();

    await page.goBack();
    await expect(page).toHaveURL(/#d2010_3$/);
    await expect(page.locator('[data-calendar-view][data-month="3"]')).toBeVisible();
    assertNoErrors();
});

test('panels toggle, replace one another and close only from outside or Escape', async ({page}) => {
    const assertNoErrors = pageErrors(page);
    await openCalendar(page);

    const authorsButton = page.locator('button[popovertarget="authors-dialog"]');
    const authors = page.locator('#authors-dialog');
    await authorsButton.click();
    await expect(page.locator('#authors-dialog:popover-open')).toBeVisible();
    await authors.locator('h3').click();
    await expect(page.locator('#authors-dialog:popover-open')).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(page.locator('#authors-dialog:popover-open')).toHaveCount(0);
    await expect(authorsButton).toBeFocused();

    const aboutButton = page.locator('button[popovertarget="about-dialog"]');
    await aboutButton.click();
    await authorsButton.click();
    await expect(page.locator('#about-dialog:popover-open')).toHaveCount(0);
    await expect(page.locator('#authors-dialog:popover-open')).toBeVisible();

    await authorsButton.click();
    await expect(page.locator('#authors-dialog:popover-open')).toHaveCount(0);
    await expect(authorsButton).toBeFocused();

    await authorsButton.click();
    await page.locator('h1').click();
    await expect(page.locator('#authors-dialog:popover-open')).toHaveCount(0);
    assertNoErrors();
});

test('tooltip and image viewer work with mouse and keyboard', async ({page}) => {
    const assertNoErrors = pageErrors(page);
    await openCalendar(page);

    const rss = page.locator('.rss-link');
    await rss.hover();
    await expect(page.locator('[role="tooltip"]')).toBeVisible();
    await expect(page.locator('[role="tooltip"]')).toHaveText(/rss/);
    await page.locator('h1').hover();
    await expect(page.locator('[role="tooltip"]')).toBeHidden();

    const post = page.locator('[data-calendar-route="#d2010_3_1"]');
    await post.focus();
    await expect(page.locator('[role="tooltip"]')).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#d2010_3_1$/);
    await expect(page.locator('#post-view-dialog')).toHaveJSProperty('open', true);
    await expect(page.locator('#post-view-dialog img')).toHaveJSProperty('complete', true);

    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/#d2010_3$/);
    await expect(page.locator('#post-view-dialog')).not.toHaveAttribute('open', '');
    await expect(post).toBeFocused();

    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/#d2010_3_1$/);
    await expect(page.locator('#post-view-dialog')).toHaveJSProperty('open', true);
    await page.keyboard.press('Escape');
    await expect(page).toHaveURL(/#d2010_3$/);
    assertNoErrors();
});

test('a missing full-size image reports an error without opening the viewer', async ({page}) => {
    const assertNoErrors = pageErrors(page, [/status of 404/]);
    await page.goto('/#d2010_3_2');

    await expect(page.locator('[data-calendar-view][data-month="3"]')).toBeVisible();
    await expect(page.locator('[data-region="alert"]')).toBeVisible();
    await expect(page.locator('#post-view-dialog')).not.toHaveAttribute('open', '');
    await expect(page.locator('[data-region="month"]')).toHaveAttribute('aria-busy', 'false');
    assertNoErrors();
});

test('an obsolete slow request cannot replace the latest calendar', async ({page}) => {
    const assertNoErrors = pageErrors(page);
    await page.route(/\/handler\.php\?/, async (route) => {
        const parameters = new URL(route.request().url()).searchParams;
        if (parameters.get('month') === '4') {
            await new Promise((resolve) => setTimeout(resolve, 500));
        }
        try {
            await route.continue();
        } catch {
            // An aborted request can dispose its intercepted route before the delay ends.
        }
    });
    await openCalendar(page);

    await page.evaluate(() => { window.location.hash = '#d2010_4'; });
    await page.waitForTimeout(30);
    await page.evaluate(() => { window.location.hash = '#d2010_5'; });
    await expect(page.locator('[data-calendar-view][data-month="5"]')).toBeVisible();
    await page.waitForTimeout(600);
    await expect(page.locator('[data-calendar-view][data-month="5"]')).toBeVisible();
    await expect(page.locator('[data-region="month"]')).toHaveAttribute('aria-busy', 'false');
    await expect(page.locator('.calendar')).toHaveCSS('opacity', '1');
    assertNoErrors();
});

test('request errors preserve the old calendar and reduced motion skips animations', async ({page}) => {
    const assertNoErrors = pageErrors(page, [/status of 500/]);
    await page.emulateMedia({reducedMotion: 'reduce'});
    await page.route(/\/handler\.php\?/, async (route) => {
        const parameters = new URL(route.request().url()).searchParams;
        if (parameters.get('month') === '4') {
            await route.fulfill({status: 500, contentType: 'text/plain', body: 'failure'});
        } else {
            await route.continue();
        }
    });
    await openCalendar(page);

    await page.evaluate(() => { window.location.hash = '#d2010_4'; });
    await expect(page.locator('[data-region="alert"]')).toBeVisible();
    await expect(page.locator('[data-region="month"]')).toHaveAttribute('aria-busy', 'false');
    await expect(page.locator('[data-calendar-view][data-month="3"]')).toBeVisible();
    await expect(page.locator('.calendar')).toHaveCSS('opacity', '1');
    expect(await page.locator('.calendar').evaluate((element) => element.getAnimations().length)).toBe(0);
    assertNoErrors();
});

test('fixed calendar, panel and viewer match visual baselines', async ({page, browserName}) => {
    test.skip(browserName !== 'chromium', 'Visual baselines are intentionally Chromium-only.');
    const assertNoErrors = pageErrors(page);
    await page.emulateMedia({reducedMotion: 'reduce'});
    await openCalendar(page);
    await expect(page).toHaveScreenshot('calendar-march-2010.png', {fullPage: true});

    await page.locator('button[popovertarget="about-dialog"]').click();
    await expect(page.locator('#about-dialog:popover-open')).toBeVisible();
    await expect(page).toHaveScreenshot('about-panel.png', {fullPage: true});

    await page.keyboard.press('Escape');
    await page.locator('[data-calendar-route="#d2010_3_1"]').click();
    await expect(page.locator('#post-view-dialog')).toHaveJSProperty('open', true);
    await expect(page).toHaveScreenshot('image-viewer.png', {fullPage: true});
    assertNoErrors();
});
