import {defineConfig} from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 15_000,
    outputDir: '/tmp/playwright-results',
    reporter: 'line',
    snapshotPathTemplate: '{testDir}/{testFilePath}-snapshots/{arg}-{platform}{ext}',
    projects: [
        {name: 'chromium', use: {browserName: 'chromium'}},
        {name: 'firefox', use: {browserName: 'firefox'}},
        {name: 'webkit', use: {browserName: 'webkit'}},
    ],
    expect: {
        timeout: 5_000,
        toHaveScreenshot: {
            animations: 'disabled',
            maxDiffPixelRatio: 0.001,
        },
    },
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://web',
        colorScheme: 'light',
        locale: 'en-US',
        reducedMotion: 'no-preference',
        screenshot: 'only-on-failure',
        timezoneId: 'UTC',
        trace: 'retain-on-failure',
        viewport: {width: 908, height: 900},
    },
});
