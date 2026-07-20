<?php

declare(strict_types=1);

$runner->test('published titles have thumbnail and full-size images', static function (): void {
    $blog = PROJECT_ROOT . '/public/blog';
    $titleCount = 0;

    foreach (glob($blog . '/*.txt') ?: [] as $titleFile) {
        $monthName = pathinfo($titleFile, PATHINFO_FILENAME);
        if (preg_match('/^[0-9]{1,2}$/D', $monthName) !== 1) {
            continue;
        }

        foreach (file($titleFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            Assert::true(
                preg_match('/^([0-9]{1,2})\s+.+$/u', $line, $match) === 1,
                'Malformed title line in ' . basename($titleFile) . ': ' . $line,
            );
            $day = (int) $match[1];
            $prefix = $blog . '/' . (int) $monthName . '_' . $day;
            Assert::true(is_file($prefix . '_thmb.jpg'), 'Missing thumbnail for ' . basename($prefix));
            Assert::true(is_file($prefix . '_full.jpg'), 'Missing full image for ' . basename($prefix));
            $titleCount++;
        }
    }

    Assert::same(53, $titleCount, 'Published title count changed');

    foreach (glob($blog . '/*_thmb*.jpg') ?: [] as $thumbnail) {
        Assert::true(is_file(str_replace('_thmb', '_full', $thumbnail)), 'Unpaired thumbnail: ' . basename($thumbnail));
    }
    foreach (glob($blog . '/*_full*.jpg') ?: [] as $fullImage) {
        Assert::true(is_file(str_replace('_full', '_thmb', $fullImage)), 'Unpaired full image: ' . basename($fullImage));
    }
});
