import {defineConfig} from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 15_000,
    outputDir: '/tmp/playwright-results',
    reporter: 'line',
    expect: {
        timeout: 5_000,
        toHaveScreenshot: {
            animations: 'disabled',
            maxDiffPixelRatio: 0.001,
        },
    },
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://web',
        browserName: 'chromium',
        colorScheme: 'light',
        locale: 'en-US',
        reducedMotion: 'no-preference',
        screenshot: 'only-on-failure',
        timezoneId: 'UTC',
        trace: 'retain-on-failure',
        viewport: {width: 908, height: 900},
    },
});
