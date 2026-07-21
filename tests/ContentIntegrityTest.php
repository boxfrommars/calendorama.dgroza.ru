<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use PHPUnit\Framework\TestCase;

final class ContentIntegrityTest extends TestCase
{
    public function testPublishedTitlesHaveThumbnailAndFullSizeImages(): void
    {
        $blog = dirname(__DIR__) . '/public/blog';
        $titleCount = 0;
        $published = [];

        foreach (glob($blog . '/*.txt') ?: [] as $titleFile) {
            $monthName = pathinfo($titleFile, PATHINFO_FILENAME);
            if (preg_match('/^[0-9]{1,2}$/D', $monthName) !== 1) {
                continue;
            }

            $month = (int) $monthName;
            self::assertGreaterThanOrEqual(1, $month);
            self::assertLessThanOrEqual(12, $month);

            foreach (file($titleFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $matched = preg_match('/^([0-9]{1,2})\s+.+$/u', $line, $match);
                if ($matched !== 1) {
                    self::fail('Malformed title line in ' . basename($titleFile) . ': ' . $line);
                }
                $day = (int) $match[1];
                self::assertGreaterThanOrEqual(1, $day);
                self::assertLessThanOrEqual(cal_days_in_month(CAL_GREGORIAN, $month, 2000), $day);
                $key = $month . '_' . $day;
                self::assertArrayNotHasKey($key, $published, 'Duplicate title for ' . $key);
                $published[$key] = true;
                $prefix = $blog . '/' . $key;
                self::assertFileExists($prefix . '_thmb.jpg');
                self::assertFileExists($prefix . '_full.jpg');
                $titleCount++;
            }
        }

        self::assertSame(53, $titleCount);

        $canonicalThumbnails = 0;
        foreach (glob($blog . '/*_thmb.jpg') ?: [] as $thumbnail) {
            $matched = preg_match('/^([1-9]|1[0-2])_([1-9]|[12][0-9]|3[01])_thmb\.jpg$/D', basename($thumbnail), $match);
            self::assertSame(1, $matched, 'Non-canonical thumbnail name: ' . basename($thumbnail));
            $month = (int) $match[1];
            $day = (int) $match[2];
            self::assertLessThanOrEqual(cal_days_in_month(CAL_GREGORIAN, $month, 2000), $day);
            self::assertArrayHasKey($month . '_' . $day, $published);
            $canonicalThumbnails++;
        }
        self::assertSame(53, $canonicalThumbnails);

        foreach (glob($blog . '/*_thmb*.jpg') ?: [] as $thumbnail) {
            self::assertFileExists(str_replace('_thmb', '_full', $thumbnail));
        }
        foreach (glob($blog . '/*_full*.jpg') ?: [] as $fullImage) {
            self::assertFileExists(str_replace('_full', '_thmb', $fullImage));
        }
    }
}
