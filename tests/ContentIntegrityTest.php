<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\CalendarArchive;
use PHPUnit\Framework\TestCase;

final class ContentIntegrityTest extends TestCase
{
    public function testManifestAndCanonicalImagesMatchInBothDirections(): void
    {
        $root = dirname(__DIR__);
        $blog = $root . '/public/blog';
        $manifest = $root . '/content/archive.json';
        $records = self::records($manifest);
        self::assertSame(53, (new CalendarArchive($manifest))->summary()->postCount);

        /** @var array<string, true> $published */
        $published = [];
        foreach ($records as $record) {
            $key = $record['month'] . '_' . $record['day'];
            self::assertArrayNotHasKey($key, $published, 'Duplicate manifest entry for ' . $key);
            $published[$key] = true;
            self::assertFileExists($blog . '/' . $key . '_thmb.jpg');
            self::assertFileExists($blog . '/' . $key . '_full.jpg');
        }
        self::assertCount(53, $published);

        self::assertCanonicalImagesMatchManifest($blog, 'thmb', $published);
        self::assertCanonicalImagesMatchManifest($blog, 'full', $published);
    }

    public function testMigratedTitlesRemainExact(): void
    {
        $records = self::records(dirname(__DIR__) . '/content/archive.json');
        $lines = array_map(
            static fn (array $record): string => sprintf(
                '%d %d %s',
                $record['month'],
                $record['day'],
                $record['title'],
            ),
            $records,
        );

        self::assertSame(
            'deaa4e9eaba2307179a3bb73d1ef9f93701608aea42f82e178bd59b937b6a54e',
            hash('sha256', implode("\n", $lines)),
        );
    }

    public function testAdditionalImageVariantsRemainPairedButAreNotPosts(): void
    {
        $blog = dirname(__DIR__) . '/public/blog';
        $variantThumbnails = [];
        foreach (glob($blog . '/*_thmb*.jpg') ?: [] as $thumbnail) {
            if (preg_match('/^[0-9]+_[0-9]+_thmb\.jpg$/D', basename($thumbnail)) === 1) {
                continue;
            }

            $variantThumbnails[] = basename($thumbnail);
            self::assertFileExists(str_replace('_thmb', '_full', $thumbnail));
        }

        sort($variantThumbnails);
        self::assertSame(['3_28_thmb_1.jpg'], $variantThumbnails);
        self::assertSame(53, (new CalendarArchive(dirname(__DIR__) . '/content/archive.json'))->summary()->postCount);
    }

    public function testLegacyTitleFilesAreRemoved(): void
    {
        self::assertSame([], glob(dirname(__DIR__) . '/public/blog/*.txt') ?: []);
    }

    /** @param array<string, true> $published */
    private static function assertCanonicalImagesMatchManifest(string $blog, string $size, array $published): void
    {
        $count = 0;
        foreach (glob($blog . '/*_' . $size . '.jpg') ?: [] as $image) {
            $matched = preg_match(
                '/^([1-9]|1[0-2])_([1-9]|[12][0-9]|3[01])_' . $size . '\.jpg$/D',
                basename($image),
                $match,
            );
            self::assertSame(1, $matched, 'Non-canonical image name: ' . basename($image));
            $month = (int) $match[1];
            $day = (int) $match[2];
            self::assertLessThanOrEqual(cal_days_in_month(CAL_GREGORIAN, $month, 2000), $day);
            self::assertArrayHasKey($month . '_' . $day, $published);
            $count++;
        }

        self::assertSame(53, $count);
    }

    /** @return list<array{month: int, day: int, title: string}> */
    private static function records(string $manifest): array
    {
        $json = file_get_contents($manifest);
        self::assertIsString($json);

        $records = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        /** @var list<array{month: int, day: int, title: string}> $records */

        return $records;
    }
}
