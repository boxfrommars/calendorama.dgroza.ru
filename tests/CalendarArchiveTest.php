<?php

declare(strict_types=1);

namespace Calendorama\Tests;

use Calendorama\Calendar\ArchiveMonth;
use Calendorama\Calendar\CalendarArchive;
use PHPUnit\Framework\TestCase;

final class CalendarArchiveTest extends TestCase
{
    public function testReturnsCompletePostOrNull(): void
    {
        $archive = new CalendarArchive(dirname(__DIR__) . '/public/blog');

        $post = $archive->post(3, 1);
        self::assertNotNull($post);
        self::assertNotSame('no subject', $post->title);
        self::assertSame('blog/3_1_full.jpg', $post->fullUrl);
        self::assertSame('blog/3_1_thmb.jpg', $post->thumbnailUrl);
        self::assertNull($archive->post(3, 2));
    }

    public function testBuildsSummaryFromCanonicalThumbnails(): void
    {
        $summary = (new CalendarArchive(dirname(__DIR__) . '/public/blog'))->summary();

        self::assertCount(12, $summary->months);
        self::assertSame(53, $summary->postCount);
        self::assertSame(
            [5, 2, 27, 5, 2, 2, 1, 2, 1, 0, 2, 4],
            array_map(static fn (ArchiveMonth $month): int => $month->postCount, $summary->months),
        );
    }

    public function testIgnoresVariantsAndImpossibleDates(): void
    {
        $directory = self::temporaryDirectory();

        try {
            touch($directory . '/3_1_thmb.jpg');
            touch($directory . '/3_1_full.jpg');
            touch($directory . '/3_1_thmb_1.jpg');
            touch($directory . '/2_30_thmb.jpg');
            touch($directory . '/13_1_thmb.jpg');
            touch($directory . '/3_2_full.jpg');

            $summary = (new CalendarArchive($directory))->summary();

            self::assertSame(1, $summary->postCount);
            self::assertSame(1, $summary->months[2]->postCount);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }

    public function testFallsBackToNoSubject(): void
    {
        $directory = self::temporaryDirectory();

        try {
            touch($directory . '/3_1_thmb.jpg');
            $post = (new CalendarArchive($directory))->post(3, 1);

            self::assertNotNull($post);
            self::assertSame('no subject', $post->title);
        } finally {
            @unlink($directory . '/3_1_thmb.jpg');
            @rmdir($directory);
        }
    }

    private static function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/calendorama-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory));

        return $directory;
    }
}
