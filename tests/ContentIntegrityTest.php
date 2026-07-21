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

        foreach (glob($blog . '/*.txt') ?: [] as $titleFile) {
            $monthName = pathinfo($titleFile, PATHINFO_FILENAME);
            if (preg_match('/^[0-9]{1,2}$/D', $monthName) !== 1) {
                continue;
            }

            foreach (file($titleFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $matched = preg_match('/^([0-9]{1,2})\s+.+$/u', $line, $match);
                if ($matched !== 1) {
                    self::fail('Malformed title line in ' . basename($titleFile) . ': ' . $line);
                }
                $day = (int) $match[1];
                $prefix = $blog . '/' . (int) $monthName . '_' . $day;
                self::assertFileExists($prefix . '_thmb.jpg');
                self::assertFileExists($prefix . '_full.jpg');
                $titleCount++;
            }
        }

        self::assertSame(53, $titleCount);

        foreach (glob($blog . '/*_thmb*.jpg') ?: [] as $thumbnail) {
            self::assertFileExists(str_replace('_thmb', '_full', $thumbnail));
        }
        foreach (glob($blog . '/*_full*.jpg') ?: [] as $fullImage) {
            self::assertFileExists(str_replace('_full', '_thmb', $fullImage));
        }
    }
}
